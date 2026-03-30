<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   DB::statement("CREATE OR REPLACE FUNCTION public.get_retirement_fund_reimbursement(date_period date, percentage numeric, total numeric, affiliate_id numeric)
        RETURNS numeric
        LANGUAGE plpgsql
       AS $$
                    declare
                        cr_retirement_fund numeric:=0;
                        retirement_fund_into numeric:=0;
                        cr_mortuary_quota numeric:=0;
       
                    begin
                    --*********************************************--
                    --Funcion para obtener monto de fondo de retiro--
                    --*********************************************--
                        --select round(avg(retirement_fund),2) into cr_retirement_fund from contribution_rates cr where month_year <= date_period and extract(year from month_year)= format_year_into;
                        --select round(avg(mortuary_quota),2) into cr_mortuary_quota from contribution_rates cr where month_year <= date_period and extract(year from month_year)= format_year_into;
	                    select retirement_fund into cr_retirement_fund from contribution_rates cr where month_year = date_period limit 1;
                        select mortuary_quota into cr_mortuary_quota from contribution_rates cr where month_year = date_period limit 1;
                       
     					---CASO 1: Porcentajes segun Contrib.Rates
                         if (percentage = round(cr_retirement_fund+cr_mortuary_quota,2)) then
                            retirement_fund_into:= total * cr_retirement_fund/percentage;
                         elseif (percentage = round(cr_mortuary_quota,2)) THEN
                             retirement_fund_into:=0;
                         --CASO2: Porcentaje distinto
                         elseif (percentage <> round(cr_mortuary_quota,2) and (percentage <> round(cr_mortuary_quota,2) and total>0)) THEN
                             retirement_fund_into:=get_retirement_fund_reimbursement_calculator(date_period,total,affiliate_id);
                         raise notice 'dif';
                         else
                           RAISE exception '(%)', 'Unknown percentage of contribution!';
                         end if;
                    return round(retirement_fund_into,2);
                    end;
                $$
       ;");

        DB::statement("CREATE OR REPLACE FUNCTION public.get_retirement_fund_reimbursement_calculator(p_date_period date, p_total numeric, p_affiliate_id numeric )
        RETURNS numeric
        LANGUAGE plpgsql
       AS $$
                    declare
                        retirement_fund_calculator_into numeric:=0;
                    begin
                    --*********************************************--
                    --Funcion para obtener monto de fondo de retiro calculado--
                    --*********************************************--
							/*1. REALIZAR LA SUMA DE TODAS LAS CONTRIBUCIONES DEL AFILIADO HASTA LA FECHA DE IMPORTACIÓN DEL REINTEGRO
	                    	  2. OBTENER EL PORCENTAJE DE FR EN BASE A LA SUMATORIA TOTAL (FR + CM)
	                    	  3. MULTIPLICAR EL PORCENTAJE DE FR POR EL CAMPO TOTAL DE REINTEGRO*/
									select
										(sum(retirement_fund)/sum(retirement_fund + mortuary_quota)) * p_total into retirement_fund_calculator_into
									from
										contributions c
									where
										affiliate_id = p_affiliate_id
										and month_year <= p_date_period
										and extract(year from month_year)= extract(year from p_date_period)
                                        and deleted_at is null
									group by
										affiliate_id;
                    return round(retirement_fund_calculator_into,2);
                    end;
                $$
       ;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('function_calculate_reimbursement');
    }
};
