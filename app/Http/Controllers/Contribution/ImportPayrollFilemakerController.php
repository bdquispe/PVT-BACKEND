<?php

namespace App\Http\Controllers\Contribution;

use App\Http\Controllers\Controller;
use App\Models\Contribution\PayrollFilemaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Helpers\Util;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArchivoPrimarioExport;
use Auth;

class ImportPayrollFilemakerController extends Controller
{
    
        /**
     * @OA\Post(
     *      path="/api/contribution/upload_copy_payroll_filemaker",
     *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
     *      summary="PASO 1 COPIADO DE DATOS PLANILLA FILEMAKER",
     *      operationId="upload_copy_payroll_filemaker",
     *      description="Copiado de datos del archivo de planillas filemaker a la tabla payroll_copy_filemakers",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="file", type="file", description="file required", example="file"),
     *             @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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

    public function upload_copy_payroll_filemaker(request $request)
    {
        $request->validate([
            'file' => 'required',
            'date_import' => 'required|date',
        ]);

        $date_import = Carbon::parse($request->date_import)->format('Y-m-d');
        $extencion = strtolower($request->file->getClientOriginalExtension());
        $file_name_entry = $request->file->getClientOriginalName();

        $route = '';
        $route_file_name = '';
        DB::beginTransaction();
        try{

            $username = env('FTP_USERNAME');
            $password = env('FTP_PASSWORD');
            $successfully = false;
            if($extencion == "csv"){

                $rollback_period = "delete from payroll_copy_filemakers where state = 'unrealized' and created_at::date ='".$date_import."';";
                $rollback_period = DB::connection('db_aux')->select($rollback_period);
                $file_name = "filemaker".'.'.$extencion;
                    if($file_name_entry == $file_name){

                        $base_path = 'planillas/planilla_filemaker/'.Carbon::now()->toDateString();
                        $file_path = Storage::disk('ftp')->putFileAs($base_path,$request->file,$file_name);
                        $base_path ='ftp://'.env('FTP_HOST').env('FTP_ROOT').$file_path;                 

                        $drop = "drop table if exists payroll_copy_filemaker_tmp";
                        $drop = DB::connection('db_aux')->select($drop);

                        $temporary_payroll = "create temporary table payroll_copy_filemaker_tmp(
                                                a_o integer,
                                                mes integer,                                                
                                                carnet varchar,
                                                matricula varchar,
                                                pat varchar,
                                                mat varchar,
                                                nom varchar,
                                                nom2 varchar,
                                                ap_casada varchar,
                                                grado varchar,
                                                cor_afi integer,
                                                fecha_pago date,
                                                recibo varchar,
                                                monto decimal(13,2),
                                                observacion varchar,
                                                affiliate_id_frcam integer,
                                                tipo_aportante varchar)";
                        $temporary_payroll = DB::connection('db_aux')->select($temporary_payroll);
             
                        $copy = "copy payroll_copy_filemaker_tmp(a_o,mes, carnet, matricula, pat, mat, nom, nom2, ap_casada, grado, cor_afi, fecha_pago, recibo, monto, observacion, affiliate_id_frcam, tipo_aportante)
                                FROM PROGRAM 'wget -q -O - $@  --user=$username --password=$password $base_path'
                                WITH DELIMITER ':' CSV header;";
                        $copy = DB::connection('db_aux')->select($copy);

                        //******validación de datos****************/

                        $verify_number_records = "select count(*) from payroll_copy_filemaker_tmp";
                        $verify_number_records = DB::connection('db_aux')->select($verify_number_records);

                        $verify_data = "select count(*) from payroll_copy_filemaker_tmp where a_o is null or mes is null;";
                        $verify_data = DB::connection('db_aux')->select($verify_data);

                        if($verify_data[0]->count > 0){
                            return response()->json([
                                'message' => 'Error en el copiado de datos',
                                'payload' => [
                                    'successfully' => false,
                                    'error' => 'Existen datos incorrectos en la(s) columnas de mes o año.',
                                    'route' => $route,
                                    'route_file_name' => $route_file_name
                                ],
                            ]);
                        }

                        //****************************************/
                        
