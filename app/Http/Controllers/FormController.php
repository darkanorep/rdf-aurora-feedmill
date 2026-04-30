<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormmRequest;
use App\Services\FormService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class FormController extends Controller
{
    use ApiResponse;
    protected $formService;

    public function __construct(FormService $formService)
    {
        $this->formService = $formService;
    }

    public function index(Request $request) {
         $forms = $this->formService->getForms($request);

        return $forms->isEmpty()
            ? $this->responseNotFound('No Forms found.')
            : $this->responseSuccess("Forms retrieved successfully.", $forms);
    }

    public function store(FormmRequest $request) {
        $data = $request->validated();
        $this->formService->createForm($data);

        return $this->responseCreated("Form created successfully.");
    }

    public function getFormByChecklistId(Request $request) {
        $forms = $this->formService->getFormByChecklistId($request->checklist_id);

        if ($forms['forms']->isEmpty()) {
            return $this->responseNotFound("No forms found for the given checklist ID.");
        }

        return $this->responseSuccess("Forms retrieved successfully.", $forms);
    }

    public function updateByChecklistId(FormmRequest $request) {

        $data = $request->validated();
        $this->formService->updateByChecklistId($data, $request->checklist_id);

        return $this->responseSuccess("Forms updated successfully.");
    }

    public function deleteByChecklistId(Request $request) {

        $this->formService->deleteByChecklistId($request->checklist_id);

        return $this->responseSuccess("Form deleted successfully.");
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        $imageKit = new ImageKit(
            config('app.imagekit_public_key'),
            config('app.imagekit_private_key'),
            config('app.imagekit_url_endpoint')
        );

        $uploadedFiles = [];
        $failedFiles = [];

        foreach ($validated['images'] as $file) {
            $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

            try {
                $uploadFile = $imageKit->uploadFile([
                    'file' => fopen($file->getRealPath(), 'r'),
                    'fileName' => $fileName
                ]);

                // Convert Response object to array
                $responseData = json_decode(json_encode($uploadFile), true);

                $uploadedFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'fileName' => $fileName,
                    'url' => $responseData['result']['url'] ?? null,
                    'fileId' => $responseData['result']['fileId'] ?? null,
                ];
            } catch (\Throwable $e) {
                $failedFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (empty($uploadedFiles) && !empty($failedFiles)) {
            return response()->json([
                'message' => 'All images failed to upload.',
                'failed' => $failedFiles,
            ], 400);
        }

        return response()->json([
            'message' => 'Images uploaded ' . (empty($failedFiles) ? 'successfully.' : 'with some failures.'),
            'uploaded' => $uploadedFiles,
            'failed' => $failedFiles,
        ]);
    }

}
