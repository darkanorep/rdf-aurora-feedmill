<?php

namespace App\Services;

use App\Models\ApprovalFlow;
use App\Models\Checklist;
use Illuminate\Validation\ValidationException;

final class ApprovalFlowResolver
{
    public function resolve(Checklist $checklist): ApprovalFlow
    {
        $flows = ApprovalFlow::with('steps.type', 'steps.assignees')
            ->where('section_id', $checklist->section_id)->get();

        if ($flows->count() === 1) {
            return $flows->first();
        }

        throw ValidationException::withMessages(['approval' => [
            $flows->isEmpty()
                ? 'No approval flow is configured for this checklist. Contact the administrator.'
                : 'More than one approval flow matches this checklist. Fix the configuration.',
        ]]);
    }
}
