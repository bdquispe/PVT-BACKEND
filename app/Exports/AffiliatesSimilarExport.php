<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AffiliatesSimilarExport implements FromGenerator, WithHeadings, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function generator(): \Generator
    {
        $counter = 1;
        foreach ($this->data as $row) {
            yield [
                $counter++,
                $row->nup1,
                $row->ci1,
                $row->last_name1,
                $row->mothers_last_name1,
                $row->surname_husband1,
                $row->first_name1, 
                $row->second_name1,
                $row->month1,
                $row->year1,
                $row->unit_code1,
                $row->hierarchy_code1,
                $row->degree_code1,
                $row->base_wage1,
                $row->seniority_bonus1,
                $row->study_bonus1,
                $row->position_bonus1,
                $row->border_bonus1,
                $row->east_bonus1,
                $row->gain1,
                $row->total1,
                $row->contributionable_type1,
                $row->nup2,
                $row->ci2,
                $row->last_name2,
                $row->mothers_last_name2,
                $row->surname_husband2,
                $row->first_name2, 
                $row->second_name2,
                $row->month2,
                $row->year2,
                $row->unit_code2,
                $row->hierarchy_code2,
                $row->degree_code2,
                $row->base_wage2,
                $row->seniority_bonus2,
                $row->study_bonus2,
                $row->position_bonus2,
                $row->border_bonus2,
                $row->east_bonus2,
                $row->gain2,
                $row->total2,
                $row->contributionable_type2
            ];
        }
    }

    public function headings(): array
    {
        return [
            'NRO',
            'NUP',
            'C.I.',
            'AP.PATERNO',
            'AP.MATERNO',
            'AP.CASADA',
            'NOMBRE 1',
            'NOMBRE 2',
            'MES',
            'AÑO',
            'UNIDAD(CODIGO)',
            'NIVEL(CODIGO)',
            'GRADO(CODIGO)',
            'SUELDO',
            'ANTIGUEDAD',
            'BONO ESTUDIO',
            'BONO CARGO',
            'BONO FRONTERA',
            'BONO ORIENTE',
            'TOTAL GANADO',
            'APORTE',
            'TIPO DE CONTRIBUCION',
            'NUP',
            'C.I.',
            'AP.PATERNO',
            'AP.MATERNO',
            'AP.CASADA',
            'NOMBRE 1',
            'NOMBRE 2',
            'MES',
            'AÑO',
            'UNIDAD(CODIGO)',
            'NIVEL(CODIGO)',
            'GRADO(CODIGO)',
            'SUELDO',
            'ANTIGUEDAD',
            'BONO ESTUDIO',
            'BONO CARGO',
            'BONO FRONTERA',
            'BONO ORIENTE',
            'TOTAL GANADO',
            'APORTE',
            'TIPO DE CONTRIBUCION'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headings = $this->headings();
        $columnCount = count($headings);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        foreach (range(1, $columnCount) as $colIndex) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $headerRange = "A1:{$lastColumn}1";
        $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(-1);

        return [];
    }
}