                        $insert = "INSERT INTO payroll_copy_filemakers(a_o, mes, carnet, matricula, pat ,mat, nom, nom2, ap_casada, grado, cor_afi, fecha_pago, recibo, monto, observacion, affiliate_id_frcam, tipo_aportante, created_at, updated_at)
                        SELECT a_o::INTEGER, mes::INTEGER, carnet, matricula, pat, mat, nom, nom2, ap_casada, grado, cor_afi::INTEGER, fecha_pago::DATE, recibo, monto, observacion, affiliate_id_frcam::INTEGER, tipo_aportante, current_timestamp, current_timestamp 
                        FROM payroll_copy_filemaker_tmp";
                        $insert = DB::connection('db_aux')->select($insert);
                        
                        $drop = "drop table if exists payroll_copy_filemaker_tmp";
                        $drop = DB::connection('db_aux')->select($drop);

                        $data_count = $this->data_count_payroll_filemaker($date_import);                       

                        //*******Limpieza de duplicados en carnet, mes, a_o, monto************//
                        $data_cleaning = "WITH duplicados AS (
                            SELECT id,
                                    ROW_NUMBER() OVER (
                                        PARTITION BY carnet, a_o, mes, monto
                                        ORDER BY id
                                    ) AS rn
                                FROM payroll_copy_filemakers WHERE created_at::date = '".$date_import."'
                            )
                            UPDATE payroll_copy_filemakers
                            SET deleted_at = NOW()
                            WHERE created_at::date = '".$date_import."' AND 
                                id IN (
                                    SELECT id FROM duplicados WHERE rn > 1
                                );";
                        $data_cleaning = DB::connection('db_aux')->select($data_cleaning);

                        //******validación de datos****************/
                        $verify_data = "update payroll_copy_filemakers pcf set error_message = concat(error_message,' - ','Los valores de los apellidos son NULOS ') from (select id from payroll_copy_filemakers where a_o is null and mes is null and pat is null and mat is null  and deleted_at is null and created_at::date = '".$date_import."') as subquery where pcf.id = subquery.id;";
                        $verify_data = DB::connection('db_aux')->select($verify_data);

                        $verify_data = "update payroll_copy_filemakers pcf set error_message = concat(error_message,' - ','El valor del primer nombre es NULO ') from (select id from payroll_copy_filemakers where  nom is null and deleted_at is null and created_at::date = '".$date_import."') as subquery where pcf.id = subquery.id;";
                        $verify_data = DB::connection('db_aux')->select($verify_data);

                        $verify_data = "update payroll_copy_filemakers pcf set error_message = concat(error_message,' - ','El numero de carnet es duplicado en el mismo periodo') from (select carnet, a_o, mes from payroll_copy_filemakers where deleted_at is null and created_at::date = '".$date_import."' group by carnet, a_o, mes having count(*) > 1) as subquery where pcf.carnet = subquery.carnet and pcf.a_o = subquery.a_o and pcf.mes = subquery.mes and deleted_at is null;";
                        $verify_data = DB::connection('db_aux')->select($verify_data);

                        //****************************************/
                        $verify_data = "select count(id) from payroll_copy_filemakers pcf where created_at::date = '".$date_import."' and error_message is not null and deleted_at is null;";
                        $verify_data = DB::connection('db_aux')->select($verify_data);

                        if($verify_data[0]->count > 0) {
                            $route = '/contribution/download_error_data_filemaker';
                            $route_file_name = 'datos_observados_archivo.xls';
                            return response()->json([
                                'message' => 'Excel',
                                'payload' => [
                                    'successfully' => false,
                                    'error' => 'Existen datos en el archivo que son incorrectos y no seran considerados en la importación, favor revisar.',
                                    'route' => $route,
                                    'route_file_name' => $route_file_name
                                ],
                            ]);
                        }
                        //****************************************/
                        DB::commit();

                        if($data_count['num_total_data_copy'] > 0){
                            $message = "Realizado con éxito";
                            $successfully = true;
                        }

                        return response()->json([
                            'message' => $message,
                            'payload' => [
                                'successfully' => $successfully,
                                'data_count' => $data_count,
                                'route' => $route,
                                'route_file_name' => $route_file_name
                            ],
                        ]);
                    } else {
                           return response()->json([
                            'message' => 'Error en el copiado del archivo',
                            'payload' => [
                                'successfully' => $successfully,
                                'error' => 'El nombre del archivo no coincide con en nombre requerido',
                                'route' => $route,
                                'route_file_name' => $route_file_name
                            ],
                        ]);
                    }
            } else {
                    return response()->json([
                        'message' => 'Error en el copiado del archivo',
                        'payload' => [
                            'successfully' => $successfully,
                            'error' => 'El archivo no es un archivo CSV',
                            'route' => $route,
                            'route_file_name' => $route_file_name
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
                   'route' => $route,
                   'route_file_name' => $route_file_name
               ],
           ]);
        }
    }

    public function data_count_payroll_filemaker($date_import){
        $data_count['num_total_data_copy'] = 0;
        $data_count['num_data_not_considered'] = 0;
        $data_count['num_data_unrelated'] = 0;
        $data_count['num_data_considered'] = 0;
        $data_count['num_data_validated'] = 0;
        $data_count['num_data_not_validated'] = 0;

        //---TOTAL DE DATOS DEL ARCHIVO
        $query_total_data = "SELECT count(id) FROM payroll_copy_filemakers where created_at::date = '$date_import';";
        $query_total_data = DB::connection('db_aux')->select($query_total_data);
        $data_count['num_total_data_copy'] = $query_total_data[0]->count;

        //---NUMERO DE DATOS NO CONSIDERADOS duplicados de afilaidos y aportes
        $query_data_not_considered = "SELECT count(id) FROM payroll_copy_filemakers where created_at::date = '$date_import 'and (error_message is not null or deleted_at is not null);";
        $query_data_not_considered = DB::connection('db_aux')->select($query_data_not_considered);
        $data_count['num_data_not_considered'] = $query_data_not_considered[0]->count;

        //---NUMERO DE DATOS NO RELACIONADOS 
        $query_data_unrelated= "SELECT count(id) FROM payroll_copy_filemakers where created_at::date = '$date_import' and error_message is null and deleted_at is null and criteria = '7-no-identificado';";
        $query_data_unrelated= DB::connection('db_aux')->select($query_data_unrelated);
        $data_count['num_data_unrelated'] = $query_data_unrelated[0]->count;

        //---NUMERO DE DATOS CONSIDERADOS 
        $query_data_considered = "SELECT count(id) FROM payroll_copy_filemakers where created_at::date = '$date_import' and error_message is null and deleted_at is null;";
        $query_data_considered = DB::connection('db_aux')->select($query_data_considered);
        $data_count['num_data_considered'] = $query_data_considered[0]->count;

        //---NUMERO DE DATOS VALIDADOS
        $data_count['num_data_validated'] = PayrollFilemaker::data_period($date_import)['count_data'];
        //  //---NUMERO DE DATOS NO VALIDADOS
        // $data_count['num_data_not_validated'] = $data_count['num_data_considered'] - $data_count['num_data_validated'];

        return  $data_count;
    }

         /**
      * @OA\Post(
      *      path="/api/contribution/download_error_data_filemaker",
      *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
      *      summary="DESCARGA EL ARCHIVO, CON EL LISTADO DE AFILIADOS QUE TENGAN OBSERVACIONES EN EL ARCHIVO",
      *      operationId="download_error_data_filemaker",
      *      description="Descarga el archivo con el listado de afiliados con CI duplicado, primer nombre nulo, apellido paterno y materno en nulo ",
      *      @OA\RequestBody(
      *          description= "Provide auth credentials",
      *          required=true,
      *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
*                 @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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
    public function download_error_data_filemaker(Request $request){

        $request->validate([
            'date_import' => 'required|date',
        ]);
        
        $date_import = Carbon::parse($request->date_import)->format('Y-m-d');

        $data_header=array(array("AÑO","MES","CARNET","APELLIDO PATERNO","APELLIDO MATERNO","PRIMER NOMBRE","SEGUNDO NOMBRE","APORTE","OBSERVACIÓN"));

        $data_payroll_copy_filemaker = "select a_o,mes,carnet,pat,mat,nom,nom2,monto,error_message from payroll_copy_filemakers pcf where created_at::date = '".$date_import."' and (error_message is not null or deleted_at is not null) order by carnet";
        $data_payroll_copy_filemaker = DB::connection('db_aux')->select($data_payroll_copy_filemaker);
            foreach ($data_payroll_copy_filemaker as $row){
                array_push($data_header, array($row->a_o,$row->mes,$row->carnet,$row->pat,
                $row->mat,$row->nom,$row->nom2,$row->monto,$row->error_message));
            }
            $export = new ArchivoPrimarioExport($data_header);
            $file_name = "observacion-planilla-filemaker";
            $extension = '.xls';
            return Excel::download($export, $file_name."_".$extension);
    }

         /**
     * @OA\Post(
     *      path="/api/contribution/validation_affiliate_filemaker",
     *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
     *      summary="PASO 2 VALIDACION AFILIADOS Y APORTES",
     *      operationId="validation_affiliate_filemaker",
     *      description="validacion de Afiliados y aportres de la planilla filemaker",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=false,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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

    public function validation_affiliate_filemaker(Request $request){

        $request->validate([
            'date_import' => 'required|date',
        ]);
        $date_import = Carbon::parse($request->date_import)->format('Y-m-d');
        try{
            DB::beginTransaction();
            $message = "No hay datos por validar";
            $successfully =false;
            $data_count['num_total_data_copy'] = 0;
            $data_count['count_data_automatic_link'] = 0;
            $data_count['count_data_unidentified'] = 0;
            $data_count['count_data_error'] = 0;
            $data_count['num_total_data_payroll'] = 0;
            $data_count['num_total_data_contribution'] = 0;
            $route = '';
            $route_file_name = '';

            $connection_db_aux = Util::connection_db_aux();
            $query = "select search_affiliate_filemaker('$connection_db_aux', '$date_import');";
            $data_validated = DB::select($query);
            $num_total_data_copy = $this->data_count_payroll_filemaker($date_import);
            $count_data_automatic_link = "select count(id) from payroll_copy_filemakers pcf where criteria in ('1-CI-sPN-sPA-sSA', '2-partCI-sPN-sPA', '3-sCI-MAT-PN-PA', '4-MAT-APCAS', '5-cCI-sPN-sPA','6-partcCI-sPN-sPA') and created_at::date = '".$date_import."'";
            $count_data_automatic_link = DB::connection('db_aux')->select($count_data_automatic_link);
            $count_data_unidentified = "select count(id) from payroll_copy_filemakers pcf where criteria in ('7-no-identificado') and created_at::date = '".$date_import."'";
            $count_data_unidentified = DB::connection('db_aux')->select($count_data_unidentified);
            $count_data_error = "select count(id) from payroll_copy_filemakers pcf where (error_message is not null or deleted_at is not null) and created_at::date = '".$date_import."'";
            $count_data_error = DB::connection('db_aux')->select($count_data_error);
            $data_count['num_total_data_copy'] = $num_total_data_copy['num_total_data_copy'];
            $data_count['count_data_automatic_link'] = $count_data_automatic_link[0]->count;
            $data_count['count_data_unidentified'] = $count_data_unidentified[0]->count;
            $data_count['count_data_error'] = $count_data_error[0]->count;

            $validated_contribution = $this->validation_contribution_filemaker($date_import);
             //return $validated_contribution;

            if($num_total_data_copy['num_total_data_copy'] <= 0){
                $successfully =false;
                $message = 'no existen datos';
            }elseif($count_data_unidentified[0]->count > 0){
                $successfully =false;
                $message = 'Excel';
                $route = '/contribution/download_data_revision';
                $route_file_name = 'observados_para_revision.xls';
            }elseif($count_data_unidentified[0]->count == 0 && $count_data_error[0]->count > 0){
                $valid_contribution =  "select count(id) from payroll_copy_filemakers pcf  where state like 'accomplished' and error_message is not null and created_at::date = '".$date_import."';";
                $valid_contribution = DB::connection('db_aux')->select($valid_contribution);
                if($valid_contribution[0]->count == 0){
                    $successfully =true;
                    $message = 'Excel';
                    $route = '/contribution/download_data_revision';
                    $route_file_name = 'afiliados_para creacion.xls';
                }else{
                    $successfully =false;
                    $message = 'Excel';
                    $route = '/contribution/download_error_data_archive';
                    $route_file_name = 'datos_aportes_observados.xls';
                }
            }elseif($count_data_unidentified[0]->count == 0 && $count_data_error[0]->count == 0){
                $successfully =true;
                $message = 'Realizado con Exito.';
            }else{
                $successfully =false;
                $message = 'Ops Ocurrio algo inesperado.';
            }
            return response()->json([
                'message' => $message,
                'payload' => [
                    'successfully' => $successfully,
                    'data_count' => $data_count,
                    'route' => $route,
                    'route_file_name' => $route_file_name
                ],
            ]);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
            'message' => 'Error en la busqueda de datos de afiliados.',
            'payload' => [
                'successfully' => false,
                'error' => $e->getMessage(),
            ],
            ]);
        }
    }
    
    /**
     * @OA\Post(
     *      path="/api/contribution/copy_affiliate_id_filemaker",
     *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
     *      summary="PASO 2.1 COPIA DE NUPS NO IDENTIFICADOS POR CRITERIO CON LOS IDENTIFICADOS POR FRCAM",
     *      operationId="copy_affiliate_id",
     *      description="copia de affiliate_id_frcam a affiliate_id filemaker",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=false,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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

    //metodo para copiar los affiliate_id_frcam a affiliate_id
    public function copy_affiliate_id_frcam_to_affiliate_id(request $request){
        $request->validate([
            'date_import' => 'required|date',
        ]);
        $date_import = Carbon::parse($request->date_import)->format('Y-m-d');
        $query = "UPDATE payroll_copy_filemakers pcf
                  SET affiliate_id = pcf.affiliate_id_frcam,
                   criteria = '8-affiliate_id_frcam',
                   observacion = split_part(observacion,'|',1),
                   tipo_aportante = split_part(observacion,'|',2)
                  WHERE pcf.affiliate_id IS NULL AND pcf.affiliate_id_frcam IS NOT NULL AND created_at::date = '".$date_import."';";//criterio '7-no-identificado'
        DB::connection('db_aux')->select($query);
        $data_count = $this->data_count_payroll_filemaker($date_import); 
        return response()->json([
            'payload' => [
                'data_count' => $data_count
            ],
        ]);
    }

    //método para verificar si existe montos con diferentes contribuciones
    public function validation_contribution_filemaker($date_import){
        $different_contribution = false;

        $connection_db_aux = Util::connection_db_aux();
        //1. Reemplaza los casos que tengan aportes iguales registrados por el de filemaker
        //2. Reemplaza los valores que contengan cero en aporte aunque esten clasificados
        $sql_dblink = "
            SELECT id, affiliate_id, a_o, mes, monto, created_at
            FROM payroll_copy_filemakers
            WHERE created_at::date = '$date_import'
        ";
        
        $payroll_filermaker = DB::select("
            SELECT pcf.id, cp.affiliate_id, pcf.monto, cp.total, cp.contribution_type_mortuary_id
            FROM contribution_passives cp
            JOIN dblink('$connection_db_aux', $$ $sql_dblink $$)
            AS pcf(id INT, affiliate_id INT, a_o INT, mes INT, monto NUMERIC(13,2), created_at date)
            ON cp.affiliate_id = pcf.affiliate_id
            WHERE EXTRACT(YEAR FROM cp.month_year) = pcf.a_o
            AND EXTRACT(MONTH FROM cp.month_year) = pcf.mes
            AND cp.total <> pcf.monto
            AND cp.total > 0
            AND pcf.created_at::date = '$date_import';
        ");
    

          foreach($payroll_filermaker as $update_payroll) {
            $messages = [];
             if ($update_payroll->total != $update_payroll->monto) {
                $messages[] = "La contribución anterior es: $update_payroll->total difiere de la planilla $update_payroll->monto";
            }
            //  if (!is_null($update_payroll->contribution_type_mortuary_id)) {
            //     $messages[] = "tramite clasificado como $update_payroll->contribution_type_mortuary_id";
            // }
             if (!empty($messages)) {
                $error_message = implode(' - ', $messages);
                $update_query = "
                    UPDATE payroll_copy_filemakers pf 
                    SET error_message = CONCAT(COALESCE(error_message, ''), ' - ', '$error_message') 
                    WHERE pf.id = $update_payroll->id and created_at::date = '$date_import;
                ";
                $update_query = DB::connection('db_aux')->select($update_query);
                // DB::connection('db_aux')->statement($update_query);
                $different_contribution = true;
            }
        }
          if($different_contribution == true){
            return false;
        }else{
            return true;
        }
    }
    

         /**
     * @OA\Post(
     *      path="/api/contribution/import_payroll_filemaker",
     *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
     *      summary="PASO 3 VALIDACION DE DATOS APORTES",
     *      operationId="validation_contribution_filemaker",
     *      description="validacion de datos de aportes de payroll_copy_filemakers a la tabla payroll_filemakers",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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

    public function import_payroll_filemaker(Request $request){

        $request->validate([
            'date_import' => 'required|date',
        ]);
        $date_import = Carbon::parse($request->date_import)->format('Y-m-d');

        try{
                DB::beginTransaction();
                $message = "No hay datos";
                $successfully =false;
                $connection_db_aux = Util::connection_db_aux();
    
                //conteo de  affiliate_id is null distito del criterio 7-no-identificado
                $count_data = "SELECT count(id) FROM payroll_copy_filemakers where error_message is null and deleted_at is null and state = 'accomplished' and affiliate_id is not null and criteria!='7-no-identificado' and created_at::date = '".$date_import."';";
                $count_data = DB::connection('db_aux')->select($count_data);
                if($count_data[0]->count > 0){
                    $count_data_validated = "SELECT count(id) FROM payroll_copy_filemakers where state ='validated' and created_at::date = '".$date_import."';";
                    $count_data_validated = DB::connection('db_aux')->select($count_data_validated);

                    if($count_data_validated[0]->count == 0 || $count_data[0]->count > 0){
    
                        $query = "select registration_payroll_filemakers('$connection_db_aux', '$date_import');";
                        $data_validated = DB::select($query);
    
                            if($data_validated){
                                $message = "Realizado con exito";
                                $successfully = true;
                                $data_payroll_copy_filemaker = "SELECT * from  payroll_copy_filemakers  where state ='validated' and created_at::date = '".$date_import."';";
                                $data_payroll_copy_filemaker = DB::connection('db_aux')->select($data_payroll_copy_filemaker);
                                if(count($data_payroll_copy_filemaker)> 0){
                                    $message = "Excel";                            
                                }
                            }
                        DB::commit();
                        $data_count = $this->data_count_payroll_filemaker($date_import);
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
                            'error' => 'Error el primer paso no esta concluido o se concluyo el 3 paso.'
                        ],
                    ]);
                }
    
            }catch(Exception $e){
                DB::rollBack();
                return response()->json([
                'message' => 'Ocurrio un error.',
                'payload' => [
                    'successfully' => false,
                    'error' => $e->getMessage(),
                ],
                ]);
            }
        }

    /**
     * @OA\Post(
     *      path="/api/contribution/import_contribution_filemaker",
     *      tags={"IMPORTACION-PLANILLA-FILEMAKER"},
     *      summary="PASO 4 IMPORTACIÓN DE CONTRIBUCIONES FILEMAKER",
     *      operationId="import_contribution_filemaker",
     *      description="Importación de aportes de filemaker a la tabla contribution_passsives",
     *      @OA\RequestBody(
     *          description= "Provide auth credentials",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="date_import", type="string",description="fecha importacion required",example= "2025-07-08")
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
    public function import_contribution_filemaker(Request $request){
        $request->validate([
            'date_import' => 'required|date',
        ]);

        try {
            DB::beginTransaction();
            $userId = Auth::id();
            $date_import = Carbon::parse($request->date_import)->format('Y-m-d');
            $message = 'No existen datos de la planilla.';
            $success = false;
    
            // Verifica si ya se realizó una importación
            $existingContributions = DB::table('contribution_passives')
                ->where('contributionable_type', 'payroll_filemakers')
                ->whereDate('created_at', '=', $date_import)
                ->count();
    
            if ($existingContributions > 0) {
                return response()->json([
                    'message' => 'Error: ya se realizó la importación de datos.',
                    'payload' => [
                        'successfully' => false,
                        'num_total_data_contribution' => $existingContributions,
                    ],
                ]);
            }
    
            // Verifica si hay datos en payroll_filemakers
            $payrollCount = DB::table('payroll_filemakers')->whereDate('created_at', '=', $date_import)->count();
    
            if ($payrollCount > 0) {
                DB::statement("SELECT import_contribution_filemaker($userId, '$date_import')");
    
                DB::commit(); // Confirma transacción
    
                $message = '¡Importación realizada con éxito!';
                $success = true;
    
                $totalContributions = DB::table('contribution_passives')
                    ->where('contributionable_type', 'payroll_filemakers')
                    ->whereDate('created_at', '=', $date_import )
                    ->count();
            } else {
                $totalContributions = 0;
            }
    
            return response()->json([
                'message' => $message,
                'payload' => [
                    'successfully' => $success,
                    'num_total_data_contribution' => $totalContributions,
                ],
            ]);
    
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al realizar la importación.',
                'payload' => [
                    'successfully' => false,
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
