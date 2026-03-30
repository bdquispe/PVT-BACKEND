<?php

namespace App\Http\Controllers\Contribution;

use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\AffiliateRecord;
use App\Models\Affiliate\Degree;
use App\Models\Contribution\ContributionPassive;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class ContributionPassiveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     *      path="/api/contribution/passive_affiliate_contribution",
     *      tags={"CONTRIBUCION"},
     *      summary="CONTRIBUCIONES DEL AFILIADO - SECTOR PASIVO",
     *      operationId="getContributionsPassive",
     *      description="contribuciones del afiliado - sector pasivo",
     *      @OA\RequestBody(
     *          description= "affiliate_id",
     *          required=true,
     *          @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *             @OA\Property(property="affiliate_id", type="integer",description="affiliate_id",example=33)
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
     *
     * @param Request $request
     * @return void
     */

    public function show(Request $request)
    {
        $affiliate = Affiliate::find($request->affiliate_id);
        $hasContributionPassives = $affiliate->contribution_passives;
        if (sizeof($hasContributionPassives) > 0) {
            $year_min = $affiliate->minimum_year_contribution_passive;
            $year_max = $affiliate->maximum_year_contribution_passive;

            $all_contributions = collect();
            $months = DB::table('months')->get();

            for ($i = $year_max; $i >= $year_min; $i--) {
                $contributions = collect();
                $contribution_passives = ContributionPassive::whereAffiliateId($request->affiliate_id)
                    ->whereYear('month_year', $i)
                    ->orderBy('month_year', 'asc')
                    ->get();

                foreach ($months as $month) {
                    $mes = (string)$month->id;
                    $detail = collect();
                    foreach ($contribution_passives as $contributions_passive) {
                        $m = ltrim(Carbon::parse($contributions_passive->month_year)->format('m'), "0");
                        if ($m == $mes) {
                            $detail->push(
                                $contributions_passive
                            );
                        }
                    }
                    $contributions->push([
                        'month' => $month->name,
                        'detail' => (object)$detail->first()
                    ]);
                }
                $all_contributions->push([
                    'year' => (string)$i,
                    'contributions' => $contributions
                ]);
            }

            return response()->json([
                'hasContributionPassives' => true,
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
                'hasContributionPassives' => false,
                'payload' => []
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/contribution/search_passive_affiliate_contribution",
     *     tags={"CONTRIBUCION"},
     *     summary="Filtrado y listado de contribuciones - Sector Pasivo",
     *     operationId="getContributionPassive",
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
     *    @OA\Parameter(
     *         name="contribution_state_id",
     *         in="query",
     *         description="id del estado del aporte",
     *         required=false,
     *     ),
     *    @OA\Parameter(
     *         name="affiliate_rent_class",
     *         in="query",
     *         description="tipo de aporte VEJEZ, VIUDEDAD, VEJEZ/VIUDEDAD",
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
     * Get list of contributions passive.
     *
     * @param Request $request
     * @return void
     */

    public function SearchContributionPassive(Request $request)
    {

        $request->validate([
            'affiliate_id' => 'required|integer|exists:contribution_passives,affiliate_id',
            'contribution_state_id' => 'nullable|integer|exists:contribution_states,id',
            'affiliate_rent_class' => 'nullable|in:VEJEZ,VIUDEDAD,VEJEZ/VIUDEDAD'
        ]);
        $year = request('year') ?? '';
        $month = request('month') ?? '';
        $contributionable_type = request('contributionable_type') ?? '';
        $contribution_state_id = request('contribution_state_id') ?? '';
        $affiliate_rent_class = request('affiliate_rent_class') ?? '';
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
        if ($contributionable_type != '') {
            array_push($conditions, array('contributionable_type', 'like', "%{$contributionable_type}%"));
        }
        if ($contribution_state_id != '') {
            array_push($conditions, array('contribution_state_id', "{$contribution_state_id}"));
        }
        if ($affiliate_rent_class != '') {
            array_push($conditions, array('affiliate_rent_class', 'ilike', "%{$affiliate_rent_class}%"));
        }
        $per_page = $request->per_page ?? 10;
        $contributions_passives = ContributionPassive::whereAffiliateId($request->affiliate_id)->where($conditions)->orderBy('month_year', $order_year)->paginate($per_page);

        foreach ($contributions_passives as $contributions_passive) {
            $year = Carbon::parse($contributions_passive->month_year)->format('Y');
            $month = ltrim(Carbon::parse($contributions_passive->month_year)->format('m'), "0");
            if ($contributions_passive->contributionable_type == "discount_type_economic_complement") {
                $contributions_passive->contributionable_type_name = "Complemento Economico";
            } else if ($contributions_passive->contributionable_type == "payroll_senasirs") {
                $contributions_passive->contributionable_type_name = "Senasir";
            } else if ($contributions_passive->contributionable_type == "payroll_filemakers") {
                $contributions_passive->contributionable_type_name = "Filemaker";
            } else {
                    $contributions_passive->contributionable_type_name = "";
            }            
            $contributions_passive->year = $year;
            $contributions_passive->month = $month;
            $contributions_passive->contribution_state;
            $contributions_passive->can_deleted = $contributions_passive->can_deleted();
        }
        return $contributions_passives;
    }

    /**
     * @OA\Get(   
     *     path="/api/contribution/print_contributions_passive/{affiliate_id}",
     *     tags={"CONTRIBUCION"},
     *     summary="Impresión de certificado de contribuciones - Sector Pasivo",
     *     operationId="getCertificateContributionPassive",
     *      @OA\Parameter(
     *         name="affiliate_id",
     *         in="path",
     *         description="Id del afiliado",
     *         example=210,
     *
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format = "int64"
     *         )
     *       ),
     *      @OA\Parameter(
     *         name="affiliate_rent_class",
     *         in="query",
     *         description="Tipo de renta (VEJEZ/VIUDEDAD)",
     *         example="VEJEZ",
     *
     *         required=true,
     *         @OA\Schema(
     *             type="string"
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
     * Print certificate of contributions passive
     *
     * @param Request $request
     * @return void
     */
    public function printCertificationContributionPassive(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contribution_passives,affiliate_id',
            'affiliate_rent_class' => 'required'
        ]);

        $this->getCertificatePassive($affiliate_id, $request->affiliate_rent_class);

        $affiliate = Affiliate::find($affiliate_id);
        $user = Auth::user();
        $degree = Degree::find($affiliate->degree_id);
        $text = '';
        $contributions = collect();

        $value = false;

        $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
            ->when($request->affiliate_rent_class == 'VEJEZ', function ($query) use ($request) {
                $query->where('affiliate_rent_class', 'ilike', $request->affiliate_rent_class);
            }, function ($query) use ($request) {
                $query->where('affiliate_rent_class', 'ilike', '%' . $request->affiliate_rent_class . '%');
            })
            ->where('contribution_state_id', 2)
            ->orderBy('month_year', 'asc')
            ->get();

        if ($affiliate->dead && $affiliate->spouse != null) {
            $value = true;
        }

        foreach ($contributions_passives as $contributions_passive) {
            $year = Carbon::parse($contributions_passive->month_year)->format('Y');
            $month = Carbon::parse($contributions_passive->month_year)->format('m');
            if ($contributions_passive->affiliate_rent_class == 'VEJEZ') {
                $rent_class = 'Titular';
            } elseif ($contributions_passive->affiliate_rent_class == 'VIUDEDAD') {
                $rent_class = 'Viuda';
            } else {
                $rent_class = 'Titular/Viuda';
            }
            if ($contributions_passive->contributionable_type == 'discount_type_economic_complement') {
                $modality = $contributions_passive->contributionable->economic_complement->eco_com_procedure;
                $modality_year = Carbon::parse($modality->year)->format('Y');
                $text = "C.E." . $modality->semester . " Semestre " . $modality_year;
            } else {
                $text = $contributions_passive->contributionable_type == 'payroll_senasirs' ? 'Descuento SENASIR' : 'Descuento No Especificado';
            }
            $contributions->push([
                'id' => $contributions_passive->id,
                'month_year' => $contributions_passive->month_year,
                'month' => $month,
                'year' => $year,
                'rent_class' => $rent_class,
                'description' => $text,
                'quotable' => $contributions_passive->quotable,
                'total' => $contributions_passive->total,
                'type' => $contributions_passive->contributionable_type
            ]);
        }

        $num = 0;
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
            'text' => $text,
            'contributions' => $contributions
        ];

        $file_name = 'aportes_pas_' . $affiliate_id . '.pdf';
        $pdf = PDF::loadView('contribution.print.certification_contribution_eco_com', $data);
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
     * Update the specified resource in storage.
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
     *     path="/api/contribution/contributions_passive/{contributionPassive}",
     *     tags={"CONTRIBUCION"},
     *     summary="Eliminación de aporte Sector pasivo",
     *     operationId="deleteContributionPassive",
     *     @OA\Parameter(
     *         description="ID del aporte del sector pasivo",
     *         in="query",
     *         name="contributionPassive",
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
    public function destroy(ContributionPassive $contributionPassive)
    {
        try {
            $error = true;
            $message = 'No es permitido la eliminación del registro';
            if ($contributionPassive->can_deleted()) {
                Util::save_record_affiliate($contributionPassive->affiliate, ' eliminó el aporte como pasivo del periodo ' . $contributionPassive->month_year . '.');
                $contributionPassive->delete();
                $error = false;
                $message = 'Eliminado exitosamente';
            }
            return response()->json([
                'error' => $error,
                'message' => $message,
                'data' => $contributionPassive
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

            $contributions = ContributionPassive::where('affiliate_id', $request->affiliate_id)
            ->whereBetween('month_year', [$request->date_start, $request->date_end])->where('contributionable_type', '!=', 'discount_type_economic_complement')
            ->get();

            if($contributions->isEmpty()) {
                return response()->json([
                    'error'=> true,
                    'message' => 'No se encontraron registros en el rango '. $request->date_start ." - ". $request->date_end. " o correponden a descuento por Complemento Económico",
                    'data' => []
                ]);                
            }
            $eliminados = [];
            foreach ($contributions as $contribution) {       
                Util::save_record_affiliate($contribution->affiliate, 'eliminado el aporte como pasivo del periodo '. $contribution->month_year .'.');
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

    public static function getCertificatePassive($affiliate_id, $affiliate_rent_class)
    {
        $action = 'imprimió certificado de aportes - pasivo ' . $affiliate_rent_class;
        $user = Auth::user();
        $message = 'El usuario ' . $user->username . ' ' . $action;

        if (!self::isMessageRegisteredToday($affiliate_id, $message)) {
            if (!self::isRentClassRegisteredToday($affiliate_id, $affiliate_rent_class)) {
                $affiliate_record = new AffiliateRecord();
                $affiliate_record->user_id = $user->id;
                $affiliate_record->affiliate_id = $affiliate_id;
                $affiliate_record->message = $message;
                $affiliate_record->save();

                return response()->json([
                    'message' => 'Datos registrados con éxito',
                    'payload' => [
                        'affiliate' => $affiliate_record
                    ],
                ]);
            }
        }

        return response()->json([
            'message' => 'Datos no registrados',
            'payload' => []
        ]);
    }

    private static function isMessageRegisteredToday($affiliate_id, $message)
    {
        return AffiliateRecord::whereDate('created_at', now())
            ->where('affiliate_id', $affiliate_id)
            ->where('message', $message)
            ->exists();
    }

    private static function isRentClassRegisteredToday($affiliate_id, $affiliate_rent_class)
    {
        return AffiliateRecord::whereDate('created_at', now())
            ->where('affiliate_id', $affiliate_id)
            ->where('message', 'like', '%' . $affiliate_rent_class . '%')
            ->exists();
    }
}
