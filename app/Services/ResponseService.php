<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Image;
use App\Models\Response;
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
                'user_id' => $data['user_id'] ?? null,
                'approver_id' => $data['approver_id'] ?? null,
                'batch_no' => $batchNo,
                'response' => $responseData,
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
