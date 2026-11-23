<?php

namespace App\Http\Controllers;

use App\Exports\CobsReportExport;
use App\Services\CobsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CobsReportController extends Controller
{
    public function __construct(private readonly CobsReportService $report) {}

    public function index(Request $request)
    {
        [$month, $year, $unitId] = $this->filters($request);

        return response()->json([
            'period'   => ['month' => $month, 'year' => $year, 'unit_id' => $unitId],
            'headings' => CobsReportService::HEADINGS,
            'rows'     => $this->report->rows($month, $year, $unitId),
        ]);
    }

    public function export(Request $request)
    {
        [$month, $year, $unitId] = $this->filters($request);

        return Excel::download(
            new CobsReportExport($month, $year, $unitId),
            sprintf('cobs-report-%04d-%02d%s.xlsx', $year, $month, $unitId ? "-unit{$unitId}" : '')
        );
    }

    /** @return array{0:int,1:int,2:?int} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'month'   => ['nullable', 'integer', 'between:1,12'],
            'year'    => ['nullable', 'integer', 'between:2000,2100'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        return [
            (int) ($data['month'] ?? Carbon::now()->month),
            (int) ($data['year'] ?? Carbon::now()->year),
            isset($data['unit_id']) ? (int) $data['unit_id'] : null,
        ];
    }
}
