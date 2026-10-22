<?php

namespace App\Services;

use App\Models\Checklist;
use App\Models\Image;
use App\Models\Response;
use App\Models\Section;
use App\Models\Submission;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ImageKit\ImageKit;
use RuntimeException;

class ResponseService
{
    private Response $response;
    private ImageKit $imageKit;

    public function __construct(Response $response)
    {
        $this->response = $response;
        $this->imageKit = new ImageKit(
            config('app.imagekit_public_key'),
            config('app.imagekit_private_key'),
            config('app.imagekit_url_endpoint')
        );
    }

    public function getResponses($request)
    {
        $responses = $this->response->with('user')->useFilters()->get();
        $section = strtolower($request->section);

        $month = (int) ($request->month ?? Carbon::now()->month);
        $year  = (int) ($request->year ?? Carbon::now()->year);

        return match ($section) {
            'pests', 'birds' => $this->formatPestAndBirdsResponses($responses, $section, $month, $year),
            default => $this->formatCobsResponses($responses, $month, $year),
        };
    }

    public function formatCobsResponses($responses, int $month, int $year)
    {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $batchesByUnit = $batches->groupBy('unit_id');

        return Unit::query()->with([
            'checkLists' => fn ($query) => $query->withoutTrashed(),
        ])->get()->mapWithKeys(function ($unit) use ($batchesByUnit, $month, $year) {
            return $this->formatUnitResponse($unit, $batchesByUnit, $month, $year);
        });
    }

    public function formatPestAndBirdsResponses($responses, $section, int $month, int $year)
    {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $checklists = Section::query()
            ->with(['checkLists'])
            ->where('name', $section)
            ->first()
            ?->checkLists ?? collect();

        // Only pests has a fixed "must submit N times" rule. Birds' period count is
        // calendar-driven (4 or 5 depending on the month), so no required count applies.
        $requiredCount = $section === 'birds' ? null : 2;

        $userId = data_get($responses->first(), 'user_id') ?? auth()->id();

        $periodsInMonth = $section === 'birds'
            ? Carbon::createFromDate($year, $month, 1)->endOfMonth()->weekOfMonth()
            : null;

        return $checklists->mapWithKeys(function ($checklist) use ($batches, $section, $requiredCount, $userId, $periodsInMonth) {
            $checklistBatches = $batches->where('checklist_id', $checklist->id);

            if ($section === 'birds') {
                $periods = collect(range(1, $periodsInMonth))
                    ->mapWithKeys(fn ($week) => ['Period ' . $week => collect()])
                    ->all();

                foreach ($checklistBatches as $batch) {
                    $week = Carbon::parse($batch['start_at'])->weekOfMonth();

                    if ($week < 1 || $week > $periodsInMonth) {
                        continue;
                    }

                    $periods['Period ' . $week]->push($batch);
                }

                $inspectionAreas = collect($checklist->items)
                    ->firstWhere('name', 'Inspection Areas')['items'] ?? [];
            } else {
                $periods = [
                    'Period 1' => $checklistBatches->filter(fn ($b) => Carbon::parse($b['start_at'])->day <= 15)->values(),
                    'Period 2' => $checklistBatches->filter(fn ($b) => Carbon::parse($b['start_at'])->day > 15)->values(),
                ];
            }

            // null (not false) for birds, so the frontend can tell "not applicable" from "not completed".
            $previousMonthCompleted = ($requiredCount !== null && $userId)
                ? $this->checkPreviousMonthCompleted($userId, $checklist->id, $requiredCount)
                : null;

            return [
                $checklist->checklist_name => [
                    'id'                       => $checklist->id,
                    'checklist_name'           => $checklist->checklist_name,
                    'created_at'               => Carbon::parse($checklist->created_at)->format('Y-m-d'),
                    'previous_month_completed' => $previousMonthCompleted,
                    'periods'                  => $periods,
                    ...($section === 'birds' ? ['inspection_areas' => $inspectionAreas] : []),
                ],
            ];
        });
    }

