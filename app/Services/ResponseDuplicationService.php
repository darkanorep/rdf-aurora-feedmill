<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResponseDuplicationService
{
    /**
     * Duplicate a response by its batch number.
     *
     * @param int $batchNo
     * @param string $reason
     * @return Response
     */
    public function duplicate(int $batchNo, string $reason): Response
    {
        // Defense in depth: re-check business rules here even though the
        // Policy already gated the controller action. Services shouldn't
        // trust that every future caller will go through the HTTP layer
        // (e.g. a console command, a queued job, another service).
        $response = Response::where('batch_no', $batchNo)->first();

        if (! $response) {
            throw new RuntimeException('Response not found.');
        }

        if (! $response->isCompleted()) {
            throw new RuntimeException('Cannot duplicate a response that has not been completed.');
        }

        if ($response->hasDuplicate()) {
            throw new RuntimeException('This response has already been duplicated.');
        }

        return DB::transaction(function () use ($response, $reason) {
            // Eager-load images before replicate() to avoid an N+1 when we
            // iterate them below.
            $response->loadMissing('images');

            $copy = $response->replicate([
                'response',
                'good_points',
                'temporal_audit',
                'start_at',
                'end_at'
            ]);

            $copy->parent_response_id = $response->id;
            $copy->duplicate_reason   = $reason;
            $copy->batch_no           = $this->generateBatchNo();
            $copy->is_completed       = true; // the new week starts fresh, not inherited from its parent
            $copy->start_at           = Carbon::parse($response->start_at)->addDays(7);
            $copy->end_at             = $response->end_at
                ? Carbon::parse($response->start_at)->addDays(7)
                : null;

            $copy->save();

            $imagesToInsert = $response->images->map(fn (Image $image) => [
                ...$image->replicate()->toArray(),
                'response_id' => $copy->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->all();

            if (! empty($imagesToInsert)) {
                Image::insert($imagesToInsert);
            }

            return $copy->fresh('images');
        });
    }

    private function generateBatchNo()
    {
        return DB::table('responses')->max('batch_no') + 1;
    }
}
