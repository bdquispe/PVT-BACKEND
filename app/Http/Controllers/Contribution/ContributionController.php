<?php

namespace App\Http\Controllers\Contribution;

use App\Exports\ArchivoPrimarioExport;
use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\AffiliateRecord;
use App\Models\Affiliate\Degree;
use App\Models\Contribution\Contribution;
use App\Models\Contribution\Reimbursement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Auth;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;

class ContributionController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/contribution/search_active_affiliate_contribution",
     *     tags={"CONTRIBUCION"},
     *     summary="Filtrado y listado de contribuciones - Sector Activo",
     *     operationId="getContributionActive",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Página a mostrar",
     *         example=1,
     *         required=false,
     *       ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Por Página",
     *         example=10,
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="sortDesc",
     *         in="query",
     *         description="Vector de orden descendente(0) o ascendente(1)",
     *         example=1,
     *         required=false,
     *     ),
     *    @OA\Parameter(
     *         name="affiliate_id",
     *         in="query",
     *         description="Id del Afiliado",
     *         required=false,
     *     ),
     *    @OA\Parameter(
     *         name="con_re",
     *         in="query",
     *         description="Filtro por Tipo de Contribución",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filtro por Año",
     *         required=false,
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Filtro por Mes",
     *         required=false,
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
     * Get list of contributions active
     *
     * @param Request $request
     * @return void
     */

    public function SearchContributionActive(Request $request)
    {
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contributions,affiliate_id',
            'con_re' => 'nullable|in:CON,RE'
        ]);

        $year = request('year') ?? '';
        $month = request('month') ?? '';
        $breakdown = request('breakdown') ?? '';
        $con_re = request('con_re') ?? '';

        $order = request('sortDesc') ?? '';
        if ($order != '') {
            if ($order) {
                $order_year = 'asc';
            }
            if (!$order) {
                $order_year = 'desc';
            }
        } else {
            $order_year = 'desc';
        }

        $conditions = [];
        if ($year != '') {
            array_push($conditions, array('month_year', 'like', "%{$year}%-%"));
        }
        if ($month != '') {
            array_push($conditions, array('month_year', 'like', "%-%{$month}%-%"));
        }
        if ($breakdown != '') {
            array_push($conditions, array('breakdowns.name', 'ilike', "%{$breakdown}%"));
        }

        $per_page = $request->per_page ?? 10;

        $affiliate = Affiliate::find($request->affiliate_id);

        if (strtoupper($con_re) == 'RE') {
            $reimbursements = $affiliate->reimbursements()->selectRaw(
                "
                reimbursements.id as con_re_id,
                affiliate_id,
                month_year,
                extract(month from month_year) as month,
                extract(year from month_year) as year,
                degree_id,
                unit_id,
                base_wage,
                seniority_bonus,
                study_bonus,
                position_bonus,
                border_bonus,
                east_bonus,
                public_security_bonus,
                gain,
                quotable,
                retirement_fund,
                mortuary_quota,
                total,
                'RE' as con_re,
                type,
                breakdowns.name as breakdown_name,
                units.code as unit_code,
                degrees.shortened as degree_shortened"
            )->leftjoin("breakdowns", "breakdowns.id", "=", "reimbursements.breakdown_id")
            ->leftjoin("units", "units.id", "=", "reimbursements.unit_id")
            ->leftjoin("degrees", "degrees.id", "=", "reimbursements.degree_id")
                ->where($conditions)
                ->orderBy('month_year', $order_year)
                ->paginate($per_page);
            foreach ($reimbursements as $reimbursement)
                $reimbursement->can_deleted = false;
            return $reimbursements;
        } elseif (strtoupper($con_re) == 'CON') {
            $contributions = $affiliate->contributions()->selectRaw(
                "
                    contributions.id as con_re_id,
                affiliate_id,
                month_year,
                extract(month from month_year) as month,
                extract(year from month_year) as year,
                degree_id,
                unit_id,
                base_wage,
                seniority_bonus,
                study_bonus,
                position_bonus,
                border_bonus,
                east_bonus,
                public_security_bonus,
                gain,
                quotable,
                retirement_fund,
                mortuary_quota,
                total,
                'CON' as con_re,
                type,
                breakdowns.id as breakdown_id,
                breakdowns.name as breakdown_name,
                units.code as unit_code,
                degrees.shortened as degree_shortened"
            )->leftjoin("breakdowns", "breakdowns.id", "=", "contributions.breakdown_id")
            ->leftjoin("units", "units.id", "=", "contributions.unit_id")
            ->leftjoin("degrees", "degrees.id", "=", "contributions.degree_id")
                ->where($conditions)
                ->orderBy('month_year', $order_year)
                ->paginate($per_page);

            foreach ($contributions as $contribution) {
                $c = Contribution::find($contribution->con_re_id);
                $contribution->can_deleted = $c->can_deleted();
            }
            return $contributions;
        }
        if ($con_re == '') {
            $reimbursements = $affiliate->reimbursements()->selectRaw(
                "
                reimbursements.id as con_re_id,
                affiliate_id,
                month_year,
                extract(month from month_year) as month,
                extract(year from month_year) as year,
                null,
                null,
                base_wage,
                seniority_bonus,
                study_bonus,
                position_bonus,
                border_bonus,
                east_bonus,
                public_security_bonus,
                gain,
                quotable,
                retirement_fund,
                mortuary_quota,
                total,
                'RE' as con_re,
                type,
                breakdowns.id as breakdown_id,
                breakdowns.name as breakdown_name,
                units.code as unit_code,
                degrees.shortened as degree_shortened"
            )->leftjoin("breakdowns", "breakdowns.id", "=", "reimbursements.breakdown_id")
            ->leftjoin("units", "units.id", "=", "reimbursements.unit_id")
            ->leftjoin("degrees", "degrees.id", "=", "reimbursements.degree_id")
                ->where($conditions)
                ->orderBy('month_year', $order_year);

            $contributions = $affiliate->contributions()->selectRaw(
                "
                contributions.id as con_re_id,
                affiliate_id,
                month_year,
                extract(month from month_year) as month,
                extract(year from month_year) as year,
                degree_id,
                unit_id,
                base_wage,
                seniority_bonus,
                study_bonus,
                position_bonus,
                border_bonus,
                east_bonus,
                public_security_bonus,
                gain,
                quotable,
                retirement_fund,
                mortuary_quota,
                total,
                'CON' as con_re,
                type,
                breakdowns.id as breakdown_id,
                breakdowns.name as breakdown_name,
                units.code as unit_code,
                degrees.shortened as degree_shortened"
            )->leftjoin("breakdowns", "breakdowns.id", "=", "contributions.breakdown_id")
            ->leftjoin("units", "units.id", "=", "contributions.unit_id")
            ->leftjoin("degrees", "degrees.id", "=", "contributions.degree_id")
                ->union($reimbursements)
                ->where($conditions)
                ->orderBy('month_year', $order_year)
                ->paginate($per_page);
            foreach ($contributions as $contribution) {
                if ($contribution->con_re == 'CON') {
                    $c = Contribution::find($contribution->con_re_id);
                    $contribution->can_deleted = $c->can_deleted();
                } else {
                    $contribution->can_deleted = false;
                }
            }
            return $contributions;
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * @OA\Post(
     *      path="/api/contribution/active_affiliate_contribution",
     *      tags={"CONTRIBUCION"},
     *      summary="CONTRIBUCIONES DEL AFILIADO - SECTOR ACTIVO",
     *      operationId="getContributions",
     *      description="contribuciones del afiliado",
     *      @OA\RequestBody(
     *          description= "affiliate_id",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="affiliate_id", type="integer",description="affiliate_id",example=123)
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

    public function show(Request $request)
    {
        $affiliate = Affiliate::find($request->affiliate_id);
        $hasContributions = $affiliate->contributions;
        if (sizeof($hasContributions) > 0) {
            $year_min = $affiliate->minimum_year_contribution_active;
            $year_max = $affiliate->maximum_year_contribution_active;
            $contribution_total = 0;
            $all_contributions = collect();

            $reimbursements = Reimbursement::whereAffiliateId($request->affiliate_id)
                ->orderBy('month_year', 'asc')
                ->get();
            $months = DB::table('months')->get();
            for ($i = $year_max; $i >= $year_min; $i--) {
                $full_total = 0;

                $contributions = collect();

                $contributions_actives = Contribution::whereAffiliateId($request->affiliate_id)
                    ->orderBy('month_year', 'asc')
                    ->whereYear('month_year', $i)
                    ->get();

                foreach ($months as $month) {
                    $mes = (string)$month->id;
                    $detail = collect();
                    foreach ($contributions_actives as $contributions_active) {
                        $contribution_total = $contributions_active->total;
                        $reimbursement_total = 0;
                        $full_total = $contributions_active->total;

                        foreach ($reimbursements as $reimbursement) {
                            if ($contributions_active->month_year == $reimbursement->month_year) {
                                $reimbursement_total = $reimbursement->total;
                                $full_total = $contribution_total + $reimbursement_total;
                            }
                        }
                        $m = ltrim(Carbon::parse($contributions_active->month_year)->format('m'), "0");

                        if ($m == $mes) {
                            $detail->push([
                                'id' => $contributions_active->id,
                                'month_year' => $contributions_active->month_year,
                                'quotable' => Util::money_format($contributions_active->quotable),
                                'retirement_fund' => Util::money_format($contributions_active->retirement_fund),
                                'mortuary_quota' => Util::money_format($contributions_active->mortuary_quota),
                                'reimbursement_total' => Util::money_format($reimbursement_total),
                                'total' => Util::money_format($contribution_total),
                                'contribution_total' => Util::money_format($full_total),
                                'type' => $contributions_active->contributionable_type
                            ]);
                        }
                    }
                    $contributions->push([
                        'month' => $month->name,
                        'detail' => (object)$detail->first()
                    ]);
                }
                $all_contributions->push([
                    'year' => $i . "",
                    'contributions' => $contributions,
                ]);
            }

            return response()->json([
                'hasContributions' => true,
                'payload' => [
                    'first_name' => $affiliate->first_name,
                    'second_name' => $affiliate->second_name,
                    'last_name' => $affiliate->last_name,
                    'mothers_last_name' => $affiliate->mothers_last_name,
                    'surname_husband' => $affiliate->surname_husband,
                    'identity_card' => $affiliate->identity_card,
                    'all_contributions' => $all_contributions
                ],
            ]);
        } else {
            return response()->json([
                'hasContributions' => false,
                'payload' => []
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/contribution/print_contributions_active/{affiliate_id}",
     *     tags={"CONTRIBUCION"},
     *     summary="Impresión de certificado de contribuciones - Sector Activo",
     *     operationId="getCertificateContributionActive",
     *      @OA\Parameter(
     *         name="affiliate_id",
     *         in="path",
     *         description="Id del afiliado",
     *         example=210,
     *
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *       ),
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
     * Print certificate of contributions active
     *
     * @param Request $request
     * @return void
     */

    public function printCertificationContributionActive(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contributions,affiliate_id',
        ]);

        $this->getCertificateActive($affiliate_id);

        $affiliate = Affiliate::find($affiliate_id);
        $user = Auth::user();
        $degree = Degree::find($affiliate->degree_id);
        $contributions = Contribution::whereAffiliateId($affiliate_id)
            ->where('total', '>', 0)
            ->orderBy('month_year', 'asc')
            ->get();
        $reimbursements = Reimbursement::whereAffiliateId($affiliate_id)
            ->orderBy('month_year', 'asc')
            ->get();
        $num = 0;
        $value = false;
        $data = [
            'header' => [
                'direction' => 'DIRECCIÓN DE BENEFICIOS ECONÓMICOS',
                'unity' => 'UNIDAD DE OTORGACIÓN DE FONDO DE RETIRO
                            POLICIAL, CUOTA MORTUORIA Y AUXILIO MORTUORIO',
                'table' => [
                    ['Usuario', $user->username],
                    ['Fecha', Carbon::now('GMT-4')->format('d/m/Y')],
                    ['Hora', Carbon::now('GMT-4')->format('H:i')],
                ]
            ],
            'num' => $num,
            'degree' => $degree,
            'affiliate' => $affiliate,
            'user' => $user,
            'value' => $value,
            'contributions' => $contributions,
            'reimbursements' => $reimbursements
        ];

        $file_name = 'aportes_act_' . $affiliate_id . '.pdf';

        $pdf = PDF::loadView('contribution.print.certification_contribution_active', $data);
        $pdf->set_paper('letter', 'portrait');
        $pdf->output();

        return Util::pdf_to_base64($pdf, $file_name);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.co
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * @OA\delete(
     *     path="/api/contribution/contribution/{contribution}",
     *     tags={"CONTRIBUCION"},
     *     summary="Eliminación de aporte Sector activo",
     *     operationId="deleteContribution",
     *     @OA\Parameter(
     *         description="ID del aporte del sector activo",
     *         in="path",
     *         name="contribution",
     *         required=true,
     *         @OA\Schema(
     *             format="int64",
     *             type="integer"
     *         )
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
     * Delete list of contributions passive.
     *
     * @param Request $request
     * @return void
     */
    public function destroy(Contribution $contribution)
    {
        try {
            $error = true;
            $message = 'No es permitido la eliminación del registro';
            if ($contribution->can_deleted()) {
                Util::save_record_affiliate($contribution->affiliate,' eliminó el aporte como activo del periodo '.$contribution->month_year.'.');
                $contribution->delete();
                $error = false;
                $message = 'Eliminado exitosamente';
            }
            return response()->json([
                'error' => $error,
                'message' => $message,
                'data' => $contribution
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'data' => (object)[]
            ]);
        }
    }

    public function destroyMassive(Request $request){
      
        try{            
            $request->validate([
                'affiliate_id' => 'required|integer',
                'date_start' => 'required|date',
                'date_end' => 'required|date|after_or_equal:date_start',
            ]);   

            $contributions = Contribution::where('affiliate_id', $request->affiliate_id)
            ->whereBetween('month_year', [$request->date_start, $request->date_end])
            ->get();

            if($contributions->isEmpty()) {
                return response()->json([
                    'error'=> true,
                    'message' => 'No se eNcontraron registros en el rango '. $request->date_start ." - ". $request->date_end,
                    'data' => []
                ]);                
            }
            $eliminados = [];
            foreach ($contributions as $contribution) {       
                Util::save_record_affiliate($contribution->affiliate, 'eliminado el aporte como activo del periodo '. $contribution->month_year .'.');
                $contribution->delete();
                $eliminados[] = $contribution->id;
            }
            
            if(count($eliminados) == 0){
                return response()->json([
                    'error'=> true,
                    'message' => 'No se pudo eliminar ningun registro',
                    'data' => []
                ]);                
            }
            return response()->json([
                'error' => false,
                'message' => 'Registros eliminados exitosamente: ' . count($eliminados),
                'data' => $contributions
            ]);
        }

        catch(\Exception $e){
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    public static function getCertificateActive($affiliate_id)
    {
        $action = 'imprimió certificado de aportes - activo';
        $user = Auth::user();
        $message = 'El usuario ' . $user->username . ' ';
        $affiliate_record = new AffiliateRecord();
        $affiliate_record->user_id = $user->id;
        $affiliate_record->affiliate_id = $affiliate_id;
        $affiliate_record->message = $message . $action;

        $data = AffiliateRecord::whereDate('created_at', now())
            ->where('affiliate_id', $affiliate_id)
            ->where('message', 'not ilike', '%pasivo%')
            ->get();

        if (sizeof($data) == 0) {
            $affiliate_record->save();
        }

        return response()->json([
            'message' => 'Datos registrados con éxito',
            'payload' => [
                'affiliate' => $affiliate_record
            ],
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/contribution/get_report_certificate",
     *      tags={"CONTRIBUCION"},
     *      summary="GENERA REPORTE DE CERTIFICACIONES EMITIDAS",
     *      operationId="report_certificate",
     *      description="Genera reporte de las certificaciones de aportes emitidas",
     *      @OA\RequestBody(
     *          description= "Reporte de certificaciones",
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="start_date", type="date",description="Fecha inicio del reporte", example="2023-02-05"),
     *              @OA\Property(property="end_date", type="date",description="Fecha final del reporte", example="2023-02-14")
     *         ),
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
    public function get_report_certificate(Request $request)
    {
        $date = date('Y-m-d');

        if ($request->start_date == NULL || $request->end_date == NULL) {
            $start_date = $date;
            $end_date = $date;
        } else {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
        }

        $list = AffiliateRecord::leftjoin('users', 'affiliate_records_pvt.user_id', '=', 'users.id')
            ->leftjoin('view_affiliates', 'affiliate_records_pvt.affiliate_id', '=', 'view_affiliates.id_affiliate')
            ->where('affiliate_records_pvt.message', 'ilike', '%certificado de aportes%')
            ->whereBetween(DB::raw('DATE(affiliate_records_pvt.created_at)'), [$start_date, $end_date])
            ->select(
                'users.username as username',
                'affiliate_records_pvt.affiliate_id as affiliate_id',
                'view_affiliates.full_name_affiliate as full_name_affiliate',
                'affiliate_records_pvt.message as message',
                'affiliate_records_pvt.created_at as date'
            )->orderBy('affiliate_records_pvt.created_at', 'asc')->get();

        $data_header = array(array(
            "NRO", "USUARIO", "NUP", "AFILIADO", "ACCIÓN", "FECHA GENERACIÓN"
        ));
        $i = 1;
        foreach ($list as $row) {
            array_push($data_header, array(
                $row->number = $i,
                $row->username, $row->affiliate_id,
                $row->full_name_affiliate, $row->message, $row->date
            ));
            $i++;
        }

        $export = new ArchivoPrimarioExport($data_header);
        $file_name = "reporte_certificaciones";
        $extension = '.xls';
        return Excel::download($export, $file_name . $extension);
    }
}