    private function formatUnitResponse($unit, $batchesByUnit, int $month, int $year)
    {
        $unitBatches = $batchesByUnit->get($unit->id, collect());
        $batchesByWeek = $unitBatches->groupBy('week');
        $checklists = $unit->checkLists;

        $firstBatch = $unitBatches->first();
        $userId = $firstBatch['user_id'] ?? null;

        // Previous month completion for cobs (required: 4 times)
        $previousMonthCompleted = $this->checkPreviousMonthCompleted($userId, data_get($unit->checkLists->first(), 'id'), 4);

        $weeksInMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->weekOfMonth();

        return [
            'Unit: ' . $unit->name => [
                'unit_id' => $unit->id,
                'previous_month_completed' => $previousMonthCompleted,
                'checklists' => $checklists->map(function ($checklist) {
                    return [
                        'id' => $checklist->id,
                        'checklist_name' => $checklist->checklist_name,
                        'created_at' => Carbon::parse($checklist->created_at)->format('Y-m-d'),
                    ];
                })->values(),
                'weeks' => collect(range(1, $weeksInMonth))->mapWithKeys(function ($week) use ($batchesByWeek) {
                    return [
                        'Week ' . $week => $batchesByWeek->get($week, collect())->values()->all(),
                    ];
                })->all(),
            ],
        ];
    }

    /** Same payload as before: response[n] + image[n][m]. */
    public function storeResponse(array $data): ?array
    {
        $responses = $data['response'] ?? [];

        // Ignore image groups with no matching response key (avoids orphan uploads).
        $imageItems = array_intersect_key($data['image'] ?? $data['images'] ?? [], $responses);

        // 1. Slow network work first, outside the transaction.
        $uploaded = $this->uploadImageGroups($imageItems);

        try {
            // 2. All DB work is atomic.
            $batchNo = DB::transaction(function () use ($data, $responses, $uploaded) {
                $existingBatchNo = $data['batch_no'] ?? null;

                if ($existingBatchNo) {
                    $ids = $this->response->where('batch_no', $existingBatchNo)->pluck('id');
                    Image::whereIn('response_id', $ids)->forceDelete();
                    $this->response->where('batch_no', $existingBatchNo)->forceDelete();
                }

                $batchNo  = $existingBatchNo ?: $this->generateBatchNo();
                $baseData = $this->buildBaseResponseData($data, $batchNo);

                foreach ($responses as $key => $value) {
                    $response = $this->response->create(array_merge($baseData, [
                        'response' => $this->parseData($value),
                    ]));

                    foreach ($uploaded[$key] ?? [] as $file) {
                        Image::create([
                            'response_id' => $response->id,
                            'url'         => $file['url'],
                            'file_id'     => $file['file_id'],
                        ]);
                    }
                }

                // The approval steps come from the section's approval flow.
                if (! empty($data['is_completed'])) {
                    app(SubmissionWorkflow::class)->finalize((int) $batchNo, (bool) ($data['requires_evaluator'] ?? false));
                }

                return (int) $batchNo;
            });
        } catch (\Throwable $e) {
            $this->discardUploads($uploaded);   // don't leave orphans in ImageKit
            throw $e;
        }

        return $this->generateSummaryReportByBatchNo($batchNo);
    }

    private function uploadImageGroups(array $imageItems): array
    {
        $uploaded = [];

        try {
            foreach ($imageItems as $key => $images) {
                foreach (Arr::wrap($images) as $image) {
                    if (! $image instanceof UploadedFile || ! $image->isValid()) {
                        continue;
                    }
                    $uploaded[$key][] = $this->uploadToImageKit($image);
                }
            }
        } catch (\Throwable $e) {
            $this->discardUploads($uploaded);   // partial batch: roll back what already went up
            throw $e;
        }

        return $uploaded;
    }

