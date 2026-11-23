<?php

namespace App\Exports;

use App\Services\BirdsReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BirdsReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(private readonly int $month, private readonly int $year) {}

    public function collection(): \Illuminate\Support\Enumerable
    {
        return app(BirdsReportService::class)
            ->rows($this->month, $this->year)
            ->map(function (array $row) {
                $values = array_map(function ($value) {
                    if (is_string($value)) {
                        $decoded = json_decode(trim($value), true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $value = $decoded;
                        }
                    }

                    if ($value instanceof \Illuminate\Support\Collection) {
                        $value = $value->all();
                    }

                    if (is_array($value)) {
                        $items = array_filter(
                            array_map(
                                fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                                $value
                            ),
                            fn ($item) => $item !== ''
                        );

                        return $items ? implode(', ', $items) : 'N/A';
                    }

                    return blank($value) ? 'N/A' : $value;
                }, $row);

                return array_values($values);
            });
    }

    public function headings(): array
    {
        return BirdsReportService::HEADINGS;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF008000'],
                ],
            ],
        ];
    }
}
