<?php

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Contribution\ContributionCopyPayrollCommand;
use App\Models\Contribution\PayrollCommand;
use Carbon\Carbon;
use DateTime;
use DB;
use Auth;
use App\Helpers\Util;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArchivoPrimarioExport;

class ImportPayrollCommandController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/contribution/update_base_wages",
     *      tags={"CONTRIBUCION"},
     *      summary="PASO 3 ACTUALIZACION DE SUELDOS BASE",
     *      operationId="updateData",
     *      description="Actualización de sueldos base tabla base_wages",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="month", type="integer",description="mes required",example=11),
     *              @OA\Property(property="year", type="integer",description="año required",example=2021)
     *          )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */
    public function update_base_wages(Request $request){
        $request->validate([
          'month' => 'required|integer|min:1|max:12',
          'year' => 'required|integer|min:1',
        ]);

        try{
            DB::beginTransaction();
            $message = "No hay datos de sueldos base por actualizar";
            $successfully =false;
            $user = Auth::user();
            $user_id = Auth::user()->id;

            $month = $request->get('month');
            $year_completed =  $request->get('year');
            $year = substr(strval($year_completed), strlen($year_completed)-2,2);

            $date_base_wages = Carbon::create($year_completed, $month, 1);
            $date_base_wages = Carbon::parse($date_base_wages)->format('Y-m-d');

            if(!$this->exists_data_table_base_wages($date_base_wages)){
                $query = "select * from update_base_wages($month,$year,$user_id,'$date_base_wages');";
            $update_base_wages = DB::select($query);

            if($update_base_wages != []){
                $message = "Realizado con éxito la actualización de sueldos base";
                $successfully = true;
            }

            DB::commit();

            return response()->json([
                'message' => $message,
                'payload' => [
                    'successfully' => $successfully,
                    'update_base_wages'=> $update_base_wages
                ],
            ]);
            }else{
                return response()->json([
                    'message' => "Ya existen datos de sueldos base, no se puede volver a realizar esta acción",
                    'payload' => [
                        'successfully' => $successfully,
                    ],
                ]);

            }
            }catch(Exception $e){
                DB::rollBack();
                return response()->json([
                    'message' => 'Error en el formateo de datos',
                    'payload' => [
                        'successfully' => false,
                        'error' => $e->getMessage(),
                    ],
            ]);
        }
    }
     /**
     * @OA\Post(
     *      path="/api/contribution/upload_copy_payroll_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="PASO 1 COPIADO DE DATOS PLANILLA COMANDO",
     *      operationId="upload_copy_payroll_command",
     *      description="Copiado de datos del archivo de planillas comando a la tabla payroll_copy_commands",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *            @OA\Property(property="file", type="file", description="file required", example="file"),
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2023-04-01"),
     *              @OA\Property(property="reimbursement", type="boolean",description="Es reintegro",example=false)
  
     *            ) 
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */

    public function upload_copy_payroll_command(request $request)
    {
        $request->validate([
            'file' => 'required',
            'date_payroll' => 'required|date_format:"Y-m-d"',
            'reimbursement' => 'required'
        ]);
        $extencion = strtolower($request->file->getClientOriginalExtension());
        $file_name_entry = $request->file->getClientOriginalName();
        $reimbursement = $request->reimbursement;
        $reimbursement_bool = filter_var($request->reimbursement ?? false, FILTER_VALIDATE_BOOLEAN); 
       DB::beginTransaction();
        try{
            $username = env('FTP_USERNAME');
            $password = env('FTP_PASSWORD');
            $successfully = false;
            if($extencion == "csv"){
                $date_payroll = Carbon::parse($request->date_payroll);
                $year = $date_payroll->format("Y");
                $year_format = $date_payroll->format("y");
                $month = $date_payroll->format("m");
                $month_format =(int)$month;

                $rollback_period = "delete from payroll_copy_commands WHERE mes = $month_format AND a_o = $year AND reimbursement = $reimbursement::BOOLEAN";

                $rollback_period  = DB::connection('db_aux')->select($rollback_period);
                $file_name='';
                if(!$reimbursement_bool){
                    $file_name = "comando-".$month."-".$year.'.'.$extencion;
                }else{
                    $file_name = "re-comando-".$month."-".$year.'.'.$extencion;
                }
                //return $file_name_entry .'-'. $file_name.'-'.$reimbursement;
                
                    if($file_name_entry == $file_name){
                        $base_path = 'planillas/planilla_comando';
                        $file_path = Storage::disk('ftp')->putFileAs($base_path,$request->file,$file_name);
                        $base_path ='ftp://'.env('FTP_HOST').env('FTP_ROOT').$file_path;

                        $temporary_payroll = "create temporary table payroll_copy_commands_tmp(uni varchar,desg varchar, mes varchar, a_o varchar,car varchar,pat varchar,mat varchar,apes varchar,nom varchar,nom2 varchar,eciv varchar,niv varchar,gra varchar,sex varchar,dtr varchar,sue varchar,cat varchar,est varchar,carg varchar,fro varchar,ori varchar,
                                      gan varchar, mus varchar,lpag varchar,nac varchar,ing varchar)";
                        $temporary_payroll = DB::connection('db_aux')->select($temporary_payroll);

                        $copy = "copy payroll_copy_commands_tmp(uni,desg,mes,a_o,car,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,dtr,sue,cat,est,carg,fro,ori,
                                gan, mus,lpag,nac,ing)
                                FROM PROGRAM 'wget -q -O - $@  --user=$username --password=$password $base_path'
                                WITH DELIMITER ':' CSV header;";
                        $copy = DB::connection('db_aux')->select($copy);

                        if(!$reimbursement_bool){
                            $insert = "INSERT INTO payroll_copy_commands(uni,desg,mes,a_o,car,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,sue,cat,est,carg,fro,ori,gan,mus,lpag,nac,ing,created_at,updated_at,reimbursement,dtr)
                            SELECT uni,desg::INTEGER,mes::INTEGER,a_o::INTEGER,car,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,sue,cat,est,carg,fro,ori,gan,mus,lpag,nac,ing,current_timestamp,current_timestamp,false,dtr::NUMERIC::INTEGER FROM payroll_copy_commands_tmp; ";
                            $insert = DB::connection('db_aux')->select($insert);

                            $update_year="UPDATE payroll_copy_commands set a_o = concat(20,'',a_o)::integer where mes =$month_format and a_o=$year_format and reimbursement=false";
                            $update_year = DB::connection('db_aux')->select($update_year);
                        }else{
                            $insert = "INSERT INTO payroll_copy_commands(uni,desg,mes,a_o,car,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,sue,cat,est,carg,fro,ori,gan,mus,lpag,nac,ing,created_at,updated_at,reimbursement,dtr)
                            SELECT uni,desg::INTEGER,mes::INTEGER,a_o::INTEGER,car,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,sue,cat,est,carg,fro,ori,gan,mus,lpag,nac,ing,current_timestamp,current_timestamp,true,dtr::NUMERIC::INTEGER FROM payroll_copy_commands_tmp; ";
                            $insert = DB::connection('db_aux')->select($insert);

                            $update_year="UPDATE payroll_copy_commands set a_o = concat(20,'',a_o)::integer where mes =$month_format and a_o=$year_format and reimbursement=true";
                            $update_year = DB::connection('db_aux')->select($update_year);
                        }

                        $drop = "drop table if exists payroll_copy_commands_tmp";
                        $drop = DB::select($drop);

                        $query = "select * from format_payroll_copy_commands($month_format,$year,$reimbursement);";
                        $data_format = DB::connection('db_aux')->select($query);
                        DB::commit();


                        if($data_format != []){
                            $message = "Realizado con éxito";
                            $successfully = true;
                        }

                        return response()->json([
                            'message' => $message,
                            'payload' => [
                                'successfully' => $successfully,
                                'data_count' => $this->data_count_payroll_command($month_format,$year,$reimbursement)
                            ],
                        ]);
                    } else {
                           return response()->json([
                            'message' => 'Error en el copiado del archivo',
                            'payload' => [
                                'successfully' => $successfully,
                                'error' => 'El nombre del archivo no coincide con en nombre requerido'
                            ],
                        ]);
                    }
            } else {
                    return response()->json([
                        'message' => 'Error en el copiado del archivo',
                        'payload' => [
                            'successfully' => $successfully,
                            'error' => 'El archivo no es un archivo CSV'
                        ],
                    ]);
            }
       }catch(Exception $e){
           DB::rollBack();
           return response()->json([
               'message' => 'Error en el copiado de datos',
               'payload' => [
                   'successfully' => false,
                   'error' => $e->getMessage(),
               ],
           ]);
        }
    }
    // -------------metodo para verificar si ya existen sueldos base registrados-----//
    public function exists_data_table_base_wages($date){
        $exists_data = true;
        $query = "select * from base_wages bw  where month_year = '$date'";
        $verify_data = DB::select($query);

        if($verify_data == []) $exists_data = false;

        return $exists_data;
    }

    //data count payroll commnada
    public function data_count_payroll_command($month,$year,$reimbursement){
        $data_count['num_total_data_copy'] = 0;
        $data_count['num_data_validated'] = 0;
        $data_count['num_data_regular'] = 0;
        $data_count['num_data_new'] = 0;

        //---TOTAL DE DATOS DEL ARCHIVO
        $query_total_data = "SELECT count(id) FROM payroll_copy_commands where mes = $month::INTEGER and a_o = $year::INTEGER and reimbursement=$reimbursement::BOOLEAN";
        $query_total_data = DB::connection('db_aux')->select($query_total_data);
        $data_count['num_total_data_copy'] = $query_total_data[0]->count;
        // TOTAL VALIDADOS
        $data_count['num_data_validated'] =PayrollCommand::data_count($month,$year,$reimbursement)['validated'];
        //CANTIDAD DE AFILIADOS REGULARES
        $data_count['num_data_regular'] = PayrollCommand::data_count($month,$year,$reimbursement)['regular'];
        //CANTIDAD DE AFILIADOS NUEVOS
        $data_count['num_data_new'] =PayrollCommand::data_count($month,$year,$reimbursement)['new'];

        return  $data_count;
    }
         /**
     * @OA\Post(
     *      path="/api/contribution/import_payroll_command_progress_bar",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="INFORMACIÓN DE PROGRESO DE IMPORTACIÓN PLANILLA COMANDO",
     *      operationId="import_payroll_command_progress_bar",
     *      description="Muestra la información de la importación de Comando  (-1)Si existió algún error en algún paso, (100) Si todo fue exitoso, (30-60)Paso 1 y 2 (0)si esta iniciando la importación",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2022-03-01"),
     *              @OA\Property(property="reimbursement", type="boolean", description="Es reintegro required", example=false)
     *            )
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */

    public function import_payroll_command_progress_bar(Request $request){

        $request->validate([
            'date_payroll' => 'required|date_format:"Y-m-d"',
            "reimbursement" => 'required'
          ]);

        $date_payroll = Carbon::parse($request->date_payroll);
        $year = (int)$date_payroll->format("Y");
        $month = (int)$date_payroll->format("m");
        $reimbursement = boolval($request->reimbursement);
        $message = "Exito";

        $result['file_exists'] = false;
        $result['file_name'] = "";
        $result['percentage'] = 0;
        $result['query_step_1'] = false;
        $result['query_step_2'] = false;

        $result['query_step_1'] = $this->exists_data_payroll_copy_commands($month,$year,$reimbursement);
        $result['query_step_2'] = PayrollCommand::data_period($month,$year,$reimbursement)['exist_data'];
        $date_payroll_format = $request->date_payroll;

        //verificamos si existe el archivo de importación 
        $date_month= strlen($month)==1?'0'.$month:$month;
        $origin_name = 'comando-';
        $new_file_name = "comando-".$date_month."-".$year.'.csv';
        $base_path = 'planillas/planilla_comando'.'/'.$new_file_name;
        if (Storage::disk('ftp')->has($base_path)) {
            $result['file_name'] = $new_file_name;
            $result['file_exists'] = true;
        }

        if($result['file_exists'] == true && $result['query_step_1'] == true && $result['query_step_2'] == true){
            $result['percentage'] = 100;
        }else{
            if($result['file_exists'] == true && $result['query_step_1'] == true && $result['query_step_2'] == false){
                $result['percentage'] = 50;
            }else{
                if ($result['query_step_1'] == false && $result['query_step_2'] == false) {
                    $result['percentage'] = 0;
                } else {
                    $result['percentage'] = -1;
                    $message = "Error! Algo salió mal en algún paso, por favor vuelva a iniciar la importación.";
                }
            }
        }

        return response()->json([
            'message' => $message,
            'payload' => [
                'import_progress_bar' =>  $result,
                'data_count' =>  $this->data_count_payroll_command($month,$year,$reimbursement)
            ],
        ]);
    }
    
    //método para verificar si existe datos en el paso 1 

    public function exists_data_payroll_copy_commands($month,$year,$reimbursement){
        $exists_data = true;
        $query = "select * from payroll_copy_commands where mes = $month::INTEGER and a_o = $year::INTEGER and reimbursement = $reimbursement::BOOLEAN;";
        $verify_data = DB::connection('db_aux')->select($query);

        if($verify_data == []) $exists_data = false;

        return $exists_data;
    }

    //borrado de datos de la tabla payroll_copy_commands paso 1
    public function delete_payroll_copy_commands($month, $year,$reimbursement)
    {
             if($this->exists_data_payroll_copy_commands($month,$year,$reimbursement))
             {
                $query = "delete from payroll_copy_commands where a_o = $year::INTEGER and mes = $month::INTEGER and reimbursement = $reimbursement::BOOLEAN;";
                $query = DB::connection('db_aux')->select($query);
                DB::commit();
                return true;
             }
             else
                 return false;
    }

    /**
     * @OA\Post(
     *      path="/api/contribution/rollback_payroll_copy_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="REHACER PASO 1 IMPORTACIÓN PLANILLA COMANDO",
     *      operationId="rollback_payroll_copy_command",
     *      description="Para rehacer paso 1 de la importación Comando",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2022-03-01"),
     *              @OA\Property(property="reimbursement", type="boolean", description="Es reintegro required", example= false)
     *            )
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return voidvalid_rollback
    */

    public function rollback_payroll_copy_command(Request $request)
    {
       $request->validate([
           'date_payroll' => 'required|date_format:"Y-m-d"',
           'reimbursement' => 'required'
         ]);
       DB::beginTransaction();
       try{
           $result['delete_step_1'] = false;
           $valid_rollback = false;
           $date_payroll = Carbon::parse($request->date_payroll);
           $reimbursement = $request->reimbursement;

           $year = (int)$date_payroll->format("Y");
           $month = (int)$date_payroll->format("m");
    
           if($this->exists_data_payroll_copy_commands($month,$year,$reimbursement) && !PayrollCommand::data_period($month,$year,$reimbursement)['exist_data']){
               $result['delete_step_1'] = $this->delete_payroll_copy_commands($month,$year,$reimbursement);

               if($result['delete_step_1'] == true){
                   $valid_rollback = true;
                   $message = "Realizado con éxito!";
               }
           }else{
               if(PayrollCommand::data_period($month,$year,$reimbursement)['exist_data'])
                   $message = "No se puede rehacer, por que ya realizó la validación del la planilla de Comando General";
               else
                   $message = "No existen datos para rehacer";
           }

           DB::commit();

           return response()->json([
               'message' => $message,
               'payload' => [
                   'valid_rollbackk' =>  $valid_rollback,
                   'delete_step' =>  $result
               ],
           ]);
       }catch (Exception $e)
       {
           DB::rollback();
           return $e;
       }
    }

    /**
     * @OA\Post(
     *      path="/api/contribution/list_months_validate_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="LISTA LOS MESES QUE SE REALIZARON IMPORTACIONES PLANILLA COMANDO EN BASE A UN AÑO DADO EJ:2021",
     *      operationId="list_months_validate_command",
     *      description="Lista los meses importados en la tabla payroll_copy_commands enviando como parametro un año en especifico",
     *     @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *             @OA\Property(property="period_year", type="integer",description="Año de contribución a listar",example= "2022"),
     *             @OA\Property(property="with_data_count", type="boolean",description="valor para pedir envio de conteo de datos",example= false)
     *            )
     *
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */

    public function list_months_validate_command(Request $request)
    {
       $request->validate([
           'period_year' => 'required|date_format:"Y"',
           'with_data_count'=>'boolean'
       ]);
       $with_data_count = !isset($request->with_data_count) || is_null($request->with_data_count)? true:$request->with_data_count;
        $period_year = $request->get('period_year');
    //     ///Obtener para registro de planilla
    //     $query = "SELECT  distinct month_p,year_p,  to_char( (to_date(year_p|| '-' ||month_p, 'YYYY/MM/DD')), 'TMMonth') as period_month_name from payroll_commands where deleted_at  is null and year_p =$period_year and reimbursement=false group by month_p, year_p";
    //     $query = DB::select($query);
    //     $query_months = "select id as period_month ,name  as period_month_name from months order by id asc";
    //     $query_months = DB::select($query_months);
      
    //     foreach ($query_months as $month) {
    //        $month->state_importation = false;
    //        foreach ($query as $month_payroll) {
    //            if($month->period_month == $month_payroll->month_p){
    //                $month->state_importation = true;
    //                break;
    //            }
    //        }
    //        if($with_data_count)
    //        $month->data_count = $this->data_count_payroll_command($month->period_month,$period_year,'false');
    //     }

    //     ///obtener para reintegros
    //     $query_re = "SELECT  distinct month_p,year_p,  to_char( (to_date(year_p|| '-' ||month_p, 'YYYY/MM/DD')), 'TMMonth') as period_month_name from payroll_commands where deleted_at  is null and year_p =$period_year and reimbursement=true group by month_p, year_p";
    //     $query_re = DB::select($query_re);
    //     $query_months_re = "select id as period_month ,name  as period_month_name from months order by id asc";
    //     $query_months_re = DB::select($query_months_re);
      
    //     foreach ($query_months_re as $month_re) {
    //        $month_re->state_importation = false;
    //        foreach ($query_re as $month_payroll_re) {
    //            if($month_re->period_month == $month_payroll_re->month_p){
    //                $month_re->state_importation = true;
    //                break; 
    //            }
    //        }
    //        if($with_data_count)
    //        $month_re->data_count = $this->data_count_payroll_command($month_re->period_month,$period_year,'true');
    //     }

    //     return response()->json([
    //        'message' => "Exito",
    //        'payload' => [
    //            'list_months' =>  $query_months,
    //            'count_months' =>  count($query),
    //            'list_months_re' =>  $query_months_re,
    //            'count_months_re' =>  count($query_re)
    //        ],
    //    ]);
        ///Obtener para registro de planilla
        $months = collect(DB::select("SELECT id as period_month, name as period_month_name FROM months ORDER BY id ASC"));
        $months_ids = $months->pluck('period_month');

        $query = "SELECT  distinct month_p,year_p,  to_char( (to_date(year_p|| '-' ||month_p, 'YYYY/MM/DD')), 'TMMonth') as period_month_name from payroll_commands where deleted_at  is null and year_p =$period_year and reimbursement=false group by month_p, year_p";
        $periods =collect(DB::select($query));
        $periods = $periods->pluck('month_p');

        $months_not_import = $months_ids->diff($periods);
        $months_import = $months_ids->intersect($periods);

        $months_not_import_with_name = $months->whereIn('period_month', $months_not_import)->values();
        $months_import_with_name = $months->whereNotIn('period_month', $months_not_import)->values();

        if($with_data_count) {
            foreach($months_import_with_name->all() as $months_import) {
                $months_import->data_count = $this->data_count_payroll_command($months_import->period_month, $period_year, 'false');
            }
        }

        ///obtener para reintegros
        $query_re = "SELECT  distinct month_p,year_p,  to_char( (to_date(year_p|| '-' ||month_p, 'YYYY/MM/DD')), 'TMMonth') as period_month_name from payroll_commands where deleted_at  is null and year_p =$period_year and reimbursement=true group by month_p, year_p";
        $periods_re = collect(DB::select($query_re));
        $periods_re = $periods_re->pluck('month_p');

        $months_not_import_re = $months_ids->diff($periods_re);
        $months_import_re = $months_ids->intersect($periods_re);

        $months_not_import_with_name_re = $months->whereIn('period_month', $months_not_import_re)->values();
        $months_import_with_name_re = $months->whereNotIn('period_month', $months_not_import_re)->values();

        if($with_data_count) {
            foreach($months_import_with_name_re->all() as $month_import_re) {
                $month_import_re->data_count = $this->data_count_payroll_command($month_import_re->period_month, $period_year, 'true');
            }

        }

        return response()->json([
            'message' => "Exito",
            'payload' => [
                'list_months' =>  $months_import_with_name->all(),
                'list_months_not_import' => $months_not_import_with_name->all(),
                'list_months_re' =>  $months_import_with_name_re->all(),
                'list_months_not_import_re' => $months_not_import_with_name_re->all(),
            ],
        ]);
    }

 /**
     * @OA\Post(
     *      path="/api/contribution/validation_payroll_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="PASO 2 VALIDACION DE DATOS PLANILLA COMANDO GENERAL",
     *      operationId="validation_payroll_command",
     *      description="validacion de datos  de planilla de comando general",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2022-03-01"),
     *              @OA\Property(property="reimbursement", type="boolean",description="Es reintegro",example= false)
     *            )
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */

    public function validation_payroll_command(Request $request){
        $request->validate([
        'date_payroll' => 'required|date_format:"Y-m-d"',
        'reimbursement' => 'required'
        ]);
        try{
                DB::beginTransaction();
                $user_id = Auth::user()->id;
                $message = "No hay datos por validar";
                $successfully =false;
                $date_payroll_format = $request->date_payroll;
                $date_payroll = Carbon::parse($request->date_payroll);
                $year = (int)$date_payroll->format("Y");
                $month = (int)$date_payroll->format("m");
                $reimbursement=$request->reimbursement;
                $last_date = Carbon::parse($year.'-'.$month)->toDateString();
                $num_data_no_validated = 0;
                $connection_db_aux = Util::connection_db_aux();

                if($this->exists_data_payroll_copy_commands($month,$year,$reimbursement)){
                    if(!PayrollCommand::data_period($month,$year,$reimbursement)['exist_data']){

                        $query = "select registration_payroll_command('$connection_db_aux',$month,$year,$reimbursement,$user_id);";
                        $data_validated = DB::select($query);

                        if(PayrollCommand::data_period($month,$year,$reimbursement)['exist_data']){
                            $successfully =true;
                            $update_validated ="update payroll_copy_commands set is_validated = true where mes =$month and a_o = $year and reimbursement = $reimbursement::BOOLEAN";
                            $update_validated = DB::connection('db_aux')->select($update_validated);
                            $message = 'Exito';
                            $update_affiliate ="select update_affiliate_command('$date_payroll_format',$user_id,$year,$month)";
                            $update_affiliate = DB::select($update_affiliate);
                        }
                        if(PayrollCommand::data_count($month,$year,$reimbursement)['new']>0){
                            $message = 'Excel';
                        }
                        DB::commit();
                        $data_count= $this->data_count_payroll_command($month,$year,$reimbursement);

                        return response()->json([
                            'message' => $message,
                            'payload' => [
                                'successfully' => $successfully,
                                'data_count' =>  $data_count
                            ],
                        ]);
                    }else{
                        return response()->json([
                            'message' => " Error! ya realizó la validación de datos",
                            'payload' => [
                                'successfully' => $successfully,
                                'error' => 'Error! ya realizó la validación de datos.'
                            ],
                        ]);
                    }

                }else{
                    return response()->json([
                        'message' => "Error no existen datos en la tabla del copiado de datos",
                        'payload' => [
                            'successfully' => $successfully,
                            'error' => 'Error el primer paso no esta concluido.'
                        ],
                    ]);
                }
            }catch(Exception $e){
                DB::rollBack();
                return response()->json([
                'message' => 'Error en la busqueda de datos de titulares.',
                'payload' => [
                    'successfully' => false,
                    'error' => $e->getMessage(),
                ],
                ]);
            }
        }
