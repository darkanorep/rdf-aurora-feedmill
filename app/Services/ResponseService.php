<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Image;
use App\Models\Response;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ImageKit\ImageKit;

#[AllowDynamicProperties]
class ResponseService
{
    /**
     * Create a new class instance.
     */

    private Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }
    public function getResponses($request) {
        $responses = $this->response->useFilters()->get();
        return $this->formatResponses($responses);
    }
    public function formatResponses($responses) {
        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            return $this->formatBatchResponse($batchResponses, $batchNo);
        })->values();

        $batchesByUnit = $batches->groupBy('unit_id');

        return Unit::query()->with(['checkLists'])->get()->mapWithKeys(function ($unit) use ($batchesByUnit) {
            return $this->formatUnitResponse($unit, $batchesByUnit);
        });
    }
    private function formatUnitResponse($unit, $batchesByUnit) {
        $unitBatches = $batchesByUnit->get($unit->id, collect());
        $batchesByWeek = $unitBatches->groupBy('week');
        $checklists = $unit->checkLists;

        return [
            'Unit: ' . $unit->name => [
                'unit_id' => $unit->id,
                'checklists' => $checklists->map(function ($checklist) {
                    return [
                        'id' => $checklist->id,
                        'checklist_name' => $checklist->checklist_name,
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
    public function storeResponse(array $data)
    {
        $batchNo = $this->generateBatchNo();
        $responsesData = $data['response'] ?? [];
        $imagesData = $data['image'] ?? $data['images'] ?? [];

        $imageKit = new ImageKit(
            config('app.imagekit_public_key'),
            config('app.imagekit_private_key'),
            config('app.imagekit_url_endpoint')
        );

        foreach ($responsesData as $key => $value) {
            $responseData = is_string($value) ? json_decode($value, true) : $value;

            if (is_string($value) && json_last_error() !== JSON_ERROR_NONE) {
                $responseData = ['value' => $value];
            }

            if (!is_array($responseData)) {
                $responseData = ['value' => $responseData];
            }

            $response = $this->response->create([
                'checklist_id' => $data['checklist_id'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'approver_id' => $data['approver_id'] ?? null,
                'batch_no' => $batchNo,
                'response' => $responseData,
                'good_points' => $data['good_points'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'temporal_audit' => $data['temporal_audit'] ?? null,
                'start_at' => $data['start_at'] ?? Carbon::now(),
                'is_completed' => $data['is_completed'] ?? null,
                'end_at' => $data['is_completed'] ? Carbon::now() : null,
            ]);

            $images = $imagesData[$key] ?? [];
            if (!is_array($images)) {
                $images = [$images];
            }

            foreach ($images as $image) {
                if (!$image || !method_exists($image, 'getRealPath')) {
                    continue;
                }

                $fileName = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                $uploadFile = $imageKit->uploadFile([
                    'file' => fopen($image->getRealPath(), 'r'),
                    'fileName' => $fileName,
                ]);

                Image::create([
                    'response_id' => $response->id,
                    'url' => data_get($uploadFile, 'result.url'),
                ]);
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
        $firstResponse = $batchResponses->first();
        $startAt = $firstResponse?->start_at;
        $countSubItems = $firstResponse?->checklist?->countSubItems();
        $countResponses = $batchResponses->count();
        $progress = $countSubItems > 0 ? ($countResponses / $countSubItems) * 100 : 0;

        // Compute hierarchical score (now returns array with breakdown)
        $scoreData = $this->computeHierarchicalScore($firstResponse, $batchResponses);

        return [
            'batch_no' => (int) $batchNo,
            'progress' => (int) $progress . '%' ,
            'score' => (int) $scoreData['score'],
            'score_breakdown' => $scoreData['breakdown'],
            'checklist_id' => $firstResponse?->checklist_id,
            'checklist_name' => $firstResponse?->checklist?->checklist_name,
            'unit_id' => $firstResponse?->unit_id,
            'unit' => $firstResponse?->unit?->name,
            'user_id' => $firstResponse?->user_id,
            'user' => $firstResponse?->user?->getFullNameAttribute(),
            'approver_id' => $firstResponse?->approver_id,
            'approver' => $firstResponse?->approver?->getFullNameAttribute(),
            'is_completed' => $firstResponse?->is_completed,
            'is_approved' => $firstResponse?->is_approved,
            'good_points' => $firstResponse?->good_points,
            'remarks' => $firstResponse?->remarks,
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
            'status' => $firstResponse?->is_approved ? 'Approved' : ($firstResponse?->is_completed && $progress == 100 ? 'For Acknowledgement' : 'On Progress')
        ];
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
}
