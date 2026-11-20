<?php

namespace App\Http\Controllers;

use App\Exports\PestsReportExport;
use App\Services\PestsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class PestsReportController extends Controller
{
    public function __construct(private readonly PestsReportService $report) {}

    public function index(Request $request)
    {
        [$month, $year, $period] = $this->filters($request);

        return response()->json([
            'period'   => ['month' => $month, 'year' => $year, 'half' => $period],
            'headings' => $this->report->headings(),
            'rows'     => $this->report->rows($month, $year, $period),
        ]);
    }

    public function export(Request $request)
    {
        [$month, $year, $period] = $this->filters($request);

        return Excel::download(
            new PestsReportExport($month, $year, $period),
            sprintf('pests-report-%04d-%02d%s.xlsx', $year, $month, $period ? "-p{$period}" : '')
        );
    }

    /** @return array{0:int,1:int,2:?int} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'month'  => ['nullable', 'integer', 'between:1,12'],
            'year'   => ['nullable', 'integer', 'between:2000,2100'],
            'period' => ['nullable', 'integer', 'in:1,2'],
        ]);

        return [
            (int) ($data['month'] ?? Carbon::now()->month),
            (int) ($data['year'] ?? Carbon::now()->year),
            isset($data['period']) ? (int) $data['period'] : null,
        ];
    }
}
