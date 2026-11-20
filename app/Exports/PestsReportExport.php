<?php

namespace App\Exports;

use App\Services\PestsReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PestsReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $month,
        private readonly int $year,
        private readonly ?int $period = null,
    ) {}

    public function collection(): \Illuminate\Support\Enumerable
    {
        return app(PestsReportService::class)->rows($this->month, $this->year, $this->period)
            ->map(fn (array $row) => array_values($row));
    }

    public function headings(): array
    {
        return app(PestsReportService::class)->headings();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
