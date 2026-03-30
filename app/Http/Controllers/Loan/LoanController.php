<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\Admin\Module;
use App\Models\Admin\Role;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\Spouse;
use App\Models\Loan\Loan;
use App\Models\Loan\LoanBorrower;
use App\Models\Loan\LoanPayment;
use App\Models\Loan\LoanState;
use App\Models\Procedure\ProcedureModality;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
class LoanController extends Controller
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
    public function print_plan(Request $request, Loan $loan)
    {
        if($loan->disbursement_date){
            $procedure_modality = $loan->modality;
            $file_title = implode('_', ['PLAN','DE','PAGOS', $procedure_modality->shortened, $loan->code,Carbon::now()->format('m/d')]);
            $is_dead = false;
            if($loan->borrower->first()->type == 'spouses')
                $is_dead = true;
            $data = [
                'header' => [
                    'direction' => 'DIRECCIÓN DE ESTRATEGIAS SOCIALES E INVERSIONES',
                    'unity' => 'UNIDAD DE INVERSIÓN EN PRÉSTAMOS',
                    'table' => [
                        ['Tipo', $loan->modality->procedure_type->second_name],
                        ['Modalidad', $loan->modality->shortened],
                        ['Fecha', Carbon::now()->format('d/m/Y')],
                        ['Hora', Carbon::now()->format('H:i')],
                    ]
                ],
                'title' => 'PLAN DE PAGOS',
                'loan' => $loan,
                'lender' => $is_dead ? $loan->affiliate->spouse : $loan->affiliate,
                'is_dead'=> $is_dead,
                'file_title'=>$file_title
            ];
            $file_name = implode('_', ['plan', $procedure_modality->shortened, $loan->code]) . '.pdf';
            $pdf=PDF::loadView('loan/payment_plan', $data);
            return $pdf->stream();
        }else{
            return "Prestamo no desembolsado";
        }
    }

    public function print_plan_v2(Request $request, Loan $loan)
    {
        if($loan->disbursement_date){
            $procedure_modality = $loan->modality;
            $file_title = implode('_', ['PLAN','DE','PAGOS', $procedure_modality->shortened, $loan->code,Carbon::now()->format('m/d')]);
            $is_dead = false;
            if($loan->borrower->first()->type == 'spouses')
                $is_dead = true;
            $data = [
                'header' => [
                    'direction' => 'DIRECCIÓN DE ESTRATEGIAS SOCIALES E INVERSIONES',
                    'unity' => 'UNIDAD DE INVERSIÓN EN PRÉSTAMOS',
                    'table' => [
                        ['Tipo', $loan->modality->procedure_type->second_name],
                        ['Modalidad', $loan->modality->shortened],
                        ['Fecha', Carbon::now()->format('d/m/Y')],
                        ['Hora', Carbon::now()->format('H:i')],
                    ]
                ],
                'title' => 'PLAN DE PAGOS',
                'loan' => $loan,
                'lender' => $is_dead ? $loan->affiliate->spouse : $loan->affiliate,
                'is_dead'=> $is_dead,
                'file_title'=>$file_title
            ];
            $file_name = implode('_', ['plan', $procedure_modality->shortened, $loan->code]) . '.pdf';
            $pdf = PDF::loadView('loan/payment_plan', $data);

            return [
                'filename' => $file_name,
                'content' => base64_encode($pdf->output()),
            ];
        }else{
            return "Prestamo no desembolsado";
        }
    }
    public function print_kardex(Request $request, Loan $loan, $standalone = true)
    {
        if($loan->disbursement_date){
            $procedure_modality = $loan->modality;
            $is_dead = false;
            if($loan->borrower->first()->type == 'spouses'){
                $is_dead = true;
            }
                $file_title = $procedure_modality->shortened;
                $data = [
                    'header' => [
                        'direction' => 'DIRECCIÓN DE ESTRATEGIAS SOCIALES E INVERSIONES',
                        'unity' => 'UNIDAD DE INVERSIÓN EN PRÉSTAMOS',
                        'table' => [
                            ['Tipo', $loan->modality->procedure_type->second_name],
                            ['Modalidad', $loan->modality->shortened],
                            ['Fecha', Carbon::now()->format('d/m/Y')],
                            ['Hora', Carbon::now()->format('H:i')],
                        ]
                    ],
                    'title' => 'KARDEX DE PAGOS',
                    'loan' => $loan,
                    'lender' => $is_dead ? $loan->affiliate->spouse : $loan->affiliate,
                    'file_title' => $file_title,
                    'is_dead' => $is_dead
                ];
                $pdf=PDF::loadView('loan.payment_kardex', $data);
                return $pdf->setPaper('a4', 'portrait')->stream();
            }else{
                return "prestamo no desembolsado";
            }
    }

    public function print_kardex_v2(Request $request, Loan $loan, $standalone = true)
    {
        if($loan->disbursement_date){
            $procedure_modality = $loan->modality;
            $is_dead = false;
            if($loan->borrower->first()->type == 'spouses'){
                $is_dead = true;
            }
                $file_title = $procedure_modality->shortened;
                $data = [
                    'header' => [
                        'direction' => 'DIRECCIÓN DE ESTRATEGIAS SOCIALES E INVERSIONES',
                        'unity' => 'UNIDAD DE INVERSIÓN EN PRÉSTAMOS',
                        'table' => [
                            ['Tipo', $loan->modality->procedure_type->second_name],
                            ['Modalidad', $loan->modality->shortened],
                            ['Fecha', Carbon::now()->format('d/m/Y')],
                            ['Hora', Carbon::now()->format('H:i')],
                        ]
                    ],
                    'title' => 'KARDEX DE PAGOS',
                    'loan' => $loan,
                    'lender' => $is_dead ? $loan->affiliate->spouse : $loan->affiliate,
                    'file_title' => $file_title,
                    'is_dead' => $is_dead
                ];
                $pdf=PDF::loadView('loan.payment_kardex', $data);
                return [
                    'filename' => 'KARDEX DE PAGOS.pdf',
                    'content' => base64_encode($pdf->output()),
                ];
            }else{
                return "prestamo no desembolsado";
            }
    }
     /**
     * @OA\Get(
     *     path="/api/app/get_information_loan/{id_affiliate}",
     *     tags={"OFICINA VIRTUAL"},
     *     summary="LISTADO DE PRESTAMOS DE UN AFILIADO",
     *     operationId="get_information_loan",
     * @OA\Parameter(
     *         name="id_affiliate",
     *         in="path",
     *         description="",
     *         example=1,
     *
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format = "int64"
     *         )
     *       ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *         type="object"
     *         )
     *     ),
     * )
     *
     * Get status of virtual office.
     *
     * @param Request $request
     * @return void
     */

    public static function get_workflow(Loan $loan)
    {
        $areas=[];
        foreach($loan->modality->workflow->get_sequence() as $sequence){
            array_push($areas,array(
                "display_name"=> $sequence->current_state->name,
                "state"=> ($loan->current_state->id == $sequence->current_state->id) ? true : false
                )
            );
        }
        return $areas;
    }

    public static function  get_percentaje_loan(Loan $loan){
        $amount=$loan->amount_approved;
        $balance=$loan->balance;
        $percentage=(100*($amount-$balance))/$amount;
        return $percentage;
    }
    public function get_information_loan(Request $request, $idAffiliate)
    {
        $request['affiliate_id'] = $idAffiliate;
        $hasLoans = DB::table('loans')->where('affiliate_id',$request->id_affiliate)->exists();
        if ($hasLoans) {
            $loans = Loan::where([['affiliate_id', '=',$request->id_affiliate]])->whereIn('state_id',[1,3,4])->get();
        $current=[];
        $inProcess=[];
        $liquidated=[];
        foreach ($loans as $loan ) {
            switch ($loan->state_id) {
                case 1:
                $state = $loan->state;
                $procedure = $loan->modality;
                $type = $loan->modality->procedure_type->name;
                $wf_state = $loan->current_state;
                $flow=$this->get_workflow($loan);
                $loan->state_name = $state->name;
                $loan->procedure_modality_name = $procedure->name;
                array_push($inProcess,array(
                    'code' => $loan->code,
                    'procedure_modality_name' => $procedure->name,
                    'procedure_type_name' => $type,
                    'location' => $wf_state->name,
                    'validated' => $loan->validated,
                    'state_name' => $loan->state_name,
                    'flow'=> $flow
                )
                );
                    break;
                case 3:
                    array_push($current,array(
                        "id"=> $loan->id,
                        "code"=> $loan->code,
                        "procedure_modality" => $loan->modality->name,
                        "request_date"=> $loan->disbursement_date,
                        "amount_requested"=> $loan->amount_requested,
                        "city"=> $loan->city->name,
                        "interest"=> $loan->interest->annual_interest,
                        "state"=> $loan->state->name,
                        "amount_approved"=> $loan->amount_approved,
                        "liquid_qualification_calculated"=> $loan->liquid_qualification_calculated,
                        "loan_term"=> $loan->loan_term,
                        "refinancing_balance"=> $loan->refinancing_balance,
                        "payment_type"=> $loan->payment_type->name,
                        "destiny_id"=> $loan->destiny->name,
                        "quota"=> $loan->EstimatedQuota,
                        "percentage_paid"=>$this->get_percentaje_loan($loan)
                        )
                    );
                    break;
                case 4:
                    array_push($liquidated,array(
                        "id"=> $loan->id,
                        "code"=> $loan->code,
                        "procedure_modality" => $loan->modality->name,
                        "request_date"=> $loan->disbursement_date,
                        "amount_requested"=> $loan->amount_requested,
                        "city"=> $loan->city->name,
                        "interest"=> $loan->interest->annual_interest,
                        "state"=> $loan->state->name,
                        "amount_approved"=> $loan->amount_approved,
                        "liquid_qualification_calculated"=> $loan->liquid_qualification_calculated,
                        "loan_term"=> $loan->loan_term,
                        "refinancing_balance"=> $loan->refinancing_balance,
                        "payment_type"=> $loan->payment_type->name,
                        "destiny_id"=> $loan->destiny->name,
                        "quota"=> $loan->EstimatedQuota,
                        "percentage_paid"=>$this->get_percentaje_loan($loan)
                        )
                    );
                    break;
                default:
                    break;
            }
        }
        return response()->json([
            'hasLoan' => false,
            'message' => 'Lista de Prestamos',
            'notification'=>'Se muestran los préstamos a partir del 2021',
            'payload' => [
                'inProcess'=> $inProcess,
                'current' => $current,
                'liquited' => $liquidated,
            ],
        ]);
        }
        else{
            return response()->json([
                'hasLoan' => true,
                'message' => 'El afiliado no tiene prestamos',
                'notification'=>'Se muestran los préstamos a partir del 2021',
                'payload' => [
                    'inProcess'=> [],
                    'current' => [],
                    'liquited' => [],
                ],
            ]);
        }
    }
    public function get_information_current_loans(Request $request, $idAffiliate)
    {
        $request['affiliate_id'] = $idAffiliate;
        $hasLoans = DB::table('loans')->where('affiliate_id',$request->id_affiliate)->exists();
        if ($hasLoans) {
            $loans = Loan::where([['affiliate_id', '=',$request->id_affiliate]])->whereIn('state_id',[1,3,4])->get();
        $current=[];
        $inProcess=[];
        $liquidated=[];
        foreach ($loans as $loan ) {
            if($loan->state_id==3) {
                array_push($current,array(
                    "id"=> $loan->id,
                    "code"=> $loan->code,
                    "procedure_modality" => $loan->modality->name,
                    "request_date"=> $loan->disbursement_date,
                    "amount_requested"=> $loan->amount_requested,
                    "city"=> $loan->city->name,
                    "interest"=> $loan->interest->annual_interest,
                    "state"=> $loan->state->name,
                    "amount_approved"=> $loan->amount_approved,
                    "liquid_qualification_calculated"=> $loan->liquid_qualification_calculated,
                    "loan_term"=> $loan->loan_term,
                    "refinancing_balance"=> $loan->refinancing_balance,
                    "payment_type"=> $loan->payment_type->name,
                    "destiny_id"=> $loan->destiny->name,
                    "quota"=> $loan->EstimatedQuota,
                    "percentage_paid"=>$this->get_percentaje_loan($loan)
                    )
                );
            }
        }
        return response()->json([
            'hasLoan' => false,
            'message' => 'Lista de Prestamos',
            'notification'=>'Se muestran los préstamos a partir del 2021',
            'payload' => [
                'inProcess'=> $inProcess,
                'current' => $current,
                'liquited' => $liquidated,
            ],
        ]);
        }
        else{
            return response()->json([
                'hasLoan' => true,
                'message' => 'El afiliado no tiene prestamos',
                'notification'=>'Se muestran los préstamos a partir del 2021',
                'payload' => [
                    'inProcess'=> [],
                    'current' => [],
                    'liquited' => [],
                ],
            ]);
        }
    }
    public static function verify_loans(Request $request, $identityCard)
    {
        $affiliate = Affiliate::where("identity_card", $identityCard)->first();
        if (!$affiliate) {
            $spouse = Spouse::where("identity_card", $identityCard)->first();
            if (!$spouse) {
                return response()->json([
                    'hasLoan' => false,
                    'message' => 'El afiliado no existe',
                ]);
            }
            $affiliate_id = $spouse->affiliate_id;
        } else {
            $affiliate_id = $affiliate->id;
        }
        $hasLoans = DB::table('loans')
            ->where('affiliate_id', $affiliate_id)
            ->where('state_id', 3)
            ->exists();

        return response()->json([
            'hasLoan' => $hasLoans,
        ]);
    }


    /**        return $pdf->download('aportes_act_' . $affiliate_id . '.pdf');

     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
