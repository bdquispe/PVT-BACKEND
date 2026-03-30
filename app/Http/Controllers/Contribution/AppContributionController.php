<?php

namespace App\Http\Controllers\Contribution;

use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Models\Affiliate\Affiliate;
use App\Models\Affiliate\Degree;
use App\Models\Contribution\Contribution;
use App\Models\Contribution\ContributionPassive;
use App\Models\Contribution\Reimbursement;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class AppContributionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function all_contributions(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:affiliates,id'
        ]);

        $affiliate = Affiliate::find($affiliate_id);
        $year_min = $affiliate->minimum_year_contribution_active;
        $year_max = $this->get_maximum_year($affiliate_id);

        if ($year_min > $year_max) {
            $year_min = $affiliate->minimum_year_contribution_passive;
        }

        $degree = Degree::find($affiliate->degree_id);

        $hasContributions = $affiliate->contributions;
        if (sizeof($hasContributions) > 0)
            $has_contributions_active = true;
        else
            $has_contributions_active = false;

        $hasContributionPassives = $affiliate->contribution_passives;
        if (sizeof($hasContributionPassives) > 0)
            $has_contributions_passive = true;
        else
            $has_contributions_passive = false;

        $contributions_total = collect();

        $reimbursements = Reimbursement::whereAffiliateId($affiliate_id)
            ->orderBy('month_year', 'asc')
            ->get();

        for ($i = $year_min; $i <= $year_max; $i++) {
            $contributions = collect();
            $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
                ->whereYear('month_year', $i)
                ->orderBy('month_year', 'asc')
                ->get();
            foreach ($contributions_passives as $contributions_passive) {

                if ($contributions_passive->contributionable_type == 'discount_type_economic_complement') {
                    $modality = $contributions_passive->contributionable->economic_complement->eco_com_procedure;
                    $modality_year = Carbon::parse($modality->year)->format('Y');
                    $text = "C.E." . $modality->semester . " Semestre " . $modality_year;
                } else {
                    $text = $contributions_passive->contributionable_type == 'payroll_senasirs' ? 'Tipo de descuento Senasir' : 'Tipo de descuento No Especificado';
                }
                $contributions->push([
                    'state' => 'PASIVO',
                    'id' => $contributions_passive->id,
                    'month_year' => $contributions_passive->month_year,
                    'description' => $text,
                    'quotable' => Util::money_format($contributions_passive->quotable),
                    'retirement_fund' => null,
                    'mortuary_quota' => null,
                    'total' => Util::money_format($contributions_passive->total),
                    'type' => $contributions_passive->contributionable_type
                ]);
            }

            $full_total = 0;
            $contributions_actives = Contribution::whereAffiliateId($affiliate_id)
                ->whereYear('month_year', $i)
                ->orderBy('month_year', 'asc')
                ->get();

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
                $contributions->push([
                    'state' => 'ACTIVO',
                    'id' => $contributions_active->id,
                    'month_year' => $contributions_active->month_year,
                    'description' => null,
                    'quotable' => Util::money_format($contributions_active->quotable),
                    'retirement_fund' => Util::money_format($contributions_active->retirement_fund),
                    'mortuary_quota' => Util::money_format($contributions_active->mortuary_quota),
                    'contribution_total' => Util::money_format($contribution_total),
                    'reimbursement_total' => Util::money_format($reimbursement_total),
                    'total' => Util::money_format($full_total),
                    'type' => $contributions_active->contributionable_type
                ]);
            }
            $contributions_total->push([
                'year' => $i . "",
                'contributions' => $contributions,
            ]);
        }

        return response()->json([
            "error" => "false",
            'message' => 'Contribuciones del Afiliado',
            'payload' => [
                'has_contributions_active' => $has_contributions_active,
                'has_contributions_passive' => $has_contributions_passive,
                'degree' => $degree->name ?? '',
                'first_name' => $affiliate->first_name,
                'second_name' => $affiliate->second_name,
                'last_name' => $affiliate->last_name,
                'mothers_last_name' => $affiliate->mothers_last_name,
                'surname_husband' => $affiliate->surname_husband,
                'identity_card' => $affiliate->identity_card,
                'city_identity_card' => $affiliate->city_identity_card->first_shortened ?? '',
                'contributions_total' => $contributions_total
            ],
        ]);
    }

    public function get_maximum_year($affiliate_id)
    {
        $maximum_year_contribution_passive = $maximum_year_contribution_active = 0;
        $affiliate = Affiliate::find($affiliate_id);
        $maximum_year_contribution_passive = $affiliate->maximum_year_contribution_passive;
        $maximum_year_contribution_active = $affiliate->maximum_year_contribution_active;

        if ($maximum_year_contribution_passive > $maximum_year_contribution_active)
            return $maximum_year_contribution_passive;
        return $maximum_year_contribution_active;
    }

    public function printCertificationContributionPassive(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contribution_passives,affiliate_id',
        ]);

        $affiliate = Affiliate::find($affiliate_id);
        $user = User::find(171);
        $degree = Degree::find($affiliate->degree_id);
        $contributions = collect();
        $value = false;
        $text = '';

        if ($affiliate->dead && $affiliate->spouse != null) {
            $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
                ->where('affiliate_rent_class', 'ilike', '%VIUDEDAD%')
                ->where('contribution_state_id', 2)
                ->orderBy('month_year', 'asc')
                ->get();
            $value = true;
        } else {
            $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
                ->where('contribution_state_id', 2)
                ->orderBy('month_year', 'asc')
                ->get();
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
                'retirement_fund' => null,
                'mortuary_quota' => null,
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
                    ['Fecha', Carbon::now()->format('d/m/Y')],
                    ['Hora', Carbon::now()->format('H:i')],
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
        $pdf = PDF::loadView('contribution.print.app_certification_contribution_eco_com', $data);
        $pdf->output();
        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();

        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $pageNumberWidth = $width / 2;
        $pageNumberHeight = $height - 35;
        $canvas->page_text($pageNumberWidth, $pageNumberHeight, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 10, array(0, 0, 0));

        return $pdf->download('aportes_pas_' . $affiliate_id . '.pdf');
    }

    public function printCertificationContributionPassive_v2(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contribution_passives,affiliate_id',
        ]);

        $affiliate = Affiliate::find($affiliate_id);
        $user = User::find(171);
        $degree = Degree::find($affiliate->degree_id);
        $contributions = collect();
        $value = false;
        $text = '';

        if ($affiliate->dead && $affiliate->spouse != null) {
            $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
                ->where('affiliate_rent_class', 'ilike', '%VIUDEDAD%')
                ->where('contribution_state_id', 2)
                ->orderBy('month_year', 'asc')
                ->get();
            $value = true;
        } else {
            $contributions_passives = ContributionPassive::whereAffiliateId($affiliate_id)
                ->where('contribution_state_id', 2)
                ->orderBy('month_year', 'asc')
                ->get();
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
                'retirement_fund' => null,
                'mortuary_quota' => null,
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
                    ['Fecha', Carbon::now()->format('d/m/Y')],
                    ['Hora', Carbon::now()->format('H:i')],
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
        $pdf = PDF::loadView('contribution.print.app_certification_contribution_eco_com', $data);
        $pdf->output();
        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();

        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $pageNumberWidth = $width / 2;
        $pageNumberHeight = $height - 35;
        $canvas->page_text($pageNumberWidth, $pageNumberHeight, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 10, array(0, 0, 0));

        return [
            'filename' => 'certificacion_contribution_eco_com_' . $affiliate_id . '.pdf',
            'content' => base64_encode($pdf->output()),
        ];
    }

    public function printCertificationContributionActive(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contributions,affiliate_id',
        ]);

        $affiliate = Affiliate::find($affiliate_id);
        $user = User::find(171);
        $degree = Degree::find($affiliate->degree_id);
        $contributions = Contribution::whereAffiliateId($affiliate_id)
            ->where('total', '>', 0)
            ->orderBy('month_year', 'asc')
            ->get();
        $reimbursements = Reimbursement::whereAffiliateId($affiliate_id)
            ->orderBy('month_year', 'asc')
            ->get();
        $num = 0;
        $data = [
            'header' => [
                'direction' => 'DIRECCIÓN DE BENEFICIOS ECONÓMICOS',
                'unity' => 'UNIDAD DE OTORGACIÓN DE FONDO DE RETIRO
                            POLICIAL, CUOTA MORTUORIA Y AUXILIO MORTUORIO',
                'table' => [
                    ['Usuario', $user->username],
                    ['Fecha', Carbon::now()->format('d/m/Y')],
                    ['Hora', Carbon::now('GMT-4')->format('H:i')],
                ]
            ],
            'num' => $num,
            'degree' => $degree,
            'affiliate' => $affiliate,
            'contributions' => $contributions,
            'reimbursements' => $reimbursements
        ];

        $pdf = PDF::loadView('contribution.print.app_certification_contribution_active', $data);
        $pdf->output();
        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();

        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $pageNumberWidth = $width / 2;
        $pageNumberHeight = $height - 35;
        $canvas->page_text($pageNumberWidth, $pageNumberHeight, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 10, array(0, 0, 0));

        return $pdf->download('aportes_act_' . $affiliate_id . '.pdf');
    }

    public function printCertificationContributionActive_v2(Request $request, $affiliate_id)
    {
        $request['affiliate_id'] = $affiliate_id;
        $request->validate([
            'affiliate_id' => 'required|integer|exists:contributions,affiliate_id',
        ]);

        $affiliate = Affiliate::find($affiliate_id);
        $user = User::find(171);
        $degree = Degree::find($affiliate->degree_id);
        $contributions = Contribution::whereAffiliateId($affiliate_id)
            ->where('total', '>', 0)
            ->orderBy('month_year', 'asc')
            ->get();
        $reimbursements = Reimbursement::whereAffiliateId($affiliate_id)
            ->orderBy('month_year', 'asc')
            ->get();
        $num = 0;
        $data = [
            'header' => [
                'direction' => 'DIRECCIÓN DE BENEFICIOS ECONÓMICOS',
                'unity' => 'UNIDAD DE OTORGACIÓN DE FONDO DE RETIRO
                            POLICIAL, CUOTA MORTUORIA Y AUXILIO MORTUORIO',
                'table' => [
                    ['Usuario', $user->username],
                    ['Fecha', Carbon::now()->format('d/m/Y')],
                    ['Hora', Carbon::now('GMT-4')->format('H:i')],
                ]
            ],
            'num' => $num,
            'degree' => $degree,
            'affiliate' => $affiliate,
            'contributions' => $contributions,
            'reimbursements' => $reimbursements
        ];

        $pdf = PDF::loadView('contribution.print.app_certification_contribution_active', $data);
        $pdf->output();
        $dom_pdf = $pdf->getDomPDF();
        $canvas = $dom_pdf->get_canvas();

        $width = $canvas->get_width();
        $height = $canvas->get_height();
        $pageNumberWidth = $width / 2;
        $pageNumberHeight = $height - 35;
        $canvas->page_text($pageNumberWidth, $pageNumberHeight, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 10, array(0, 0, 0));

        return [
            'filename' => 'certificacion_contribution_active_' . $affiliate_id . '.pdf',
            'content' => base64_encode($pdf->output()),
        ];
    }

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

    /**
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
