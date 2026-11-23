<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDraftRequest;
use App\Http\Requests\StoreResponseImagesRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Models\Response;
use App\Models\Image;

class ResponseDraftController extends Controller
{
    public function __construct(private readonly ResponseService $responseService) {}

    public function store(StoreDraftRequest $request)
    {
        return response()->json([
            'message' => 'Draft created.',
            'data'    => $this->responseService->createDraft($request->validated()),
        ], 201);
    }

    public function update(UpdateDraftRequest $request, int $batchNo)
    {
        return response()->json([
            'message' => 'Draft saved.',
            'data'    => $this->responseService->updateDraft($batchNo, $request->validated()),
        ]);
    }

    public function storeImages(StoreResponseImagesRequest $request, Response $response)
    {
        $this->responseService->assertCanModifyAttachments($response);

        $images = $this->responseService->additionalAttachment($request->file('image'), $response->id);

        return response()->json([
            'message' => 'Images uploaded.',
            'data'    => ['images' => $images],
        ], 201, [], JSON_UNESCAPED_SLASHES);
    }

    public function destroyImage(Image $image)
    {
        $this->responseService->deleteImage($image);

        return response()->json(['message' => 'Image removed.']);
    }

    public function submit(int $batchNo)
    {
        return response()->json([
            'message' => 'Checklist submitted.',
            'data'    => $this->responseService->submit($batchNo),
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

}
