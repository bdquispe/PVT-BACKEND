<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use \App\Models\Admin\Permission;
use \App\Models\Admin\Action;

class ContributionPermissionSeedeer extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
           [
                'action_id'=>Action::where('name','read')->first()->id,
                'name' => 'read-import-contribution',
                'display_name' => 'Ver importación de aportes'
            ],[
                'action_id'=>Action::where('name','create')->first()->id,
                'name' => 'create-import-senasir',
                'display_name' => 'Crear importación senasir'
            ], [
                'action_id'=>Action::where('name','create')->first()->id,
                'name' => 'create-import-command',
                'display_name' => 'Crear importación Comando'
            ], [
                'action_id'=>Action::where('name','download')->first()->id,
                'name' => 'download-report-senasir',
                'display_name' => 'Descarga reporte senasir'
            ], [
                'action_id'=>Action::where('name','download')->first()->id,
                'name' => 'download-report-command',
                'display_name' => 'Descarga reporte comando general'
            ], [
                'action_id'=>Action::where('name','read')->first()->id,
                'name' => 'read-import-payroll',
                'display_name' => 'Ver importación de planillas'
            ],[
                'action_id'=>Action::where('name','create')->first()->id,
                'name' => 'create-import-payroll-senasir',
                'display_name' => 'crear importación planilla senasir'
            ],[
                'action_id'=>Action::where('name','create')->first()->id,
                'name' => 'create-import-payroll-command',
                'display_name' => 'crear importación planilla comando general'
            ],[
                'action_id'=>Action::where('name','download')->first()->id,
                'name' => 'download-report-payroll-senasir',
                'display_name' => 'Descarga reporte planilla senasir'
            ],[
                'action_id'=>Action::where('name','download')->first()->id,
                'name' => 'download-report-payroll-command',
                'display_name' => 'Descarga reporte planilla comando general'
            ],[
                'action_id'=>Action::where('name','delete')->first()->id,
                'name' => 'delete-contribution-passive',
                'display_name' => 'Eliminar aporte sector pasivo'
            ],[
                'action_id'=>Action::where('name','delete')->first()->id,
                'name' => 'delete-contribution',
                'display_name' => 'Eliminar aporte sector activo'
            ],[
                'action_id' => Action::where('name', 'download')->first()->id,
                'name' => 'download-certifications',
                'display_name' => 'Descargar certificaciones de aportes'
            ],[
                'action_id' => Action::where('name', 'create')->first()->id,
                'name' => 'create-credentials',
                'display_name' => 'Crear credenciales para afiliado'
            ],[
                'action_id'=>Action::where('name','create')->first()->id,
                'name' => 'create-import-payroll-transcript',
                'display_name' => 'crear importación planilla transcrita'
            ],[
                'action_id'=>Action::where('name','download')->first()->id,
                'name' => 'download-report-payroll-transcript',
                'display_name' => 'Descarga reporte planilla transcrita'
            ],[
                'action_id' => Action::where('name','download')->first()->id,
                'name' => 'create-import-affiliates-availability',
                'display_name' => 'Crear importación afiliados en disponibilidad'
            ],[
                'action_id' => Action::where('name', 'download')->first()->id,
                'name' => 'download-report-affiliates-availability',
                'display_name' => 'Descargar reporte de importación afiliados en disponibilidad'
            ],[
                'action_id' => Action::where('name', 'read')->first()->id,
                'name' => 'read-import-payroll-senasir',
                'display_name' => 'Ver importación de planillas Senasir'
            ],[
                'action_id' => Action::where('name', 'read')->first()->id,
                'name' => 'read-import-payroll-comando',
                'display_name' => 'Ver importación de planillas Comando'
            ],[
                'action_id' => Action::where('name', 'read')->first()->id,
                'name' => 'read-import-payroll-transcript',
                'display_name' => 'Ver importación de planillas Transcripción'
            ],[
                'action_id' => Action::where('name', 'create')->first()->id,
                'name' => 'create-import-mora',
                'display_name' => 'Crear importación de afiliados en Mora'
            ],[
                'action_id'=>Action::where('name','delete')->first()->id,
                'name' => 'delete-massive-contribution-passive',
                'display_name' => 'Eliminación masiva aportes sector pasivo'
            ],[
                'action_id'=>Action::where('name','delete')->first()->id,
                'name' => 'delete-massive-contribution',
                'display_name' => 'Eliminación masiva aportes sector activo'
            ]
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }
    }
}
