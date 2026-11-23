<?php

namespace App\Exports;

use App\Services\CobsReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CobsReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        private readonly int $month,
        private readonly int $year,
        private readonly ?int $unitId = null,
    ) {}

    public function collection(): \Illuminate\Support\Enumerable
    {
        return app(CobsReportService::class)->rows($this->month, $this->year, $this->unitId)
            ->map(fn (array $row) => array_values($row));
    }

    public function headings(): array
    {
        return CobsReportService::HEADINGS;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
