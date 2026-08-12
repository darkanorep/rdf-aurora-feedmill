<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\AcknowledgementSetting;
use App\Models\Checklist;
use App\Models\Image;
use App\Models\Response;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ImageKit\ImageKit;

#[AllowDynamicProperties]
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
        $this->acknowledgementSetting = AcknowledgementSetting::get();
    }
    public function getResponses($request) {
        $responses = $this->response->useFilters()->get();
        $section = $request->section;

        $month = (int) ($request->month ?? Carbon::now()->month);
        $year  = (int) ($request->year ?? Carbon::now()->year);

        return match ($section) {
            'pests', 'birds' => $this->formatPestAndBirdsResponses($responses, $section, $month, $year),
            default => $this->formatCobsResponses($responses, $month, $year),
        };
    }
    public function formatCobsResponses($responses, int $month, int $year) {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $batchesByUnit = $batches->groupBy('unit_id');

        return Unit::query()->with([
            'checkLists' => fn ($query) => $query->withoutTrashed()
        ])->get()->mapWithKeys(function ($unit) use ($batchesByUnit, $month, $year) {
            return $this->formatUnitResponse($unit, $batchesByUnit, $month, $year);
        });
    }
//    public function formatPestAndBirdsResponses($responses, $section) {
//        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
//            return $this->formatBatchResponse($batchResponses, $batchNo);
//        })->values();
//
//        $checklists = Section::query()
//            ->with(['checkLists'])
//            ->where('name', $section)
//            ->first()
//            ?->checkLists ?? collect();
//
//        $requiredCount = $section === 'birds' ? 4 : 2;
//
//        // Resolve once, from the full response set — not from a checklist's
//        // current-period batches, which may legitimately be empty while last
//        // month's data still exists and should be checked.
//        $userId = data_get($responses->first(), 'user_id') ?? auth()->id();
//
//        return $checklists->mapWithKeys(function ($checklist) use ($batches, $section, $requiredCount, $userId) {
//            $checklistBatches = $batches->where('checklist_id', $checklist->id);
//            if ($section === 'birds') {
//                $periods = ['Period 1' => [], 'Period 2' => [], 'Period 3' => [], 'Period 4' => []];
//                foreach ($checklistBatches as $batch) {
//                    $day = Carbon::parse($batch['start_at'])->day;
//                    $periods[match (true) {
//                        $day <= 7  => 'Period 1',
//                        $day <= 14 => 'Period 2',
//                        $day <= 21 => 'Period 3',
//                        default    => 'Period 4',
//                    }][] = $batch;
//                }
//                $periods = array_map(fn($p) => collect($p)->values(), $periods);
//
//                $inspectionAreas = collect($checklist->items)
//                    ->firstWhere('name', 'Inspection Areas')['items'] ?? [];
//            } else {
//                $periods = [
//                    'Period 1' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day <= 15)->values(),
//                    'Period 2' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day > 15)->values(),
//                ];
//            }
//
//
//            $previousMonthCompleted = $userId
//                ? $this->checkPreviousMonthCompleted($userId, $checklist->id, $requiredCount)
//                : null;
//
//            return [
//                $checklist->checklist_name => [
//                    'id'                       => $checklist->id,
//                    'checklist_name'           => $checklist->checklist_name,
//                    'created_at'               => Carbon::parse($checklist->created_at)->format('Y-m-d'),
//                    'previous_month_completed' => $previousMonthCompleted,
//                    'periods'                  => $periods,
//                    ...($section === 'birds' ? ['inspection_areas' => $inspectionAreas] : []),
//                ],
//            ];
//        });
//    }

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

        // Only pest has a fixed, meaningful "must submit N times" rule.
        // Birds' period count is now calendar-driven (4 or 5 depending on the
        // month), so a hardcoded required count no longer applies — skip the
        // check entirely for birds rather than testing against a stale number.
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

            // Birds no longer has a required-count rule, so previous_month_completed
            // is only computed for pest. Explicitly null (not false) for birds so
            // the frontend can distinguish "not applicable" from "not completed".
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
    private function formatUnitResponse($unit, $batchesByUnit, int $month, int $year) {
        $unitBatches = $batchesByUnit->get($unit->id, collect());
        $batchesByWeek = $unitBatches->groupBy('week');
        $checklists = $unit->checkLists;

        // Get first batch to extract user and checklist info
        $firstBatch = $unitBatches->first();
        $userId = $firstBatch?->user_id ?? null;

        // Check previous month completion for cobs (required: 4 times)
        $previousMonthCompleted = $this->checkPreviousMonthCompleted($userId, data_get($unit->checkLists->first(), 'id'), 4);

        // Real number of weeks in the given calendar month (e.g. Feb 2026 = 4, most months = 4 or 5)
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
    public function storeResponse(array $data) {
        $batchNo = $this->generateBatchNo();

        if ($data['batch_no']) {
            DB::transaction(function () use ($data) {
                $responseIds = $this->response->where('batch_no', $data['batch_no'])->pluck('id');
                Image::whereIn('response_id', $responseIds)->forceDelete();
                $this->response->where('batch_no', $data['batch_no'])->forceDelete();
            });
        }

        $sectionName = $this->resolveChecklistSectionName($data['checklist_id'] ?? null);
        $baseResponseData = $this->buildBaseResponseData($data, $batchNo, $sectionName);

        $this->processResponseBatch(
            $data['response'] ?? [],
            $data['image'] ?? $data['images'] ?? [],
            'response',
            $baseResponseData,
            $this->imageKit
        );
    }
    private function isThirdWeek($startAt): bool
    {
        if (!$startAt) {
            return false;
        }

        $date = Carbon::parse($startAt);
        $weekOfMonth = (int) ceil($date->day / 7);

        return $weekOfMonth === 3;
    }
    public function processResponseBatch(array $dataItems, array $imageItems, string $fieldName, array $baseData, ImageKit $imageKit)
    {
        foreach ($dataItems as $key => $value) {
            $fieldData = $this->parseData($value);

            $response = $this->response->create(array_merge($baseData, [
                $fieldName => $fieldData,
            ]));

            $this->storeImages($imageItems[$key] ?? [], $response->id, $imageKit);
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
    private function storeImages($images, int $responseId, ImageKit $imageKit): void
    {
        // Ensure $images is always an array
        $imagesToProcess = !is_array($images) ? [$images] : $images;

        foreach ($imagesToProcess as $image) {
            if (!$image || !method_exists($image, 'getRealPath')) {
                continue;
            }

            try {
                $fileName = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                $uploadFile = $imageKit->uploadFile([
                    'file' => fopen($image->getRealPath(), 'r'),
                    'fileName' => $fileName,
                ]);

                $url = data_get($uploadFile, 'result.url');
                if ($url) {
                    Image::create([
                        'response_id' => $responseId,
                        'url' => $url,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('ImageKit upload failed: ' . $e->getMessage());
            }
        }
    }
    private function generateBatchNo()
    {
        return DB::table('responses')->max('batch_no') + 1;
    }
    public function generateSummaryReportByBatchNo($batchNo) {
        $response = $this->response->where('batch_no', $batchNo)->get();

        return $response->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values()->first();
    }
    private function formatBatchResponse($batchResponses, $batchNo) {
        $section = $batchResponses->first()?->section?->name;
        $firstResponse = $batchResponses->first();
        $startAt = $firstResponse?->start_at;
        $countSubItems = $firstResponse?->checklist?->countSubItems();
        $countResponses = $batchResponses->count();
        $progress = $countSubItems > 0 ? ($countResponses / $countSubItems) * 100 : 0;

        $scoreData = $this->computeHierarchicalScore($firstResponse, $batchResponses);
        $signatory2 = $this->formatFieldData($batchResponses, 'approve', 'approve');

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
            'evaluator_id' => $firstResponse?->evaluator_id,
            'evaluator' => $firstResponse?->evaluator?->getFullNameAttribute(),
            'approver_id' => $signatory2 ? $firstResponse?->assessor_id : $firstResponse?->approver_id,
            'approver' => $signatory2 ? $firstResponse?->assessor?->getFullNameAttribute() : $firstResponse?->approver?->getFullNameAttribute(),
            'assessor_id' => $firstResponse?->assessor_id,
            'assessor' => $firstResponse?->assessor?->getFullNameAttribute(),
            'is_completed' => $firstResponse?->is_completed,
            'is_evaluated' => $firstResponse?->is_evaluated,
            'is_approved' => $firstResponse?->is_approved,
            'is_assessed' => $firstResponse?->is_assessed,
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
            'signatory_1' => $this->formatFieldData($batchResponses, 'evaluate', 'evaluate'),
            'signatory_2' => $signatory2,
            'signatory_3' => $this->formatFieldData($batchResponses, 'assess', 'assess'),
            'status' => $firstResponse?->is_approved ? 'Approved' : ($firstResponse?->is_completed && $progress == 100 ? 'For Acknowledgement' : 'On Progress')
        ];
    }
    private function formatFieldData($batchResponses, string $fieldName, string $imageFieldName): ?array
    {
        $batchNo = $batchResponses->first()?->batch_no;
        $record = $this->response->newQuery()->where('batch_no', $batchNo)
            ->whereNotNull($fieldName)
            ->first();

        if (!$record) return null;

        $value = $record->{$fieldName};

        return [
            'name' => $value['name'] ?? null,
            "{$imageFieldName}_image" => $record->images->pluck('url')->first(),
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
            ->distinct()              // <-- no argument: standard "distinct count" idiom
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
        // Weights must be computed from *scorable* counts, so we build a filtered
        // tree first rather than skipping mid-loop (which would silently shrink
        // the denominator without redistributing weight correctly).
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

                    // 0 (or missing/non-numeric) == N/A on the frontend -> exclude
                    // entirely from both scoring and the item/sub-item counts.
                    if (!is_numeric($responseValue) || (float) $responseValue <= 0) {
                        continue;
                    }

                    $filteredSubItems[] = [
                        'name'  => $subItemName,
                        'value' => (float) $responseValue,
                    ];
                }

                // Item had sub-items, but all were N/A -> drop the whole item.
                if (empty($filteredSubItems)) {
                    continue;
                }

                $filteredItems[] = [
                    'name'      => $itemName,
                    'sub_items' => $filteredSubItems,
                ];
            }

            // Category had items, but none survived filtering -> drop it.
            if (empty($filteredItems)) {
                continue;
            }

            $filteredCategories[] = [
                'name'  => $categoryName,
                'items' => $filteredItems,
            ];
        }

        if (empty($filteredCategories)) {
            // Every single item was N/A — nothing to score.
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
    private function findResponseValue($batchResponses, $categoryIndex, $itemIndex, $subItemIndex) {
        // Get category name from checklist
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

        // Search for matching response by checklist name, item name, and sub_item name
        foreach ($batchResponses as $response) {
            $resp = $response->response;

            // Match by category name, item name, and sub_item name
            if (isset($resp['checklist']) && isset($resp['item']) && isset($resp['sub_item'])) {
                if ($resp['checklist'] == $categoryName &&
                    $resp['item'] == $itemName &&
                    $resp['sub_item'] == $subItemName) {
                    // Return score value (default 0 if not found)
                    return $resp['score'] ?? 0;
                }
            }
        }

        return 0;
    }
    private function ensureArray($data) {
        // Already an array
        if (is_array($data)) {
            return $data;
        }

        // Illuminate Collection
        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        // JSON string
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Fallback
        return is_object($data) ? (array) $data : [];
    }
    public function buildBaseResponseData(array $data, ?string $batchNo = null, ?string $sectionName = null): array
    {
        $sectionName ??= $this->resolveChecklistSectionName($data['checklist_id'] ?? null);

        if ($sectionName == 'pests' || $sectionName == 'birds') {
            $acknowledgeSetting = AcknowledgementSetting::with([
                'users' => fn ($query) => $query->select('id'),
                'hierarchies' => fn ($query) => $query->select('id'),
                'sections' => fn ($query) => $query->select('id'),
            ])->join('sections', 'acknowledgement_settings.section_id', '=', 'sections.id')
                ->where('sections.name', $sectionName)
                ->first();
        }

        return [
            'checklist_id' => $data['checklist_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'user_id' => auth()->user()->id ?? $data['user_id'] ?? null,
            'evaluator_id' => $acknowledgeSetting->users->id ?? $data['evaluator_id'] ?? null ,
            'approver_id' => $acknowledgeSetting->hierarchy[0] ?? $data['approver_id'] ?? null ,
            'assessor_id' => $acknowledgeSetting->hierarchy[1] ?? $data['assessor_id'] ?? null,
            'batch_no' => $data['batch_no'] ?? $batchNo,
            'good_points' => $data['good_points'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'notes' => $data['notes'] ?? null,
            'temporal_audit' => $data['temporal_audit'] ?? null,
            'start_at' => $data['start_at'] ?? Carbon::now(),
            'is_completed' => $data['is_completed'] ?? null,
            'is_evaluated' => $sectionName == 'birds' ? 1 : null,
            'end_at' => isset($data['is_completed']) && $data['is_completed'] ? Carbon::now() : null,
        ];
    }
    public function evaluate($data) {

        $evaluator = User::whereId($data['evaluator_id'])->with([
            'acknowledgement' => fn ($item) => $item->select(['user_id', 'hierarchy']),
        ])->first();

        // Policy check
        Gate::authorize('acknowledge', $evaluator);

        $hierarchy = $evaluator->acknowledgement->hierarchy ?? [];

        $approver_id = $hierarchy[0] ?? null;
        $assessor_id = $hierarchy[1] ?? null;

        $baseResponseData = $this->buildBaseResponseData($data, $data['batch_no']);

        // Process evaluations
        $this->processResponseBatch(
            $data['evaluate'] ?? [],
            $data['evaluate_image'] ?? [],
            'evaluate',
            $baseResponseData,
            $this->imageKit
        );

        $this->response->newQuery()->where('batch_no', $data['batch_no'])->update([
            'is_evaluated' => true,
            'evaluator_id' => $evaluator->id,
            'approver_id' => $approver_id,
            'assessor_id' => $assessor_id,
        ]);
    }
    public function assess($data) {
        $baseResponseData = $this->buildBaseResponseData($data, $data['batch_no']);

        $this->processResponseBatch(
            $data['assess'] ?? [],
            $data['assess_image'] ?? [],
            'assess',
            $baseResponseData,
            $this->imageKit
        );
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
    protected function resolveEvaluatorId(?int $checklistId): ?int
    {
        if (! $checklistId) {
            return null;
        }

        $sectionId = Checklist::find($checklistId)?->section_id;

        if (! $sectionId) {
            return null;
        }

        return $this->acknowledgementSetting
            ->firstWhere('section_id', $sectionId)
            ?->user_id;
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
    public function additionalAttachment($images, int $responseId): void
    {
        if (empty($responseId)) {
            \Log::error('additionalAttachment called without a response_id.');
            return;
        }

        $imagesToProcess = !is_array($images) ? [$images] : $images;

        foreach ($imagesToProcess as $image) {
            if (!$image || !method_exists($image, 'getRealPath')) {
                continue;
            }

            try {
                $fileName = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                $uploadFile = $this->imageKit->uploadFile([
                    'file' => fopen($image->getRealPath(), 'r'),
                    'fileName' => $fileName,
                ]);

                $url = data_get($uploadFile, 'result.url');
                if ($url) {
                    Image::create([
                        'response_id' => $responseId,
                        'url' => $url,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('ImageKit upload failed (response_id: ' . $responseId . '): ' . $e->getMessage());
            }
        }
    }

    public function truncateResponse() {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('images')->truncate();
        DB::table('responses')->truncate();
//        DB::table('checklists')->truncate();
//        DB::table('acknowledgement_settings')->truncate();
//        DB::table('infestation_levels')->truncate();
//        DB::table('wastages')->truncate();
//        DB::table('scores')->truncate();
//        DB::table('sections')->truncate();
//        DB::table('pests')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
