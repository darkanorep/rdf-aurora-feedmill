<?php

namespace App\Services;

use App\Models\AcknowledgementSetting;
use App\Models\ApprovalStepType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalFlowSync
{
    public function syncAll(): void
    {
        AcknowledgementSetting::withTrashed()->each(fn ($s) => $this->sync($s));
    }

    public function sync(AcknowledgementSetting $setting): void
    {
        if (! $setting->section_id) {
            return;
        }

        $types = ApprovalStepType::pluck('id', 'key');
        foreach (['evaluate', 'approve'] as $key) {
            if (! $types->has($key)) {
                throw new RuntimeException("Step type '{$key}' is missing. Run the ApprovalStepTypeSeeder.");
            }
        }

        DB::transaction(function () use ($setting, $types) {
            $now = now();

            $flowId = DB::table('approval_flows')->where('legacy_setting_id', $setting->id)->value('id')
                ?? DB::table('approval_flows')->insertGetId([
                    'section_id' => $setting->section_id, 'unit_id' => null, 'name' => $setting->name,
                    'legacy_setting_id' => $setting->id, 'created_at' => $now, 'updated_at' => $now,
                ]);

            // A deleted setting retires its flow. Submissions keep their own snapshot.
            if ($setting->deleted_at) {
                DB::table('approval_flows')->where('id', $flowId)->update(['deleted_at' => $now]);
                return;
            }

            DB::table('approval_flows')->where('id', $flowId)->update([
                'section_id' => $setting->section_id, 'name' => $setting->name,
                'deleted_at' => null, 'updated_at' => $now,
            ]);

            // order => [type key, user ids, mode, optional]
            $desired = [];

            if ($evaluators = $this->ids($setting->evaluator_ids)) {
                $desired[0] = ['evaluate', $evaluators, 'any', true];
            }

            $order = 0;
            foreach ((array) $setting->hierarchy as $entry) {
                $userIds = $this->ids(is_array($entry) ? $entry : [$entry]);
                if (! $userIds) {
                    continue;
                }
                // A group (several people) is one level that everyone must sign.
                $desired[++$order] = ['approve', $userIds, count($userIds) > 1 ? 'all' : 'any', false];
            }

            DB::table('approval_flow_steps')->where('approval_flow_id', $flowId)
                ->whereNotIn('step_order', array_keys($desired) ?: [-1])->delete();   // assignees cascade

            foreach ($desired as $stepOrder => [$typeKey, $userIds, $mode, $optional]) {
                DB::table('approval_flow_steps')->updateOrInsert(
                    ['approval_flow_id' => $flowId, 'step_order' => $stepOrder],
                    ['step_type_id' => $types[$typeKey], 'phase' => 'post_submit', 'mode' => $mode, 'is_optional' => $optional]
                );
                $stepId = DB::table('approval_flow_steps')
                    ->where('approval_flow_id', $flowId)->where('step_order', $stepOrder)->value('id');

                DB::table('approval_flow_step_assignees')->where('approval_flow_step_id', $stepId)
                    ->whereNotIn('user_id', $userIds)->delete();
                foreach ($userIds as $userId) {
                    DB::table('approval_flow_step_assignees')->updateOrInsert(
                        ['approval_flow_step_id' => $stepId, 'user_id' => $userId], []
                    );
                }
            }
        });
    }

    /** @return int[] */
    private function ids(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $value))));
    }
}
