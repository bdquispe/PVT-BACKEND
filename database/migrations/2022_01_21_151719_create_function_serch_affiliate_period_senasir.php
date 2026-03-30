<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFunctionSerchAffiliatePeriodSenasir extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement(" CREATE OR REPLACE FUNCTION serch_affiliate_period_senasir(affiliate bigint, year_copy date)
        RETURNS integer
        as $$
        DECLARE
        id_contribution_passive integer;
        begin
           --************************************************************************************
           --Funcion par buscar id de la contribucion de un afiliado de un periodo determinado
           --************************************************************************************ 
            SELECT cp.id INTO id_contribution_passive  FROM contribution_passives cp WHERE cp.affiliate_id = affiliate AND cp.month_year = year_copy AND cp.deleted_at IS NULL;
                IF id_contribution_passive is NULL THEN
                    return 0;
                ELSE
                    RETURN  id_contribution_passive;
                END IF;
        end;
        $$ LANGUAGE 'plpgsql';
       ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP FUNCTION serch_affiliate_period_senasir");
    }
}
