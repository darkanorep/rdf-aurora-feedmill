<?php

namespace App\Http\Controllers;

use App\Exports\BirdsReportExport;
use App\Services\BirdsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class BirdsReportController extends Controller
{
    public function __construct(private readonly BirdsReportService $report) {}

    public function index(Request $request)
    {
        [$month, $year] = $this->filters($request);

        return response()->json([
            'period'   => ['month' => $month, 'year' => $year],
            'headings' => BirdsReportService::HEADINGS,
            'rows'     => $this->report->rows($month, $year),
        ]);
    }

    public function export(Request $request)
    {
        [$month, $year] = $this->filters($request);

        return Excel::download(new BirdsReportExport($month,$year), sprintf('birds-report-%04d-%02d.xlsx', $year, $month));
    }

    /** @return array{0:int,1:int} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        return [(int) ($data['month'] ?? Carbon::now()->month), (int) ($data['year'] ?? Carbon::now()->year)];
    }
}
