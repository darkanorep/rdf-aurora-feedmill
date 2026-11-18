<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BirdsReportService
{
    public const ANSWER_KEYS = [
        'lot'          => ['inspection_area', 'lot', 'lot_number'],
        'inspected_by' => ['inspected_by'],
        'infestation'  => ['infestation_level', 'level'],
        'treatment'    => ['treatment', 'action', 'treatment_action'],
        'wastage'      => ['wastage', 'feed_wastage', 'presence_of_wastage'],
        'entry_points' => ['entry_points', 'entry_point', 'remarks'],
    ];

    public const HEADINGS = [
        'Date monitor', 'Month', 'Year', 'MoYr', 'Unit', 'Time Started', 'Time Finished',
        'Inspected by', 'Lot', 'Infestation Level', 'Treatment/Action Done',
        'Presence of Feed/RM wastage',
        'Identify entry points/Saan sila pumapasok o dumadaan',
    ];

    /** One row per answered lot of every FINISHED birds checklist whose inspection date is in the month. */
    public function rows(int $month, int $year): Collection
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = (clone $from)->addMonth();

        $responses = Response::query()
            ->join('checklists', 'checklists.id', '=', 'responses.checklist_id')
            ->join('sections', 'sections.id', '=', 'checklists.section_id')
            ->whereRaw('LOWER(sections.name) = ?', ['birds'])
            ->where('responses.start_at', '>=', $from)
            ->where('responses.start_at', '<', $to)
            ->with(['unit', 'user'])
            ->select('responses.*')
            ->orderBy('responses.start_at')
            ->orderBy('responses.batch_no')
            ->orderBy('responses.id')
            ->get();

        // Finished = the approval chain is complete. Drafts, in review and rejected are left out.
        $finished = Submission::with('steps')
            ->whereIn('legacy_batch_no', $responses->pluck('batch_no')->unique())
            ->get()
            ->filter(fn ($s) => $s->status() === 'Done')
            ->pluck('legacy_batch_no')
            ->flip();

        return $responses
            ->filter(fn ($r) => $finished->has($r->batch_no))
            ->map(fn ($r) => $this->row($r))
            ->values();
    }

    private function row(Response $r): array
    {
        $answer = (array) $r->response;

        $pick = function (string $column) use ($answer) {
            foreach (self::ANSWER_KEYS[$column] as $key) {
                $value = $answer[$key] ?? null;

                if ($value !== null && $value !== '') {
                    return is_scalar($value) ? (string) $value : json_encode($value);
                }
            }

            return null;
        };

        $date = Carbon::parse($r->start_at);
        $wastage = $pick('wastage');

        if (is_string($wastage)) {
            $decoded = json_decode($wastage, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $wastage = $decoded;
            }
        }

        if ($wastage instanceof Collection) {
            $wastage = $wastage->all();
        }

        if (is_array($wastage)) {
            $items = array_filter(
                array_map(
                    fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                    $wastage
                ),
                fn ($item) => $item !== ''
            );

            $wastage = $items ? implode(', ', $items) : 'N/A';
        } elseif ($wastage === null || trim((string) $wastage) === '') {
            $wastage = 'N/A';
        }

        return [
            'date'          => $date->format('m/d/Y'),
            'month'         => $date->format('M'),
            'year'          => $date->format('Y'),
            'moyr'          => $date->format('M') . $date->format('y'),
            'unit'          => $r->unit?->name,
            'time_started'  => $date->format('h:i A'),
            'time_finished' => $r->end_at
                ? Carbon::parse($r->end_at)->format('h:i A')
                : null,
            'inspected_by'  => $pick('inspected_by') ?? $r->user?->getFullNameAttribute(),
            'lot'           => $pick('lot'),
            'infestation'   => $pick('infestation'),
            'treatment'     => $pick('treatment'),
            'wastage'       => $wastage,
            'entry_points'  => $pick('entry_points'),
        ];
    }
}
