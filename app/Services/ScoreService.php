<?php

namespace App\Services;

use App\Models\Score;

class ScoreService
{
    protected $score;
    public function __construct(Score $score)
    {
        $this->score = $score;
    }

    public function getScores() {
        return Score::useFilters()->dynamicPaginate();
    }

    public function createScore(array $data): Score {
        return Score::create($data);
    }

    public function getScoreById($id)
    {
        return Score::find($id);
    }

    public function updateScore(Score $score, array $data): Score {
        $score->update($data);
        return $score;
    }

    public function deleteScore($id) {
        $score = Score::withTrashed()->find($id);

        if (!$score) {
            return null;
        }

        if ($score->trashed()) {
            $score->restore();
        } else {
            $score->delete();
        }

        return $score;
    }
}
