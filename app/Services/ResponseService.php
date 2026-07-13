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
        return match ($section) {
            'pests', 'birds' => $this->formatPestAndBirdsResponses($responses, $section),
            default => $this->formatCobsResponses($responses),
        };
    }
    public function formatCobsResponses($responses) {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $batchesByUnit = $batches->groupBy('unit_id');

        return Unit::query()->with([
            'checkLists' => fn ($query) => $query->withoutTrashed()  // ← Add this
        ])->get()->mapWithKeys(function ($unit) use ($batchesByUnit) {
            return $this->formatUnitResponse($unit, $batchesByUnit);
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
//        return $checklists->mapWithKeys(function ($checklist) use ($batches, $section, $requiredCount) {
//            $checklistBatches = $batches->where('checklist_id', $checklist->id);
//
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
//                // Extract Inspection Areas from the checklist's items JSON column
//                $inspectionAreas = collect($checklist->items)
//                    ->firstWhere('name', 'Inspection Areas')['items'] ?? [];
//            } else {
//                $periods = [
//                    'Period 1' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day <= 15)->values(),
//                    'Period 2' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day > 15)->values(),
//                ];
//            }
//
//            $userId = $checklistBatches->first()['user_id'] ?? null;
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


    public function formatPestAndBirdsResponses($responses, $section) {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $checklists = Section::query()
            ->with(['checkLists'])
            ->where('name', $section)
            ->first()
            ?->checkLists ?? collect();

        $requiredCount = $section === 'birds' ? 4 : 2;

        // Resolve once, from the full response set — not from a checklist's
        // current-period batches, which may legitimately be empty while last
        // month's data still exists and should be checked.
        $userId = data_get($responses->first(), 'user_id');

        return $checklists->mapWithKeys(function ($checklist) use ($batches, $section, $requiredCount, $userId) {
            $checklistBatches = $batches->where('checklist_id', $checklist->id);
            if ($section === 'birds') {
                $periods = ['Period 1' => [], 'Period 2' => [], 'Period 3' => [], 'Period 4' => []];
                foreach ($checklistBatches as $batch) {
                    $day = Carbon::parse($batch['start_at'])->day;
                    $periods[match (true) {
                        $day <= 7  => 'Period 1',
                        $day <= 14 => 'Period 2',
                        $day <= 21 => 'Period 3',
                        default    => 'Period 4',
                    }][] = $batch;
                }
                $periods = array_map(fn($p) => collect($p)->values(), $periods);

                $inspectionAreas = collect($checklist->items)
                    ->firstWhere('name', 'Inspection Areas')['items'] ?? [];
            } else {
                $periods = [
                    'Period 1' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day <= 15)->values(),
                    'Period 2' => $checklistBatches->filter(fn($b) => Carbon::parse($b['start_at'])->day > 15)->values(),
                ];
            }


            $previousMonthCompleted = $this->checkPreviousMonthCompleted($userId, $checklist->id, $requiredCount);

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

    private function formatUnitResponse($unit, $batchesByUnit) {
        $unitBatches = $batchesByUnit->get($unit->id, collect());
        $batchesByWeek = $unitBatches->groupBy('week');
        $checklists = $unit->checkLists;

        // Get first batch to extract user and checklist info
        $firstBatch = $unitBatches->first();
        $userId = $firstBatch?->user_id ?? null;
        $checklistId = $unit->checkLists ?? null;

        // Check previous month completion for cobs (required: 4 times)
        $previousMonthCompleted = $this->checkPreviousMonthCompleted($userId, $checklistId, 4);

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
                'weeks' => collect(range(1, 4))->mapWithKeys(function ($week) use ($batchesByWeek) {
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

        if ($sectionName === 'cobs' && $this->isThirdWeek($data['start_at'] ?? null)) {
            $fourthWeekData = $data;
            $fourthWeekData['start_at'] = Carbon::parse($data['start_at'])->addWeeks(1);
            $fourthWeekData['end_at'] = $fourthWeekData['start_at'];

            $newBatchNo = $this->generateBatchNo();
            $baseResponseData4thWeek = $this->buildBaseResponseData($fourthWeekData, $newBatchNo, $sectionName);

            DB::transaction(function () use ($data, $baseResponseData4thWeek) {
                $this->processResponseBatch(
                    $data['response'] ?? [],
                    $data['image'] ?? $data['images'] ?? [],
                    'response',
                    $baseResponseData4thWeek,
                    $this->imageKit
                );

                $this->response->where('batch_no', $baseResponseData4thWeek['batch_no'])
                    ->whereMonth('start_at', Carbon::parse($baseResponseData4thWeek['start_at'])->month)
                    ->whereDay('start_at', '>=', 22)
                    ->delete();
            });
        }
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

        // Add this logic for previous month completion

        $requiredCount = match ($section) {
            'cobs', 'birds' => 4,
            'pests' => 2,
            default => 0,
        };

        $previousMonthCompleted = $this->checkPreviousMonthCompleted(
            $firstResponse?->user_id,
            $firstResponse?->checklist_id,
            $requiredCount
        );

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
            'remarks' => $firstResponse?->remarks,
            'notes' => $firstResponse?->notes,
            'temporal_audit' => $firstResponse?->temporal_audit,
            'start_at' => $startAt,
            'end_at' => $firstResponse?->end_at,
            'week' => $startAt ? min(Carbon::parse($startAt)->weekOfMonth, 4) : null,
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
            'previous_month_completed' => $previousMonthCompleted,
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
    private function checkPreviousMonthCompleted($userId, $checklistId, $requiredCount)
    {
        $year = (int) request()->input('year', now()->year);
        $month = (int) request()->input('month', now()->month);

        $previousMonth = Carbon::create($year, $month, 1)->subMonth();

        $count = Response::where('user_id', auth()->id())
            ->where('checklist_id', $checklistId) ->where('is_completed', true)
            ->whereMonth('start_at', $previousMonth->month)
            ->whereYear('start_at', $previousMonth->year)
            ->distinct('batch_no')
            ->count('batch_no');

        return $count >= $requiredCount;
    }
    private function computeHierarchicalScore($firstResponse, $batchResponses) {
        $checklist = $firstResponse?->checklist;
        if (!$checklist) {
            return ['score' => 0, 'breakdown' => []];
        }

        // Convert items to array (handles Collection, array, or JSON string)
        $categories = $this->ensureArray($checklist->items ?? []);

        if (empty($categories)) {
            return ['score' => 0, 'breakdown' => []];
        }

        $totalScore = 0;
        $totalPossibleScore = 0;
        $categoryCount = count($categories);
        $breakdown = [];

        // Iterate through each category (e.g., CLEANLINESS, BIOSECURITY)
        foreach ($categories as $categoryIndex => $category) {
            $categoryName = $category['name'] ?? "Category $categoryIndex";
            $categoryItems = $category['items'] ?? []; // Items in category (e.g., Front Gate, UV Cabinets)
            $itemCount = count($categoryItems);

            if ($itemCount === 0) continue;

            $categoryWeight = 1 / $categoryCount; // Each category gets equal weight
            $categoryScore = 0;
            $categoryPossibleScore = 0;
            $itemsBreakdown = [];

            // Iterate through items in category
            foreach ($categoryItems as $itemIndex => $item) {
                $itemName = $item['name'] ?? "Item $itemIndex";
                $subItems = $item['sub_items'] ?? []; // Sub-items (actual questions)
                $subItemCount = count($subItems);

                if ($subItemCount === 0) continue;

                $itemWeight = 1 / $itemCount; // Equal weight per item in category
                $itemScore = 0;
                $itemPossibleScore = 0;
                $subItemsBreakdown = [];

                // Iterate through sub-items
                foreach ($subItems as $subItemIndex => $subItem) {
                    $subItemName = $subItem['name'] ?? "Sub-item $subItemIndex";
                    $subItemWeight = 1 / $subItemCount; // Equal weight per sub-item

                    // Find response for this specific path: category → item → sub-item
                    $responseValue = $this->findResponseValue(
                        $batchResponses,
                        $categoryIndex,
                        $itemIndex,
                        $subItemIndex
                    );

                    // Normalize to 0-1 scale (response values: 0, 25, 50, 75, 100)
                    $normalizedValue = is_numeric($responseValue) ? ($responseValue / 100) : 0;

                    // Score contribution: category_weight × item_weight × sub_item_weight × response_value
                    $subItemScore = $categoryWeight * $itemWeight * $subItemWeight * $normalizedValue;
                    $subItemPossibleScore = $categoryWeight * $itemWeight * $subItemWeight;

                    $itemScore += $subItemScore;
                    $itemPossibleScore += $subItemPossibleScore;
                    $totalScore += $subItemScore;
                    $totalPossibleScore += $subItemPossibleScore;

                    // Calculate sub-item percentage contribution
                    // Base allocation: (1 / subItemCount) * 100
                    // Actual contribution: base allocation * normalized response value
                    $subItemBasePercentage = ($subItemWeight * 100);
                    $subItemContributionPercentage = $subItemBasePercentage * $normalizedValue;

                    $subItemsBreakdown[] = [
                        'name' => $subItemName,
                        'score' => (int) $responseValue,
                        'allocation' => $subItemBasePercentage,
                        'percentage' => round($subItemContributionPercentage, 2),
                    ];
                }

                // Calculate item percentage
                $itemPercentage = $itemPossibleScore > 0 ? round(($itemScore / $itemPossibleScore) * 100, 2) : 0;
                $itemBaseAllocation = ($itemWeight * 100);
                $categoryScore += $itemScore;
                $categoryPossibleScore += $itemPossibleScore;

                $itemsBreakdown[] = [
                    'name' => $itemName,
                    'score' => $itemPercentage,
                    'allocation' => $itemBaseAllocation,
                    'percentage' => $itemPercentage,
                    'sub_items' => $subItemsBreakdown,
                ];
            }

            // Calculate category percentage
            $categoryPercentage = $categoryPossibleScore > 0 ? round(($categoryScore / $categoryPossibleScore) * 100, 2) : 0;

            $breakdown[] = [
                'category' => $categoryName,
                'score' => (round($categoryWeight * 100, 2) / 100) * $categoryPercentage,
                'percentage' => $categoryPercentage,
                'allocation' => round($categoryWeight * 100, 2),
                'items' => $itemsBreakdown,
            ];
        }

        // Return total score and breakdown
        $totalScore = $totalPossibleScore > 0 ? round(($totalScore / $totalPossibleScore) * 100, 2) : 0;

        return [
            'score' => $totalScore,
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
    public function mergeResponse($data) {
        $month = $data['month'] ?? null;
        $year = $data['year'] ?? null;
//        $startAt = $data['start_at'] ?? Carbon::now();
//        $endAt = $data['end_at'] ?? $startAt;

        $this->response
            ->onlyTrashed() // Only soft-deleted records
            ->whereYear('start_at', $year)
            ->whereMonth('start_at', $month)
            ->update([
//                'start_at' => $startAt,
//                'end_at' => $endAt,
                'deleted_at' => null, // Restore by clearing deleted_at
            ]);
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
