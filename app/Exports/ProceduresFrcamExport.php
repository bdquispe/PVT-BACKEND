<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProceduresFrcamExport implements FromGenerator, WithHeadings, WithStyles
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
                $row->code,
                $row->pt_name,
                $row->pm_name,
                $row->pm_shortened,
                $row->reception_date,
                $row->city_start,
                $row->nup,
                $row->first_name,
                $row->second_name,
                $row->last_name,
                $row->mothers_last_name,
                $row->identity_card,
                $row->date_entry,
                $row->date_derelict,
                $row->degree_shortened,
                $row->birth_date,
                $row->date_death,
                $row->civil_status,
                $row->gender,
                $row->city_address,
                $row->zone,
                $row->street,
                $row->housing_unit,
                $row->number_address,
                $row->description
            ];
        }
    }

    public function headings(): array
    {
        return [
            'NRO',
            'NRO TRAMITE',
            'TIPO TRAMITE',
            'MODALIDAD',
            'SIGLA',
            'FECHA RECEPCION',
            'REGIONAL',
            'NUP',
            'PRIMER NOMBRE',
            'SEGUNDO NOMBRE',
            'APELLIDO PATERNO',
            'APELLIDO MATERNO',
            'CI',
            'FECHA INGRESO',
            'FECHA DESVINCULACION',
            'GRADO',
            'FECHA NACIMIENTO',
            'FECHA FALLECIMIENTO',
            'ESTADO CIVIL',
            'GENERO',
            'CIUDAD DOMICILIO',
            'ZONA/BARRIO/URBANIZACION',
            'CALLE/AVENIDA/CAMINO/CARRETERA',
            'CONDOMINIO/EDIFICIO/TORRE',
            'NUMERO DOMICILIO',
            'REFERENCIA DOMICILIO'
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
