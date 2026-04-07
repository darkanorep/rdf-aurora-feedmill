<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScoreRequest;
use App\Http\Resources\ScoreResource;
use App\Services\ScoreService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ScoreController extends Controller
{
    use ApiResponse;

    protected $scoreService;
    public function __construct(ScoreService $scoreService) {
        return $this->scoreService = $scoreService;
    }

    public function index(Request $request) {
        $scores = $this->scoreService->getScores($request);

        $scores instanceof LengthAwarePaginator
            ? $scores->setCollection($scores->getCollection()->transform(function ($item) {
            return new ScoreResource($item);
        }))
            : $scores = ScoreResource::collection($scores);

        return $scores->isEmpty()
            ? $this->responseNotFound('No Score found.')
            : $this->responseSuccess('Score fetched successfully.', $scores);
    }

    public function store(ScoreRequest $request) {
        $data = $request->validated();
        $score = $this->scoreService->createScore($data);

        return $this->responseCreated("Created Successfully", new ScoreResource($score));
    }

    public function show($id) {
        $score = $this->scoreService->getScoreById($id);

        return $score
            ? $this->responseSuccess('Score fetched successfully.', new ScoreResource($score))
            : $this->responseNotFound('Score not found.');
    }

    public function update(ScoreRequest $request, $id) {
        $score = $this->scoreService->getScoreById($id);

        if (!$score) {
            return $this->responseNotFound('Score not found.');
        }

        $data = $request->validated();
        $updatedScore = $this->scoreService->updateScore($score, $data);

        return $this->responseSuccess('Updated Successfully', new ScoreResource($updatedScore));
    }

    public function destroy($id) {
        $score = $this->scoreService->deleteScore($id);

        return $score
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Score not found.');
    }
}