    private function uploadToImageKit(UploadedFile $image): array
    {
        $handle = fopen($image->getRealPath(), 'r');

        try {
            $result = $this->imageKit->uploadFile([
                'file'     => $handle,
                'fileName' => Str::uuid() . '.' . ($image->guessExtension() ?? 'jpg'),
            ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $url = data_get($result, 'result.url');

        if (! $url) {
            throw new RuntimeException('ImageKit upload failed: ' . json_encode(data_get($result, 'error')));
        }

        return ['url' => $url, 'file_id' => data_get($result, 'result.fileId')];
    }

    private function discardUploads(array $uploaded): void
    {
        foreach (collect($uploaded)->flatten(1) as $file) {
            try {
                if (! empty($file['file_id'])) {
                    $this->imageKit->deleteFile($file['file_id']);
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not clean up ImageKit file ' . ($file['file_id'] ?? '?') . ': ' . $e->getMessage());
            }
        }
    }

    private function parseData($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return ['value' => $value];
        }

        return is_array($value) ? $value : ['value' => $value];
    }

    private function generateBatchNo()
    {
        return DB::table('responses')->max('batch_no') + 1;
    }

    public function generateSummaryReportByBatchNo($batchNo)
    {
        $response = $this->response->where('batch_no', $batchNo)->get();

        return $response->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values()->first();
    }

    private function formatBatchResponse($batchResponses, $batchNo)
    {
        $firstResponse = $batchResponses->first();
        $startAt = $firstResponse?->start_at;
        $countSubItems = $firstResponse?->checklist?->countSubItems();
        $countResponses = $batchResponses->count();
        $progress = $countSubItems > 0 ? ($countResponses / $countSubItems) * 100 : 0;

        $scoreData = $this->computeHierarchicalScore($firstResponse, $batchResponses);

        // Approval progress comes only from the submission's steps.
        $submission = Submission::with('steps.type', 'steps.assignees.user')
            ->where('legacy_batch_no', $batchNo)->first();
        $submitted = $submission && ! $submission->isDraft();
        $steps = $submitted ? $submission->steps : collect();

        $stepOf = fn (string $key) => $steps->first(fn ($s) => $s->type->key === $key);

        $signatoryOf = function (string $key) use ($stepOf) {
            $step = $stepOf($key);
            if (! $step || $step->status !== 'done') {
                return null;
            }
            $who = $step->assignees->firstWhere('decision', 'approved') ?? $step->assignees->first();

            return ['name' => $who?->signature_name, "{$key}_image" => $who?->signature_image];
        };

        $personOf = fn (string $key) => $stepOf($key)?->assignees->first();
        $isDone   = fn (string $key) => $stepOf($key) ? $stepOf($key)->status === 'done' : null;

        $signatory1 = $signatoryOf('evaluate');
        $signatory2 = $signatoryOf('approve');
        $signatory3 = $signatoryOf('assess');

        $status = $submitted
            ? $submission->status()
            : ($firstResponse?->is_completed ? 'Done' : 'Draft');

        $evaluator = $personOf('evaluate');
        $approver  = $personOf('approve');
        $assessor  = $personOf('assess');

        return [
            'batch_no' => (int) $batchNo,
            'progress' => (int) $progress . '%',
            'score' => (int) $scoreData['score'] ?: null,
            'score_breakdown' => $scoreData['breakdown'] ?: null,
            'checklist_id' => $firstResponse?->checklist_id,
            'checklist_name' => $firstResponse?->checklist?->checklist_name,
            'unit_id' => $firstResponse?->unit_id,
            'unit' => $firstResponse?->unit?->name,
            'user_id' => $firstResponse?->user_id,
            'user' => $firstResponse?->user?->getFullNameAttribute(),
            'user_signatory' => $firstResponse?->user?->signature,
            // Kept for the current frontend, now derived from the steps.
            'evaluator_id' => $evaluator?->user_id,
            'evaluator' => $evaluator?->user?->getFullNameAttribute(),
            'approver_id' => $approver?->user_id,
            'approver' => $approver?->user?->getFullNameAttribute(),
            'assessor_id' => $assessor?->user_id,
            'assessor' => $assessor?->user?->getFullNameAttribute(),
            'is_completed' => $firstResponse?->is_completed,
            'is_evaluated' => $isDone('evaluate'),
            'is_approved' => $isDone('approve'),
            'is_assessed' => $isDone('assess'),
            'good_points' => $firstResponse?->good_points,
            'remarks' => $firstResponse?->duplicate_reason ?: $firstResponse?->remarks,
            'notes' => $firstResponse?->notes,
            'temporal_audit' => $firstResponse?->temporal_audit,
            'start_at' => $startAt,
            'end_at' => $firstResponse?->end_at,
            'week' => $startAt ? (int) ceil(Carbon::parse($startAt)->day / 7) : null,
            'responses' => $batchResponses->map(function ($response) {
                return [
                    'id' => $response->id,
                    'response' => $response->response,
                    'images' => $response->images->pluck('url'),
                ];
            })->values(),
            'signatory_1' => $signatory1,
            'signatory_2' => $signatory2,
            'signatory_3' => $signatory3,
            'status'      => $status,
            // Every step in order, so any number of approvers can be shown.
            'steps' => $steps->map(fn ($s) => [
                'order'     => $s->step_order,
                'type'      => $s->type->key,
                'label'     => $s->label,
                'status'    => $s->status,
                'assignees' => $s->assignees->map(fn ($a) => [
                    'user_id'         => $a->user_id,
                    'name'            => $a->signature_name ?? $a->user?->getFullNameAttribute(),
                    'decision'        => $a->decision,
                    'acted_at'        => $a->acted_at,
                    'signature_image' => $a->signature_image,
                ])->values(),
            ])->values(),
        ];
    }

    private function checkPreviousMonthCompleted($userId, $checklistId, $requiredCount): ?bool
    {
        $year = (int) request()->input('year', now()->year);
        $month = (int) request()->input('month', now()->month);

        $previousMonth = Carbon::create($year, $month, 1)->subMonth();

        $count = Response::
        where('checklist_id', $checklistId)
            ->where('is_completed', true)
            ->whereMonth('start_at', $previousMonth->month)
            ->whereYear('start_at', $previousMonth->year)
            ->distinct()
            ->count('batch_no');

        return $count >= $requiredCount;
    }

    private function computeHierarchicalScore($firstResponse, $batchResponses)
    {
        $checklist = $firstResponse?->checklist;
        if (!$checklist) {
            return ['score' => 0, 'breakdown' => []];
        }

        $categories = $this->ensureArray($checklist->items ?? []);
        if (empty($categories)) {
            return ['score' => 0, 'breakdown' => []];
        }

        // ---- Phase 1: resolve responses & filter out N/A (score <= 0) entries ----
        $filteredCategories = [];

        foreach ($categories as $categoryIndex => $category) {
            $categoryName = $category['name'] ?? "Category $categoryIndex";
            $categoryItems = $category['items'] ?? [];
            $filteredItems = [];

            foreach ($categoryItems as $itemIndex => $item) {
                $itemName = $item['name'] ?? "Item $itemIndex";
                $subItems = $item['sub_items'] ?? [];
                $filteredSubItems = [];

                foreach ($subItems as $subItemIndex => $subItem) {
                    $subItemName = $subItem['name'] ?? "Sub-item $subItemIndex";

                    $responseValue = $this->findResponseValue(
                        $batchResponses,
                        $categoryIndex,
                        $itemIndex,
                        $subItemIndex
                    );

                    if (!is_numeric($responseValue) || (float) $responseValue <= 0) {
                        continue;
                    }

                    $filteredSubItems[] = [
                        'name'  => $subItemName,
                        'value' => (float) $responseValue,
                    ];
                }

                if (empty($filteredSubItems)) {
                    continue;
                }

                $filteredItems[] = [
                    'name'      => $itemName,
                    'sub_items' => $filteredSubItems,
                ];
            }

            if (empty($filteredItems)) {
                continue;
            }

            $filteredCategories[] = [
                'name'  => $categoryName,
                'items' => $filteredItems,
            ];
        }

        if (empty($filteredCategories)) {
            return ['score' => 0, 'breakdown' => []];
        }

        // ---- Phase 2: weighted scoring, using filtered counts as denominators ----
        $totalScore = 0;
        $totalPossibleScore = 0;
        $categoryCount = count($filteredCategories);
        $breakdown = [];

        foreach ($filteredCategories as $category) {
            $categoryWeight = 1 / $categoryCount;
            $itemCount = count($category['items']);
            $categoryScore = 0;
            $categoryPossibleScore = 0;
            $itemsBreakdown = [];

            foreach ($category['items'] as $item) {
                $itemWeight = 1 / $itemCount;
                $subItemCount = count($item['sub_items']);
                $itemScore = 0;
                $itemPossibleScore = 0;
                $subItemsBreakdown = [];

                foreach ($item['sub_items'] as $subItem) {
                    $subItemWeight = 1 / $subItemCount;
                    $normalizedValue = $subItem['value'] / 100;

                    $subItemScore = $categoryWeight * $itemWeight * $subItemWeight * $normalizedValue;
                    $subItemPossibleScore = $categoryWeight * $itemWeight * $subItemWeight;

                    $itemScore += $subItemScore;
                    $itemPossibleScore += $subItemPossibleScore;
                    $totalScore += $subItemScore;
                    $totalPossibleScore += $subItemPossibleScore;

                    $subItemBasePercentage = $subItemWeight * 100;
                    $subItemContributionPercentage = $subItemBasePercentage * $normalizedValue;

                    $subItemsBreakdown[] = [
                        'name'       => $subItem['name'],
                        'score'      => (int) $subItem['value'],
                        'allocation' => $subItemBasePercentage,
                        'percentage' => round($subItemContributionPercentage, 2),
                    ];
                }

                $itemPercentage = $itemPossibleScore > 0
                    ? round(($itemScore / $itemPossibleScore) * 100, 2)
                    : 0;
                $itemBaseAllocation = $itemWeight * 100;
                $categoryScore += $itemScore;
                $categoryPossibleScore += $itemPossibleScore;

                $itemsBreakdown[] = [
                    'name'       => $item['name'],
                    'score'      => $itemPercentage,
                    'allocation' => $itemBaseAllocation,
                    'percentage' => $itemPercentage,
                    'sub_items'  => $subItemsBreakdown,
                ];
            }

            $categoryPercentage = $categoryPossibleScore > 0
                ? round(($categoryScore / $categoryPossibleScore) * 100, 2)
                : 0;

            $breakdown[] = [
                'category'   => $category['name'],
                'score'      => (round($categoryWeight * 100, 2) / 100) * $categoryPercentage,
                'percentage' => $categoryPercentage,
                'allocation' => round($categoryWeight * 100, 2),
                'items'      => $itemsBreakdown,
            ];
        }

        $totalScore = $totalPossibleScore > 0
            ? round(($totalScore / $totalPossibleScore) * 100, 2)
            : 0;

        return [
            'score'     => $totalScore,
            'breakdown' => $breakdown,
        ];
    }

    private function findResponseValue($batchResponses, $categoryIndex, $itemIndex, $subItemIndex)
    {
        $checklist = $batchResponses->first()?->checklist;
        if (!$checklist) return 0;

        $categories = $this->ensureArray($checklist->items ?? []);
        if (!isset($categories[$categoryIndex])) return 0;

        $categoryName = $categories[$categoryIndex]['name'] ?? null;
        $categoryItems = $categories[$categoryIndex]['items'] ?? [];

        if (!isset($categoryItems[$itemIndex])) return 0;

        $itemName = $categoryItems[$itemIndex]['name'] ?? null;
        $subItems = $categoryItems[$itemIndex]['sub_items'] ?? [];

        if (!isset($subItems[$subItemIndex])) return 0;

        $subItemName = $subItems[$subItemIndex]['name'] ?? null;

        foreach ($batchResponses as $response) {
            $resp = $response->response;

            if (isset($resp['checklist']) && isset($resp['item']) && isset($resp['sub_item'])) {
                if ($resp['checklist'] == $categoryName &&
                    $resp['item'] == $itemName &&
                    $resp['sub_item'] == $subItemName) {
                    return $resp['score'] ?? 0;
                }
            }
        }

        return 0;
    }

    private function ensureArray($data)
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_object($data) ? (array) $data : [];
    }

    /** Header fields shared by every answer row. No approver data lives here any more. */
    public function buildBaseResponseData(array $data, ?string $batchNo = null): array
    {
        return [
            'checklist_id'   => $data['checklist_id'] ?? null,
            'unit_id'        => $data['unit_id'] ?? null,
            'user_id'        => auth()->user()->id ?? $data['user_id'] ?? null,
            'batch_no'       => $data['batch_no'] ?? $batchNo,
            'good_points'    => $data['good_points'] ?? null,
            'remarks'        => $data['remarks'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'temporal_audit' => $data['temporal_audit'] ?? null,
            'start_at'       => $data['start_at'] ?? Carbon::now(),
            'is_completed'   => $data['is_completed'] ?? null,
            'end_at'         => isset($data['is_completed']) && $data['is_completed'] ? Carbon::now() : null,
        ];
    }

    public function getImageKit()
    {
        return $this->imageKit;
    }

    public function mergeResponse(array $data): void
    {
        $month = (int) ($data['month'] ?? 0);
        $year  = (int) ($data['year'] ?? 0);

        if ($month < 1 || $month > 12 || $year < 1970) {
            return;
        }

        $completedCobsResponses = $this->response
            ->cobs()
            ->completed()
            ->inThirdWeekOf($month, $year)
            ->get(['id', 'batch_no']);

        if ($completedCobsResponses->isEmpty()) {
            return;
        }

        $batchNos = $completedCobsResponses->pluck('batch_no')->unique();

        DB::transaction(function () use ($batchNos) {
            $responsesToCopy = $this->response
                ->whereIn('batch_no', $batchNos)
                ->with('images')
                ->get();

            if ($responsesToCopy->isEmpty()) {
                return;
            }

            $newBatchNo = $this->generateBatchNo();

            $responsesToCopy->each(function (Response $response) use ($newBatchNo) {
                $copy = $response->replicate();

                $copy->batch_no = $newBatchNo;
                $copy->submission_id = null;   // the copy must not point at the original's submission
                $copy->start_at = Carbon::parse($response->start_at)->addDays(7);
                $copy->end_at   = $response->end_at
                    ? Carbon::parse($response->end_at)->addDays(7)
                    : null;

                $copy->save();

                $response->images->each(function (Image $image) use ($copy) {
                    $imageCopy = $image->replicate();
                    $imageCopy->response_id = $copy->id;
                    $imageCopy->save();
                });
            });
        });
    }

    private function resolveChecklistSectionName(?int $checklistId): ?string
    {
        if (!$checklistId) {
            return null;
        }

        $checklist = Checklist::with('section')->find($checklistId);

        return $checklist?->section?->name
            ? strtolower($checklist->section->name)
            : null;
    }

    public function additionalAttachment(UploadedFile|array $images, int $responseId): array
    {
        $response = $this->response->findOrFail($responseId);

        // Flatten so image[0][] / image[0][0] shapes work as well as image[].
        $files = collect(Arr::flatten(Arr::wrap($images)));

        [$valid, $invalid] = $files->partition(
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        );

        // Reject the whole request if anything is unusable; never drop images silently.
        if ($valid->isEmpty() || $invalid->isNotEmpty()) {
            $reasons = $invalid
                ->map(fn ($file) => $file instanceof UploadedFile
                    ? $file->getErrorMessage()
                    : 'Unexpected input of type ' . get_debug_type($file))
                ->unique()
                ->values()
                ->all();

            \Log::warning("additionalAttachment rejected (response_id: {$responseId})", [
                'received' => $files->count(),
                'reasons'  => $reasons,
            ]);

            throw ValidationException::withMessages([
                'image' => $reasons ?: ['No images were received.'],
            ]);
        }

        $uploaded = [];

        try {
            foreach ($valid as $image) {
                $uploaded[] = $this->uploadToImageKit($image);
            }

            return DB::transaction(function () use ($response, $uploaded) {
                return collect($uploaded)->map(function (array $file) use ($response) {
                    $image = Image::create([
                        'response_id' => $response->id,
                        'url'         => $file['url'],
                        'file_id'     => $file['file_id'],
                    ]);

                    return ['id' => $image->id, 'url' => $image->url];
                })->all();
            });
        } catch (\Throwable $e) {
            $this->discardUploads([$uploaded]);
            \Log::error("additionalAttachment failed (response_id: {$responseId}): " . $e->getMessage());
            throw $e;
        }
    }

    public function truncateResponse()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('images')->truncate();
        DB::table('responses')->truncate();
        DB::table('submission_step_assignees')->truncate();
        DB::table('submission_steps')->truncate();
        DB::table('submissions')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array{batch_no: int, responses: array<int, array{index: int, id: int}>} */
    public function createDraft(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $batchNo = $this->generateBatchNo();

            $base = array_merge(
                $this->buildBaseResponseData($data, $batchNo),
                ['is_completed' => false, 'end_at' => null]
            );

            $created = [];
            foreach ($data['responses'] as $index => $item) {
                $row = $this->response->create(array_merge($base, [
                    'response' => $this->parseData($item['response']),
                ]));
                $created[] = ['index' => $index, 'id' => $row->id];
            }

            return ['batch_no' => (int) $batchNo, 'responses' => $created];
        });
    }

    public function updateDraft(int $batchNo, array $data): array
    {
        $first = $this->findOwnedBatch($batchNo);
        $this->assertDraft($first);

        return DB::transaction(function () use ($batchNo, $data, $first) {
            $rows = $this->response->where('batch_no', $batchNo)->get()->keyBy('id');

            $header = Arr::only($data, ['start_at', 'good_points', 'remarks', 'notes', 'temporal_audit']);
            if ($header) {
                $rows->each(fn (Response $row) => $row->update($header));
            }

            $base = array_merge(
                $first->only([
                    'checklist_id', 'unit_id', 'user_id', 'batch_no', 'good_points',
                    'remarks', 'notes', 'temporal_audit', 'start_at',
                ]),
                $header,
                ['is_completed' => false, 'end_at' => null]
            );

            $result = [];
            foreach ($data['responses'] ?? [] as $index => $item) {
                $payload = $this->parseData($item['response']);

                if (! empty($item['id'])) {
                    // Must belong to THIS batch, otherwise a client could overwrite someone else's row.
                    $row = $rows->get((int) $item['id'])
                        ?? throw ValidationException::withMessages([
                            "responses.$index.id" => ['This response does not belong to the batch.'],
                        ]);

                    $row->update(['response' => $payload]);
                } else {
                    $row = $this->response->create(array_merge($base, ['response' => $payload]));
                }

                $result[] = ['index' => $index, 'id' => $row->id];
            }

            return ['batch_no' => $batchNo, 'responses' => $result];
        });
    }

    public function assertCanModifyAttachments(Response $response): void
    {
        abort_unless((int) $response->user_id === (int) auth()->id(), 403, 'Not your checklist.');
        $this->assertDraft($response);
    }

    public function deleteImage(Image $image): void
    {
        $response = $this->response->findOrFail($image->response_id);
        $this->assertCanModifyAttachments($response);

        $fileId = $image->file_id;
        $image->forceDelete();            // DB first: an orphaned remote file is better than a broken record

        if ($fileId) {
            try {
                $this->imageKit->deleteFile($fileId);
            } catch (\Throwable $e) {
                \Log::warning("Could not delete ImageKit file {$fileId}: " . $e->getMessage());
            }
        }
    }

    public function submit(int $batchNo): ?array
    {
        DB::transaction(function () use ($batchNo) {
            $rows = $this->response
                ->where('batch_no', $batchNo)
                ->withCount('images')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_if($rows->isEmpty(), 404, 'Batch not found.');

            $first = $rows->first();
            abort_unless((int) $first->user_id === (int) auth()->id(), 403, 'Not your checklist.');
            $this->assertDraft($first);

            $errors  = [];
            $section = $this->resolveChecklistSectionName($first->checklist_id);

            // Completeness count applies to cobs only.
            if (! in_array($section, ['pests', 'birds'], true)) {
                $expected = (int) $first->checklist?->countSubItems();
                if ($expected > 0 && $rows->count() < $expected) {
                    $errors['responses'] = ["Only {$rows->count()} of {$expected} items are answered."];
                }
            }

            // Photo-required rule is currently switched off (it was commented out in your version).
            // To enforce it again, filter $rows on images_count === 0 and add the errors here.

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $this->response->where('batch_no', $batchNo)->update([
                'is_completed' => true,
                'end_at'       => now(),
            ]);

            app(SubmissionWorkflow::class)->finalize($batchNo);
        });

        return $this->generateSummaryReportByBatchNo($batchNo);
    }

    private function findOwnedBatch(int $batchNo): Response
    {
        $first = $this->response->where('batch_no', $batchNo)->orderBy('id')->first();
        abort_if(! $first, 404, 'Batch not found.');
        abort_unless((int) $first->user_id === (int) auth()->id(), 403, 'Not your checklist.');

        return $first;
    }

    private function assertDraft(Response $response): void
    {
        abort_if($response->is_completed, 422, 'This checklist has already been submitted.');
    }

    public function uploadSignature(UploadedFile $file): array
    {
        return $this->uploadToImageKit($file);   // ['url', 'file_id']
    }

    public function discardSignature(array $uploaded): void
    {
        $this->discardUploads([[$uploaded]]);    // discardUploads expects a list of lists
    }
}