/**
     * @OA\Post(
     *      path="/api/contribution/download_new_affiliates_payroll_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="GENERA REPORTE EXCEL DE AFILIADOS NUEVOS REMITIDOS POR COMANDO",
     *      operationId="download_new_affiliates_payroll_command",
     *      description="Genera el archivo excel de afiliados nuevos remitidos por COMANDO por mes y año",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2022-03-01"),
     *              @OA\Property(property="reimbursement", type="boolean",description="Es reintegro? required",example= false)
     *            )
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */
   
    public function download_new_affiliates_payroll_command(request $request) {

        $request->validate([
            'date_payroll' => 'required|date_format:"Y-m-d"',
            'reimbursement' => 'required'
        ]);

        DB::beginTransaction();
        $message = "No hay datos";
        $data_cabeceras=array(array("ID_AFILIADO","ID_UNIDAD","ID_DESGLOSE","ID_CATEGORÍA","MES","AÑO","CARNET", 
        "APELLIDO PATERNO","APELLIDO MATERNO","AP_CASADA","PRIMER NOMBRE","SEGUNDO NOMBRE","ESTADO CIVIL","ID_JERARQUÍA","ID_GRADO","SEXO",
        "SUELDO BASE","BONO ANTIGUEDAD","BONO ESTUDIO","BONO A CARGO","BONO FRONTERA","BONO ORIENTE",
        "TOTAL GANADO","MUSERPOL","LÍQUIDO PAGABLE","FECHA DE NACIMIENTO","FECHA DE INGRESO",
        "TIPO DE AFILIADO"));

        $date_payroll = Carbon::parse($request->date_payroll);
        $year = (int)$date_payroll->format("Y");
        $month = (int)$date_payroll->format("m");
        $reimbursement = $request->reimbursement;
        $data_payroll_command = "select  * from  payroll_commands  where month_p ='$month' and year_p='$year' and reimbursement='$reimbursement::BOOLEAN' and affiliate_type = 'NUEVO'";
                    $data_payroll_command = DB::select($data_payroll_command);
                            if(count($data_payroll_command)> 0){
                                $message = "Excel";
                                foreach ($data_payroll_command as $row){
                                    array_push($data_cabeceras, array($row->affiliate_id ,$row->unit_id ,$row->breakdown_id ,$row->category_id ,
                                    $row->month_p, $row->year_p, $row->identity_card, $row->last_name , $row->mothers_last_name, $row->surname_husband, $row->first_name, $row->second_name, 
                                    $row->civil_status, $row->hierarchy_id, $row->degree_id, $row->gender, $row->base_wage, $row->seniority_bonus, $row->study_bonus, $row->position_bonus,
                                    $row->border_bonus, $row->east_bonus, $row->gain, $row->total, $row->payable_liquid, $row->birth_date, $row->date_entry,
                                    $row->affiliate_type
                                ));
                                }

                                $export = new ArchivoPrimarioExport($data_cabeceras);
                                $file_name = "Afiliados_Nuevos_Comando";
                                $extension = '.xls';
                                return Excel::download($export, $file_name."_".$month."_".$year.$extension);
                            }else{
                                return abort(403, 'No existen afiliados nuevos en Comando para mostrar');
                            }
    }


    /**
     * @OA\Post(
     *      path="/api/contribution/report_payroll_command",
     *      tags={"IMPORTACION-PLANILLA-COMANDO"},
     *      summary="GENERA REPORTE EXCEL DE DATOS REMITIDOS POR COMANDO",
     *      operationId="report_import_command",
     *      description="Genera el archivo excel de los datos remitidos por COMANDO por mes y año",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_payroll", type="string",description="fecha de planilla required",example= "2022-03-01"),
     *              @OA\Property(property="reimbursement", type="boolean",description="Es reintegro? required",example= false)
     * 
     *            )
     *          ),
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *            type="object"
     *         )
     *      )
     * )
     *
     * Logs user into the system.
     *
     * @param Request $request
     * @return void
    */

    public function report_payroll_command(request $request) {

        $request->validate([
            'date_payroll' => 'required|date_format:"Y-m-d"',
            'reimbursement' => 'required'
        ]);

        DB::beginTransaction();
        $message = "No hay datos";

        ini_set('max_execution_time', '300');

        $date_payroll_format = $request->date_payroll;
        $data_cabeceras=array(array("ID","UNIDAD","DESGLOSE","CATEGORÍA","MES","AÑO","CARNET","APELLIDO PATERNO","APELLIDO MATERNO",
        "AP_CASADA","PRIMER NOMBRE","SEGUNDO NOMBRE","ESTADO CIVIL","JERARQUIA","GRADO","GENERO","DÍAS TRABAJADOS","SUELDO BASE","BONO ANTIGÜEDAD","BONO ESTUDIO",
        "BONO A CARGO","BONO FRONTERA","BONO ORIENTE","TOTAL GANADO","TOTAL APORTE","LIQUIDO PAGABLE","FECHA DE NACIMIENTO",
        "FECHA DE INGRESO","TIPO DE AFILIADO"
    ));

        $date_payroll = Carbon::parse($request->date_payroll);
        $year = (int)$date_payroll->format("Y");
        $month = (int)$date_payroll->format("m");
        $reimbursement = $request->reimbursement;
        $data_payroll_command = PayrollCommand::whereMonth_p($month)->whereYear_p($year)->whereReimbursement($reimbursement)->get();

        $message = "Excel";
            foreach ($data_payroll_command as $row){
                array_push($data_cabeceras, array($row->id,$row->unit->name,$row->breakdown->name, isset($row->category->name) ? $row->category->name:'',
                $row->month_p, $row->year_p, $row->identity_card, $row->last_name, $row->mothers_last_name, $row->surname_husband, 
                $row->first_name, $row->second_name, $row->civil_status, $row->hierarchy->name, $row->degree->name, $row->gender, $row->days_worked, $row->base_wage, $row->seniority_bonus, $row->study_bonus, $row->position_bonus, $row->border_bonus, $row->east_bonus,
                $row->gain, $row->total, $row->payable_liquid, $row->birth_date, $row->date_entry,
                $row->affiliate_type
              ));
            }

            $export = new ArchivoPrimarioExport($data_cabeceras);
            $file_name = "Planilla_Comando";
            $extension = '.xls';
            $re = filter_var($request->reimbursement ?? false, FILTER_VALIDATE_BOOLEAN) ?'reintegro':'';
            return Excel::download($export, $file_name.$re.$month.$year.$extension);    
    }

}
