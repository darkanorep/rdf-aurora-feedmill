<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PestsReportService
{
    /** Fixed columns. The pest columns come from the master list, so they are not listed here. */
    public const ANSWER_KEYS = [
        'inspected_by' => ['inspected_by', 'inspector'],
        'area'         => ['inspection_areas', 'inspection_area', 'area', 'lot', 'sub_item', 'item'],
        'services'     => ['inspection_services', 'inspection_service', 'services', 'service'],
        'cleanliness'  => ['other_observations.Cleanliness/Sanitation'],
        'structural'   => ['other_observations.Structural'],
        'ventilation'  => ['other_observations.Proper Ventilation'],
    ];

    /** Sheet headings that differ from the master-list name. Key = pest name, lower case, letters and digits only. */
    public function labels(): array
    {
        return $this->pests()
            ->mapWithKeys(fn ($p) => [
                $this->key($p->name) => [$p->name, $p->name . ' IL'],
            ])
            ->all();
    }

    /** Active pests of the master list, in their list order. */
    public function pests(): Collection
    {
        return DB::table('pests')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    public function headings(): array
    {
        $labels = $this->pests()->map(
            fn ($p) => [$p->name, $p->name . ' IL']
        );

        return array_merge(
            ['Date', 'Month', 'Year', 'Inspected by:', 'Inspection Areas'],
            $labels->map(fn ($label) => $label[0])->all(),
            $labels->map(fn ($label) => $label[1])->all(),
            ['Cleanliness/Sanitation', 'Structural', 'Proper Ventilation'],
            ['Inspection services'],
        );
    }

    /**
     * One row per answered area of every FINISHED pests checklist whose inspection date is in the month.
     * $period: null = whole month, 1 = day 1-15, 2 = day 16 onward.
     */
    public function rows(int $month, int $year, ?int $period = null): Collection
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = (clone $from)->addMonth();

        $responses = Response::query()
            ->join('checklists', 'checklists.id', '=', 'responses.checklist_id')
            ->join('sections', 'sections.id', '=', 'checklists.section_id')
            ->whereRaw('LOWER(sections.name) = ?', ['pests'])
            ->where('responses.start_at', '>=', $from)
            ->where('responses.start_at', '<', $to)
            ->when($period === 1, fn ($q) => $q->whereRaw('DAY(responses.start_at) <= 15'))
            ->when($period === 2, fn ($q) => $q->whereRaw('DAY(responses.start_at) > 15'))
            ->with(['user'])
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

        $pests = $this->pests();

        return $responses
            ->filter(fn ($r) => $finished->has($r->batch_no))
            ->map(fn ($r) => $this->row($r, $pests))
            ->values();
    }

    private function row(Response $r, Collection $pests): array
    {
        $answer = (array) $r->response;
        $flat   = $this->normalise($answer);
        $date   = Carbon::parse($r->start_at);

        $row = [
            'date'         => $date->format('d-M-y'),     // 28-Mar-26
            'month'        => $date->format('M'),
            'year'         => $date->format('Y'),
            'inspected_by' => $this->pick($flat, 'inspected_by') ?? $r->user?->getFullNameAttribute(),
            'area'         => $this->pick($flat, 'area'),
        ];

        $values = $pests->mapWithKeys(fn ($p) => [$p->id => $this->pestValues($answer, $flat, $p)]);

        foreach ($pests as $p) {
            $row["count_{$p->id}"] = $this->number($values[$p->id]['count']);
        }
        foreach ($pests as $p) {
            $row["level_{$p->id}"] = $values[$p->id]['level'];
        }

        $row['cleanliness'] = $this->pick($flat, 'cleanliness');
        $row['structural']  = $this->pick($flat, 'structural');
        $row['ventilation'] = $this->pick($flat, 'ventilation');
        $row['services'] = $this->pick($flat, 'services');

        return $row;
    }

    /**
     * Reads one pest's count and infestation level from a stored answer. Three shapes are understood:
     *  A) a list of entries:  "pests": [{"pest_id": 1, "count": 0, "infestation_level": "Low"}, ...]
     *  B) a map keyed by the pest id or name:  "pests": {"1": {"count": 0, "level": "Low"}}
     *  C) flat keys:  "mosquito": 0, "mosquito_il": "Low"
     * Change this method if your answers are stored some other way.
     */
    private function pestValues(array $answer, array $flat, object $pest): array
    {
        $nameKey = $this->key($pest->name);
        $count   = null;
        $level   = null;

        foreach (['pests', 'pest', 'infestations', 'results', 'items'] as $listKey) {
            $container = $answer[$listKey] ?? null;

            if (! is_array($container)) {
                continue;
            }

            $isList = array_is_list($container);

            foreach ($container as $k => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $id   = $entry['pest_id'] ?? $entry['id'] ?? null;
                $name = $entry['pest'] ?? $entry['pest_name'] ?? $entry['name'] ?? null;

                $matches = ($id !== null && (int) $id === (int) $pest->id)
                    || ($name !== null && $this->key((string) $name) === $nameKey)
                    || (! $isList && (
                            (string) $k === (string) $pest->id
                            || $this->key((string) $k) === $nameKey
                        ));

                if ($matches) {
                    $count = $this->firstOf($entry, [
                        'count', 'score', 'value', 'total', 'quantity', 'qty',
                    ]);
                    $level = $this->firstOf($entry, [
                        'infestation_level', 'level', 'il',
                    ]);
                    break 2;
                }
            }
        }

        if ($count === null && isset($flat[$nameKey]) && is_scalar($flat[$nameKey])) {
            $count = $flat[$nameKey];
        }

        if ($level === null) {
            foreach (['il', 'level', 'infestationlevel'] as $suffix) {
                if (isset($flat[$nameKey . $suffix]) && is_scalar($flat[$nameKey . $suffix])) {
                    $level = (string) $flat[$nameKey . $suffix];
                    break;
                }
            }
        }

        return ['count' => $count, 'level' => $level];
    }

    private function label(object $pest): array
    {
        return $this->labels()[$this->key($pest->name)]
            ?? [$pest->name, $pest->name . ' IL'];
    }

    private function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    /** Lower-cases every key and drops everything but letters and digits, so "Mosq IL" matches mosq_il. */
    private function normalise(array $answer): array
    {
        $map = [];

        $flatten = function (array $items, string $prefix = '') use (&$flatten, &$map): void {
            foreach ($items as $key => $value) {
                $path = $prefix === ''
                    ? (string) $key
                    : $prefix . '.' . $key;

                $map[$this->key($path)] = $value;

                if (is_array($value)) {
                    $flatten($value, $path);
                }
            }
        };

        $flatten($answer);

        return $map;
    }

    private function pick(array $flat, string $column): ?string
    {
        foreach (self::ANSWER_KEYS[$column] as $candidate) {
            $value = $flat[$this->key($candidate)] ?? null;

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        return null;
    }

    private function firstOf(array $entry, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($entry[$key]) && $entry[$key] !== '') {
                return $entry[$key];
            }
        }

        return null;
    }

    /** Counts are written as numbers, so Excel can add them up. Anything else stays as text. */
    private function number(mixed $value): int|float|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : (string) $value;
    }
}
