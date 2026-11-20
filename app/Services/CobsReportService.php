<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CobsReportService
{
    public const HEADINGS = ['Date', 'Department', 'Unit', 'Category', 'P#', 'Parameter', 'Score', 'Rating', 'Remarks'];

    /**
     * The sheet's Score (0-3) behind each Rating (percent). Seen in the sample: 100 = 3 and 75 = 2.
     * The lower steps are assumed: 50 = 1 and 25 = 0. Change these two numbers if that is wrong.
     */
    public const RATING_FOR_ZERO = 25;
    public const RATING_STEP     = 25;

    /** One row per checklist item of every FINISHED cobs checklist whose inspection date is in the month. */
    public function rows(int $month, int $year, ?int $unitId = null): Collection
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = (clone $from)->addMonth();

        $responses = Response::query()
            ->join('checklists', 'checklists.id', '=', 'responses.checklist_id')
            ->join('sections', 'sections.id', '=', 'checklists.section_id')
            ->whereRaw('LOWER(sections.name) = ?', ['cobs'])
            ->where('responses.start_at', '>=', $from)
            ->where('responses.start_at', '<', $to)
            ->when($unitId, fn ($q) => $q->where('responses.unit_id', $unitId))
            ->with(['checklist', 'unit'])
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
            ->groupBy('batch_no')
            ->filter(fn ($group, $batchNo) => $finished->has($batchNo))
            ->flatMap(fn (Collection $group) => $this->rowsForBatch($group))
            ->values();
    }

    private function rowsForBatch(Collection $group): array
    {
        $first      = $group->first();
        $categories = $this->structure($first->checklist?->items);
        $date       = Carbon::parse($first->start_at);
        $unit       = $first->unit?->name;
        $department = config('reports.departments.' . $unit);

        // The answers, found by category + item + sub-item, the same way the checklist score finds them.
        $answers = [];
        foreach ($group as $response) {
            $a = (array) $response->response;
            if (isset($a['checklist'], $a['item'], $a['sub_item'])) {
                $answers[$this->key($a['checklist'], $a['item'], $a['sub_item'])] = $a;
            }
        }

        $rows = [];
        $n    = 0;

        foreach ($categories as $category) {
            foreach ($category['items'] ?? [] as $item) {
                foreach ($item['sub_items'] ?? [] as $sub) {
                    $n++;
                    $answer = $answers[$this->key($category['name'] ?? '', $item['name'] ?? '', $sub['name'] ?? '')] ?? null;
                    $rating = $answer && isset($answer['score']) && is_numeric($answer['score']) && $answer['score'] > 0
                        ? $answer['score'] + 0
                        : null;                                    // N/A or not answered stays blank

                    $rows[] = [
                        'date'       => $date->format('n/j/Y'),
                        'department' => $department,
                        'unit'       => $unit,
                        'category'   => $this->title($category['name'] ?? ''),
                        'number'     => $n,
                        'parameter'  => $sub['name'] ?? null,
                        'score'      => $rating === null ? null : $this->scale($rating),
                        'rating'     => $rating,
                        'remarks'    => ($answer['remarks'] ?? '') !== '' ? $answer['remarks'] : null,
                    ];
                }
            }
        }

        return $rows;
    }

    private function scale(int|float $rating): int
    {
        return max(0, (int) round(($rating - self::RATING_FOR_ZERO) / self::RATING_STEP));
    }

    private function structure(mixed $items): array
    {
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        return is_array($items) ? $items : [];
    }

    private function key(string $category, string $item, string $sub): string
    {
        return mb_strtolower(trim($category)) . '|' . mb_strtolower(trim($item)) . '|' . mb_strtolower(trim($sub));
    }

    /** CLEANLINESS => Cleanliness, BIOSECURITY and SAFETY => Biosecurity and Safety */
    private function title(string $name): string
    {
        $name = mb_convert_case(mb_strtolower(trim($name)), MB_CASE_TITLE);

        return preg_replace_callback('/\b(And|Of|The|For|To)\b/u', fn ($m) => mb_strtolower($m[1]), $name);
    }
}
