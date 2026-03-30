<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationRequest;
use App\Models\EconomicComplement\EcoComProcedure;
use App\Models\EconomicComplement\EcoComState;
use App\Models\EconomicComplement\EcoComStateType;
use App\Models\EconomicComplement\EconomicComplement;
use App\Models\EconomicComplement\EcoComModality;
use App\Models\Notification\NotificationSend;
use App\Models\Affiliate\Hierarchy;
use App\Models\ObservationType;
use App\Models\Admin\Module;
use App\Models\Affiliate\AffiliateToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ExceptionMicroservice;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Affiliate\Affiliate;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\NotificationSendExport;
use App\Models\Notification\NotificationNumber;
use App\Models\Notification\NotificationCarrier;
use App\Models\Loan\Loan;
use Auth;
use App\Helpers\Util;


class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notification/get_semesters",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE SEMESTRES",
     *     operationId="getSemestres",
     *     description="Obtiene el listado de semestres para complemento económico",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_semesters(){
        $semesters = EcoComProcedure::select(['id', 'year', 'semester'])
        ->orderBy('year', 'asc')
        ->get();
        $results = [];
        foreach($semesters as $semester) {
            array_push($results, (object)['id' => $semester->id, 'period' => explode("-", $semester->year)[0]." - ".$semester->semester]);
        }
        return response()->json([
            'semesters' => $results,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_observations/{module_id}",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE OBSERVACIONES",
     *     operationId="getObservaciones",
     *     description="Obtiene el listado de las observaciones de tipo 'AT' para complemento económico",
     *     @OA\Parameter(
     *          name="module_id",
     *          in="path",
     *          description="",
     *          required=true,
     *          @OA\Schema(
     *              type="integer",
     *              format="int64"
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_observations($module_id){
        $observation_types = Module::find($module_id)->observation_types()->where('type', 'AT')->get();
        return response()->json([
            'observations' => $observation_types
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_modalities_payment/{state_type_id}",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE MODALIDADES DE PAGO",
     *     operationId="getModalidadesDePago",
     *     description="Obtiene el listado de las modalidades de pago para complemento económico",
     *     @OA\Parameter(
     *         name="state_type_id",
     *         in="path",
     *         description="Pagado, habilitado y en proceso",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_modalities_payment($eco_com_state_type_id) {
        $modalities_payment = EcoComStateType::find($eco_com_state_type_id)->eco_com_state()->whereIn('id', [24, 25, 29])->get();
        return response()->json([
            'modalities_payment' => $modalities_payment
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_beneficiary_type",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE TIPOS DE BENEFICIARIOS",
     *     operationId="getTiposBeneficiarios",
     *     description="Obtiene el listado de los tipos de beneficiarios para complemento económico",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_beneficiary_type(){
        $types = EcoComModality::join('procedure_modalities', 'eco_com_modalities.procedure_modality_id', '=', 'procedure_modalities.id')
                                ->select('procedure_modalities.id', 'procedure_modalities.name')
                                ->distinct()
                                ->get();
        return response()->json([
            'beneficiary_type' => $types
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_hierarchical_level",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE JERARQUIAS",
     *     operationId="getJerarquias",
     *     description="Obtiene el listado de las jerarquias para complemento económico",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_hierarchical_level(){
        $hierarchies = Hierarchy::select('id', 'name')->where('id', '>', 1)
        ->orderBy('id')
        ->get();
        return response()->json([
            'hierarchies' => $hierarchies
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_actions",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE ACCIONES",
     *     operationId="getAcciones",
     *     description="Obtiene el listado de las acciones permitidas",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get list of cities
     *
     * @param Request $request
     * @return void
     */
    public function get_actions() {
        return response()->json([
            'Acciones' => [
                [
                    'id' => 1,
                    'shortened' => 'Recepción de requisitos',
                    'name' => 'Recepción de requisitos para el pago del complemento económico'
                ],
                [
                    'id' => 2,
                    'shortened' => 'Pago complemento económico',
                    'name' => 'Pago del complemento económico perteneciente al semestre actual'
                ],
                [
                    'id' => 3,
                    'shortened' => 'Observaciones de trámites',
                    'name' => 'Observaciones de trámites de complemento económico'
                ]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notification/get_type_notification",
     *     tags={"NOTIFICACIONES"},
     *     summary="OBTENER EL TIPO DE NOTIFICACIÓN",
     *     operationId="getTyepNotification",
     *     description="Obtiene los tipos de notificaciones (SMS, APP) para el reporte",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     * Get type of notifications
     *
     * @param Request $request
     * @return void
     */
    public function get_type_notification() {
        $type_notification = NotificationCarrier::select('id', 'name')->get();
        $results = [];
        foreach($type_notification as $type) {
            array_push($results, (object)['id' => $type->id, 'name' => $type->name]);
        }
        return response()->json([
            'type_notifications' => $results,
        ]);
    }

    // Microservicio para consumir la ruta del backend node
    public function delegate_shipping($data, $tokens, $ids){
        $res = [];
        try{
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->acceptJson()
                ->post(env('URL_BACKEND_NODE'),[
                    'tokens' => $tokens,
                    'title'  => 'MUSERPOL',
                    'body'   => 'COMUNICADO',
                    'image'  => env('NOTIFICATION_IMAGE', ''),
                    'data'   => $data
                ]);
            if($response->successful()) {
                $delivered = [];                    // Para el estado en la base de datos
                $message   = $response['message'];
                $responses = $message['responses'];

                $i = 0;
                $delivered = array();
                foreach($responses as $check) {
                    $var = $check['success'] ? true : false;
                    $aux = array(
                        'affiliate_id' => $ids[$i],
                        'status' => $var
                    );
                    array_push($delivered, $aux);
                    $i++;
                }
                $res['status']       = true;
                $res['delivered']    = $delivered;
                $res['successCount'] = $message['successCount'];
                $res['failureCount'] = $message['failureCount'];
                $res['message'] = 'Notificación masiva exitosa';
            } else {
                if(count($tokens) == 0) {
                    $res['status']  = false;
                    $res['message'] = "Nada que enviar!";
                } else {
                    $res['status']  = false;
                    $res['message'] = $response['message']['message'];
                }
            }
        }
        catch(\Exception $e) {
            $res['status'] = false;
            $res['message'] = $e->getMessage();
        }
        return $res;
    }

    // Guardar en el registro tabla notification_send
    public function to_register($user_id, $delivered, $message, $subject, $ids, $is_file, $action, $semester = null) {
        $object = ($is_file || $action === 1) ? new Affiliate() : new EconomicComplement();
        $alias = $object->getMorphClass();
        $i = 0;
        foreach($ids as $id) {
            $obj = (object)$message;
            $notification_send = new NotificationSend();
            if(!is_null($semester)) {
                if(!is_null(EconomicComplement::where('eco_com_procedure_id', $semester)->where('affiliate_id', $id)->first())) {
                    if($action === 2 || $action === 3) {
                        $id = EconomicComplement::where('eco_com_procedure_id', $semester)->where('affiliate_id', $id)->first()->id;
                    }
                }
            }
            $notification_send->create([
                'user_id' => $user_id,
                'carrier_id' => 1,
                'sendable_type' => $alias,
                'sendable_id' => $id,
                'send_date' => Carbon::now(),
                'delivered' => $delivered[$i]['status'],
                'message' => json_encode(['data' => $obj]),
                'subject' => $subject,
                'receiver_number' => null,
                'notification_type_id' => $action,
                'sender_number' => null
            ]);
            $i++;
        }
    }

    // Función que crea las tablas temporales necesarias para el manejo de las consultas
    public function create_temporary_tables_payments() {
        DB::transaction(function() {
            DB::statement('drop table if exists tmp_affiliates');
            DB::statement("create temp table tmp_affiliates (
                affiliate_id integer,
                firebase_token varchar,
                economic_complement_id integer,
                last_year date,
                last_semester varchar,
                payment_id integer,
                payment_name varchar,
                modality_id integer,
                modality_name varchar)");
            DB::statement('drop table if exists temporal');
            DB::statement('create temp table temporal as (
                select ts.affiliate_id as affiliate_id, ts.firebase_token as firebase_token, ec.id as economic_complement_id, ecp.year as last_year, ecp.semester as last_semester, ecs.id as payment_id, ecs.name as payment_name, pm.id as modality_id, pm.name as modality_name
                from affiliate_tokens ts
                inner join economic_complements ec
                on ts.affiliate_id = ec.affiliate_id
                inner join eco_com_procedures ecp
                on ec.eco_com_procedure_id = ecp.id
                inner join eco_com_states ecs
                on ec.eco_com_state_id = ecs.id
                inner join eco_com_modalities ecm
                on ec.eco_com_modality_id = ecm.id
                inner join procedure_modalities pm
                on ecm.procedure_modality_id = pm.id
                where ts.api_token is not null
                and ts.firebase_token is not null
                and ecs.id in (24, 25, 29)
                order by ts.affiliate_id, ecp.year desc)');
            $query  = <<<QUERY
            create or replace procedure fill_temp()
            as
            $$
            declare
                    campo RECORD;
                begin
                    for campo in (select affiliate_id
                                from affiliate_tokens
                                where api_token is not null
                                order by affiliate_id)
                    loop
                        insert into tmp_affiliates (affiliate_id, firebase_token, economic_complement_id, last_year, last_semester, payment_id, payment_name, modality_id, modality_name)
                        select ts.affiliate_id, ts.firebase_token, ts.economic_complement_id, ts.last_year, ts.last_semester, ts.payment_id, ts.payment_name, ts.modality_id, ts.modality_name
                        from temporal ts
                        where ts.affiliate_id = campo.affiliate_id
                        offset 0 rows
                        fetch first 1 row only;
                    end loop;
                end;
                $$ LANGUAGE plpgsql;
            QUERY;
            DB::statement($query);
            DB::statement('call fill_temp();');
        });
    }

    // Función que crea la tabla temporal necesaria para las observaciones
    public function create_temporary_table_observation($year, $semester) {
        DB::transaction(function() use ($year, $semester) {
            DB::statement('drop table if exists tmp_observations');
            DB::statement("create temp table tmp_observations as (
                select distinct at.affiliate_id, at.firebase_token, ecp.semester, ecp.year
                from affiliate_tokens at
                inner join affiliate_devices ad
                on at.id = ad.affiliate_token_id
                inner join eco_com_procedures ecp
                on ad.eco_com_procedure_id = ecp.id
                where ecp.year = '$year'
                and ecp.semester = '$semester'
                and at.api_token is not null
                and at.firebase_token is not null
            )");
        });
    }

    // Para la tabla
    public function shippable_list($query, $filters, $request) {
        // Paginado
        $page     = $request->get('page', 1);
        $per_page = $request->get('per_page', 8);
        // consulta
        $base = collect(DB::select($query));
        if($request->has('first') && $request->first) {
            $paginate = new LengthAwarePaginator($base->forPage($page, $per_page)->values(), $base->count(), $per_page, $page);
            return response()->json([
                'error'   => false,
                'message' => 'Listado de personas a notificar',
                'data'    => $paginate,
                'all'     => $base
            ]);
        }
        // filtros
        intval($filters[4]);
        intval($filters[5]);

        $result = $base->filter(function ($value, $key) use ($filters) {
            return stripos($value->last_name, $filters[0])         !== false
                && stripos($value->mothers_last_name, $filters[1]) !== false
                && stripos($value->first_name, $filters[2])        !== false
                && stripos($value->second_name, $filters[3])       !== false
                && stripos($value->identity_card, $filters[4])     !== false
                && stripos($value->affiliate_id, $filters[5])      !== false;
        });

        $all = collect($result);
        $paginate = new LengthAwarePaginator($all->forPage($page, $per_page)->values(), $all->count(), $per_page, $page);

        $body = [
            'error'   => false,
            'message' => 'Listado de personas a notificar',
            'data'    => $paginate
        ];
        return response()->json([
            'error'   => false,
            'message' => 'Listado de personas a notificar',
            'data'    => $paginate
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notification/list_to_notify",
     *     tags={"NOTIFICACIONES"},
     *     summary="LISTADO DE BENEFICIARIOS A NOTIFICAR",
     *     operationId="ListToNotify",
     *     description="Listado de beneficiarios para enviar notificación",
     *     @OA\RequestBody(
     *          description= "Listado de notificaciones",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="action", type="integer",description="Recepción de requerimientos, complemento económico u observaciones (Receipt_of_requirements, economic_complement_payment, observatinos", example="1"),
     *              @OA\Property(property="payment_method", type="integer",description="Método de pago para el complemento económico (Abono en cuenta SIGEP, Ventanilla Banco Unión y a domicilio)",example="24"),
     *              @OA\Property(property="modality", type="integer",description="Modalidad (vejez, viudedad u orfandad)", example="29"),
     *              @OA\Property(property="type_observation", type="integer",description="Tipo de observación", example="2"),
     *              @OA\Property(property="year", type="string",description="Fecha perteneciente del complemento económico",example="2022-01-01"),
     *              @OA\Property(property="semester", type="string",description="Semestre del complemento económico observado",example="Segundo"),
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     *  mass_notification
     *
     * @param Request $request
     * @return void
     */
    public function list_to_notify(Request $request) {

        $validator = Validator::make($request->all(), [
            'action' => [
                'required',
                'numeric',
                function($attribute, $value, $fail) {
                    if(!in_array($value, [1,2,3]))
                    $fail('El valor del campo '.$attribute.' es incorrecto');
                }
            ],
            'payment_method' => [
                'exclude_if:action,1,3',
                'required_if:action,2',
                'required',
                'numeric',
                function($attribute, $value, $fail) {
                    if(!in_array($value, [0,24,25,29]))
                    $fail('El '.$attribute.' (método de pago) es incorrecto');
                }
            ],
            'modality' => [
                'exclude_if:action,3,1',
                'exclude_if:payment_method,0',
                'numeric',
                function($attribute, $value, $fail) {
                    if(!in_array($value, [0, 29, 30, 31]))
                    $fail('El '.$attribute. ' (modalidad) es incorrecto');
                }
            ],
            'type_observation' => 'required_if:action,3|numeric',
            'hierarchies' => 'numeric',
            'semester_id' => 'required_if:action,3|integer'
        ]);

        if($validator->fails()) {
            $keys = $validator->errors()->keys();
            $errors = [];
            foreach($keys as $key) {
                $errors[$key] = $validator->errors()->get($key);
            }
            return response()->json([
                'error' => true,
                'errors' => $errors
            ], 422);
        }

        try {
            $action = $request->action;
            if($action === 1) { // recepción de requisitos
                $query = "select at2.affiliate_id, a.last_name, a.mothers_last_name, a.first_name, a.second_name, a.identity_card
                        from affiliate_tokens at2
                        inner join affiliates a
                        on at2.affiliate_id = a.id
                        where at2.api_token is not null
                        and at2.firebase_token is not null
                        order by at2.affiliate_id asc";
            } else {
                if($action === 2) { // Pago de complemento económico
                    $payment_method = $request->payment_method;
                    $this->create_temporary_tables_payments();
                    if($payment_method == 0) { // A todos los habilitados para pago de complemento económico
                        $query = "select ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                from tmp_affiliates ta
                                inner join eco_com_applicants eca
                                on ta.economic_complement_id = eca.economic_complement_id
                                order by ta.affiliate_id";

                    } else { // Cualquier método de pago
                        if($request->has('modality')){
                            $modality = $request->modality;
                            if($request->has('hierarchies')){
                                $hierarchies = $request->hierarchies;
                                if($hierarchies == 0) {
                                    $query = "select ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                                from tmp_affiliates ta
                                                left join eco_com_applicants eca
                                                on ta.economic_complement_id = eca.economic_complement_id
                                                inner join affiliates a
                                                on ta.affiliate_id = a.id
                                                inner join degrees d
                                                on a.degree_id = d.id
                                                inner join hierarchies h
                                                on d.hierarchy_id = h.id
                                                where payment_id = $payment_method
                                                and ta.modality_id = $modality";
                                } else {
                                    $query = "select ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                                from tmp_affiliates ta
                                                left join eco_com_applicants eca
                                                on ta.economic_complement_id = eca.economic_complement_id
                                                inner join affiliates a
                                                on ta.affiliate_id = a.id
                                                inner join degrees d
                                                on a.degree_id = d.id
                                                inner join hierarchies h
                                                on d.hierarchy_id = h.id
                                                where payment_id = $payment_method
                                                and ta.modality_id = $modality
                                                and h.id = $hierarchies";
                                }
                            } else {
                                if($modality == 0) {
                                    $query = "select distinct ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                        from tmp_affiliates ta
                                        inner join eco_com_applicants eca
                                        on ta.economic_complement_id = eca.economic_complement_id
                                        where payment_id = $payment_method";
                                } else {
                                    $query = "select distinct ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                            from tmp_affiliates ta
                                            inner join eco_com_applicants eca
                                            on ta.economic_complement_id = eca.economic_complement_id
                                            where payment_id = $payment_method
                                            and modality_id = $modality";
                                }
                            }
                        } else {
                            $query = "select ta.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                    from tmp_affiliates ta
                                    inner join eco_com_applicants eca
                                    on ta.economic_complement_id = eca.economic_complement_id
                                    where payment_id = $payment_method";
                        }
                    }
                } elseif($action === 3) { // Observaciones
                    $eco_com_procedure= EcoComProcedure::find($request->semester_id);
                    $year = $eco_com_procedure->year;
                    $semester = $eco_com_procedure->semester;

                    $this->create_temporary_table_observation($year, $semester);
                    $type = $request->type_observation;
                    $query = "select distinct tos.affiliate_id, eca.last_name, eca.mothers_last_name, eca.first_name, eca.second_name, eca.identity_card
                                from tmp_observations tos, economic_complements ec, observables o, observation_types ot, eco_com_applicants eca
                                where tos.affiliate_id = ec.affiliate_id
                                and o.observable_type = 'economic_complements'
                                and o.observable_id = ec.id
                                and ec.id = eca.economic_complement_id
                                and o.observation_type_id = $type
                                and o.enabled = true
                                and ot.type = 'AT'
                                and ot.description is not null
                                and ot.description <> ''";
                }
            }
            $filters = array();
            $filters[0] = $request->last_name ?? "";
            $filters[1] = $request->mothers_last_name ?? "";
            $filters[2] = $request->first_name ?? "";
            $filters[3] = $request->second_name ?? "";
            $filters[4] = $request->identity_card ?? "";
            $filters[5] = $request->affiliate_id ?? "";

            return $this->shippable_list($query, $filters, $request);
        } catch(\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/notification/send_mass_notification",
     *     tags={"NOTIFICACIONES"},
     *     summary="ENVÍO DE NOTIFICACIONES MASIVAS",
     *     operationId="sendMassNotification",
     *     description="Envío de notificaciones masivas",
     *     @OA\RequestBody(
     *          description= "Envío de notificaciones masivas",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="title", type="string",description="Título de la notificación", example="Comunicado Complemento económico"),
     *              @OA\Property(property="message", type="string",description="Mensaje de la notificación",example="Señor affiliado {{full_name}} se apertura la recepción de requisitos para el trámite de pago de complemento económico"),
     *              @OA\Property(property="sends", type="array", example={{"affiliate_id": "5964", "send": true}}, @OA\Items(@OA\Property(property="affiliate_id", type="integer",example=""), @OA\Property(property="send", type="boolean", example=""))),
     *              @OA\Property(property="image", type="string",description="Url de la imagen como cuerpo de la notificación", example="http://google.com/image"),
     *              @OA\Property(property="attached", type="string",description="Adjunto del mensaje",example="Comunicado"),
     *              @OA\Property(property="user_id", type="integer",description="Id del usuario que envía la notificación",example="1"),
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     *  mass_notification
     *
     * @param Request $request
     * @return void
     */
    public function send_mass_notification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'   => 'required|string',
            'message' => 'required|string',
            'sends'   => 'required|array|min:1',
            // 'action'  => 'required|integer',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error'  => true,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            ini_set('max_execution_time', -1);
            $title_notification = $request['title'];
            $message            = $request['message'];
            $image              = $request->image ?? "";
            $sends              = array_filter($request['sends'], fn($s) => $s['send'] ?? false); 
            // $amount             = count($sends);
            $is_file            = $request['is_file'] ?? false;
            $action             = $request['action'];
            $semester           = $request['semester_id'] ?? null;
            $user_id            = $request['user_id'];
            $subject            = $request['attached'];
            $publication_date   = Carbon::now()->format('Y-m-d');
            $data = [
                'title' => $title_notification,
                'image' => $image,
                'PublicationDate' => $publication_date,
                'text' => $message
            ];
            $i = 1; 
            $shipping_indicator = 0; 
            $groups = array_chunk($sends, 500, true);
            foreach($groups as $group) {
                $tokens = [];
                $ids    = [];
                foreach($group as $person) {
                    $token = AffiliateToken::where('affiliate_id', $person['affiliate_id'])->value('firebase_token');

                    if ($token) {
                        $tokens[] = $token;
                        $ids[]    = $person['affiliate_id'];
                    } else {
                        logger("No se encontró token para el afiliado ID {$person['affiliate_id']}");
                    }
                }
                $tokens = array_values(array_unique($tokens));
                $ids    = array_values(array_unique($ids));
                if (empty($tokens)) {
                    logger("Nada que enviar en el lote {$i}.");
                    $i++;
                    sleep(1);
                    continue;
                }

                $res = $this->delegate_shipping($data, $tokens, $ids);
                if ($res['status'] && count($res['delivered']) > 0) {
                    $this->to_register($user_id, $res['delivered'], $data, $subject, $ids, $is_file, $action, $semester);
                    logger("ENVÍO LOTE NRO {$i} | Éxitos: {$res['successCount']} | Fallos: {$res['failureCount']}");
                    $shipping_indicator++;
                } else {
                    logger("Fallo en el lote {$i}: " . $res['message']);
                }
                sleep(1);
                $i++;
            }
            if($shipping_indicator > 0) {
                return response()->json([
                    'error'   => false,
                    'message' => $res['message'],
                    'data'    => []
                ]);
            } else {
                response()->json([
                    'error'   => true,
                    'message' => $res['message'],
                    'data'    => []
                ], 404);
            }

        } catch(\Exception $e) {
            logger($e);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/notification/send_notifications",
     *     tags={"NOTIFICACIONES"},
     *     summary="ENVÍO DE NOTIFICACIONES MASIVAS MEDIANTE UN ARCHIVO",
     *     operationId="sendNotifications",
     *     description="Envío de notificaciones masivas mediante un archivo excel",
     *     @OA\RequestBody(
     *          description= "Parámetros requeridos",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *              @OA\Property(property="title", type="string",description="Título de la notificación", example="Comunicado Complemento económico"),
     *              @OA\Property(property="message", type="string",description="Mensaje de la notificación",example="Señor affiliado {{full_name}} se apertura la recepción de requisitos para el trámite de pago de complemento económico"),
     *              @OA\Property(property="attached", type="string",description="Adjunto del mensaje",example=""),
     *              @OA\Property(property="user_id", type="integer",description="Id del usuario que envía la notificación",example="1"),
     *              @OA\Property(property="file", type="file", format="binary", description="Archivo que solo contiene NUP's de afiliados")
     *              )
     *          ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     *  mass_notification
     *
     * @param Request $request
     * @return void
     */
    public function send_notifications(Request $request)
    {
        $rows = Excel::toArray([], $request->file);
        $rows = $rows[0];
        $affiliates = [];
        foreach($rows as $row) {
            $send = false;
	    logger($row[0]);
            if($row[0] != null && ctype_digit(trim($row[0]))) {
                if(Affiliate::find($row[0]) != null && Affiliate::find($row[0])->affiliate_token != null && Affiliate::find($row[0])->affiliate_token->firebase_token != null )
                {
                    $send = true;
                }
            }
            array_push($affiliates, ['affiliate_id' => $row[0], 'send' => $send]);
        }
        if(count($affiliates) != 0) {
            $request->merge(['sends' => $affiliates]);
            return $this->send_mass_notification($request);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Archivo vacío o NUP\'s de afiliados inválidos',
                'data' => []
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/notification/report",
     *     tags={"NOTIFICACIONES"},
     *     summary="OBTENER REPORTE NOTIFICACIONES",
     *     operationId="report",
     *     description="Ruta para obtener el reporte de notificaciones",
     *     @OA\RequestBody(
     *          description= "Reporte",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="type", type="integer",description="Tipo de notificación: Todos = 0, notificaciones App = 1, SMS = 2", example="2"),
     *              @OA\Property(property="start_date", type="date",description="Fecha inicio del reporte",example="2022-12-12"),
     *              @OA\Property(property="end_date", type="date",description="Fecha final del reporte", example="2022-12-12"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\MediaType(mediaType="multipart/form-data")
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     *
     *  mass_notification
     *
     * @param Request $request
     * @return void
     */
    public function get_report(Request $request) {
        ini_set('max_execution_time', '300000');
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $media_type = $request->type; // todos, SMS, notificaciones App,
        switch($media_type) {
            case '1':
                $sms = false;
                $app = true;
                break;
            case '2':
                $app = false;
                $sms = true;
                break;
            default:
                $app = false;
                $sms = false;
                break;
        }
        $iteration = NotificationSend::join('users', 'notification_sends.user_id', '=', 'users.id')
            ->when($sms, function ($query) {
                $query->where('carrier_id', 2);
            })
            ->when($app, function ($query) {
                $query->where('carrier_id', 1);
            })
            ->where('send_date', '>=', $start_date)
            ->where('send_date', '<=', $end_date)
            ->select('users.username', 'notification_sends.delivered', 'notification_sends.carrier_id',
            'notification_sends.sendable_type', 'notification_sends.sendable_id',
            'notification_sends.send_date', 'notification_sends.message','notification_sends.created_at',
            'notification_sends.receiver_number')->get();
        $result = collect();

        foreach($iteration as $it) {
            $temp = collect();
            $temp->push($it->username);
            if(!is_null(NotificationCarrier::find(intval($it->carrier_id)))) {
                $name = NotificationCarrier::find(intval($it->carrier_id))->name;
                if($name == 'Notifications') $name = 'Notificación APP';
            } else $name = null;
            $temp->push($name);
            $flag = true;
            switch($it->sendable_type) {
                case 'economic_complements':
                    if(!is_null(EconomicComplement::find(intval($it->sendable_id)))) {
                        $type = 'Complemento Económico';
                        $eco_com = EconomicComplement::find(intval($it->sendable_id));
                        $nup = $eco_com->affiliate_id;
                        $code = $eco_com->code;
                    } else $flag = false;
                    break;
                case 'loans':
                    if(!is_null(Loan::find(intval($it->sendable_id)))) {
                        $type = 'Préstamo';
                        $loan = Loan::find(intval($it->sendable_id));
                        $nup = $loan->affiliate_id;
                        $code = $loan->code;
                    } else $flag = false;
                    break;
                case 'affiliates':
                    if(!is_null(Affiliate::find(intval($it->sendable_id)))) {
                        $type = 'Afiliado';
                        $nup = $it->sendable_id;
                        $code = null;
                    } else $flag = false;
                    break;
            }
            if($flag){
                $temp->push($type);
                $temp->push($code);
                $temp->push($nup);
                $temp->push($it->created_at);
                if($it->sendable_type == 'economic_complements') {
                    $temp->push(json_decode($it->message)->data->text);
                } else {
                    $temp->push(json_decode($it->message)->data);
                }
                $temp->push($it->receiver_number);
                $result->push($temp);
            }
        }
        return Excel::download(new NotificationSendExport($result, $media_type), 'notificaciones.xlsx');
    }

    public function get_report_notifications(Request $request) {
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $records = NotificationSend::select('economic_complements.affiliate_id','eco_com_applicants.identity_card', 'eco_com_applicants.first_name', 'eco_com_applicants.last_name', 'eco_com_applicants.mothers_last_name', 'economic_complements.code', 'notification_sends.send_date',
            DB::raw("CASE WHEN notification_sends.delivered = true THEN 'si' ELSE 'no' END AS delivered"))
            ->join('economic_complements', 'notification_sends.sendable_id', '=', 'economic_complements.id')
            ->join('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
            ->where('notification_sends.sendable_type', 'economic_complements')
            ->where('send_date', '>=', $start_date)
            ->where('send_date', '<=', $end_date)
            ->distinct()
            ->get();

        $media_type = 0;
        return Excel::download(new NotificationSendExport($records, $media_type), 'notificaciones.xlsx');
    }
}
