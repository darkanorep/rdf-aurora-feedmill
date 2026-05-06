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

        $batches = $responses->groupBy('batch_no')->map(function ($batchResponses, $batchNo) {
            $firstResponse = $batchResponses->first();
            $startAt = $firstResponse?->start_at;

            return [
                'batch_no' => (int) $batchNo,
                'section_id' => $firstResponse?->section_id,
                'section' => $firstResponse?->section?->name,
                'unit_id' => $firstResponse?->unit_id,
                'unit' => $firstResponse?->unit?->name,
                'user_id' => $firstResponse?->user_id,
                'user' => $firstResponse?->user?->getFullNameAttribute(),
                'approver_id' => $firstResponse?->approver_id,
                'approver' => $firstResponse?->approver?->getFullNameAttribute(),
                'is_completed' => $firstResponse?->is_completed,
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
            ];
        })->values();

        $batchesByUnit = $batches->groupBy('unit_id');

        return Unit::query()->get()->mapWithKeys(function ($unit) use ($batchesByUnit) {
            $unitBatches = $batchesByUnit->get($unit->id, collect());
            $batchesByWeek = $unitBatches->groupBy('week');

            return [
                'Unit: ' . $unit->name => collect(range(1, 4))->mapWithKeys(function ($week) use ($batchesByWeek) {
                    return [
                        'Week ' . $week => $batchesByWeek->get($week, collect())->values()->all(),
                    ];
                })->all(),
            ];
        });
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
                'section_id' => $data['section_id'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'approver_id' => $data['approver_id'] ?? null,
                'batch_no' => $batchNo,
                'response' => $responseData,
                'start_at' => Carbon::now(),
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
}
