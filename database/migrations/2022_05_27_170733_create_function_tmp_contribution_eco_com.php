<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFunctionTmpContributionEcoCom extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("CREATE OR REPLACE FUNCTION public.tmp_contribution_eco_com(id_user bigint)
        RETURNS character varying
        LANGUAGE plpgsql
        AS $$
        declare
        amount_semester numeric := 0;

        sum_amount numeric;

        amount_month numeric := 0;

        contribution_id int ;

        rent_class varchar;

        array_length_months integer;

        _periods date[] := array[]::date[];

        data_base_name varchar = 'discount_type_economic_complement';

        message varchar;

        dates varchar;

        amount_economic_complement numeric;

        amount_contribution_passive numeric;

        contribution_passive RECORD ;

        month_row RECORD;
        --Declaracio del cursor
        cur_discounts cursor for (
        select
            ec.id as eco_com_id,
            ec.affiliate_id,
            ec.total_rent,
            ec.dignity_pension,
            ec.eco_com_modality_id,
            ec.eco_com_procedure_id,
            ec.aps_total_cc,
            ec.aps_total_fsa,
            ec.aps_total_fs,
            ec.aps_disability,
            ec.aps_total_death,
            dtec.id as id_discont_type,
            dtec.amount,
            ecm.procedure_modality_id
        from
            discount_type_economic_complement dtec
        inner join economic_complements ec
        on
            ec.id = dtec.economic_complement_id
        inner join eco_com_modalities ecm
        on
            ecm.id = ec.eco_com_modality_id
        inner join eco_com_states ecs
        on
	        ec.eco_com_state_id = ecs.id
        where
            dtec.discount_type_id = 7
            and ecs.eco_com_state_type_id = 1
            and ec.deleted_at is null);

        begin
        --***********************************************************************************--
        --Registro de contibuciones de los descuentos ya realizados para el auxilio mortorio--
        --***********************************************************************************--
        -- Procesa el cursor
              for record_row in cur_discounts loop
        --Declaración y asignación de información a variables
        sum_amount := 0;

        amount_month := (
        select
            discount_amount_month(record_row.id_discont_type));

        rent_class :=
        (case
            when record_row.procedure_modality_id = 29 then 'VEJEZ'
            when record_row.procedure_modality_id = 30 then 'VIUDEDAD'
        end);

        _periods :=(
        select
            get_periods_semester(record_row.eco_com_procedure_id));

        array_length_months := array_length(_periods, 1);
        --Realiza recorrido de los 6 meses
        for i in 1.. array_length_months loop
         select * from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = _periods[i]::date into contribution_passive;

        contribution_id := (
        select
            cp.id
        from
            contribution_passives cp
        where
            cp.deleted_at is null
            and cp.affiliate_id = record_row.affiliate_id
            and cp.month_year = _periods[i]::date);

        if not exists(
        select
            cp.id
        from
            contribution_passives cp
        where
            cp.deleted_at is null
            and cp.affiliate_id = record_row.affiliate_id
            and cp.month_year = _periods[i]::date) then
        --Creación de Nuevos aportes--
        insert
            into
            public.contribution_passives (user_id,
            affiliate_id,
            month_year,
            quotable,
            rent_pension,
            dignity_rent,
            interest,
            total,
            affiliate_rent_class,
            contribution_state_id,
            contributionable_type,
            contributionable_id,
            created_at,
            updated_at)
        values(id_user,
        record_row.affiliate_id,
        _periods[i]::date,
        record_row.total_rent::numeric,
        record_row.total_rent::numeric,
        0::numeric,
        0::numeric,
        amount_month::numeric,
        rent_class::character varying,
        2::bigint,---Estado Pagado
        data_base_name::character varying,
        record_row.id_discont_type,
        current_timestamp,
        current_timestamp);
       end if;
       if ((select count(cp.id) from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = _periods[i]::date and cp.contributionable_type = 'discount_type_economic_complement' and cp.contributionable_id = record_row.id_discont_type)>=1) then
                   --Actualización de aportes--
                             if(contribution_passive.total <> amount_month)then
                               update public.contribution_passives
                               set user_id = id_user,
                              	   total = amount_month::numeric,
                              	   updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                             end if;
                             if(contribution_passive.quotable <> record_row.total_rent)then
                               update public.contribution_passives
                               set user_id = id_user,
                                   quotable = record_row.total_rent::numeric,
                                   rent_pension = record_row.total_rent::numeric,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                             if(contribution_passive.aps_total_cc <> record_row.aps_total_cc)then
                               update public.contribution_passives
                               set user_id = id_user,
                                   aps_total_cc = record_row.aps_total_cc,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                               if(contribution_passive.aps_total_fsa <> record_row.aps_total_fsa)then
                               update public.contribution_passives
                               set user_id = id_user,
                                   aps_total_fsa = record_row.aps_total_fsa,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                               if(contribution_passive.aps_total_fs <> record_row.aps_total_fs)then
                               update public.contribution_passives
                               set user_id = id_user,
                                   aps_total_fs = record_row.aps_total_fs,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                              if((contribution_passive.aps_total_death <> record_row.aps_total_death) or (record_row.aps_total_death is null and contribution_passive.aps_total_death > 0))then
                               update public.contribution_passives
                               set user_id = id_user,
                                   aps_total_death = CASE WHEN record_row.aps_total_death is not null THEN record_row.aps_total_death ELSE 0 END,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                              if((contribution_passive.aps_disability <> record_row.aps_disability) or (record_row.aps_disability is null and contribution_passive.aps_disability > 0))then
                               update public.contribution_passives
                               set user_id = id_user,
                                   aps_disability = CASE WHEN record_row.aps_disability is not null THEN record_row.aps_disability ELSE 0 END,
                                   updated_at = current_timestamp
                                where contribution_passives.id = contribution_passive.id;
                             end if;
                             if(contribution_passive.affiliate_rent_class <> rent_class)then
                              update public.contribution_passives
                              set user_id = id_user,
                                  affiliate_rent_class = rent_class::character varying,
                                  updated_at = current_timestamp
                                  where contribution_passives.id = contribution_passive.id;

                             end if;
                    --Fin de Actualizacion de Aportes--
                      end if;
        --para generar el ultimo aporte
        sum_amount = sum_amount + amount_month;

        if(i = 5) then
              amount_month := record_row.amount-sum_amount;
        end if;
        end loop;
        end loop;

        amount_economic_complement :=(
        select sum(dtec.amount)
        from discount_type_economic_complement dtec
        inner join economic_complements ec
        on ec.id = dtec.economic_complement_id
        inner join eco_com_modalities ecm
        on ecm.id = ec.eco_com_modality_id
        inner join eco_com_states ecs
        on ec.eco_com_state_id = ecs.id
        where dtec.discount_type_id = 7
        and ecs.eco_com_state_type_id = 1
        and ec.deleted_at is null);
        amount_contribution_passive:= (select sum(cp.total) from contribution_passives cp where cp.deleted_at is null and contribution_state_id = 2::bigint and cp.contributionable_type='discount_type_economic_complement');
        message := 'Registro realizado exitosamente'||','|| amount_economic_complement||','||amount_contribution_passive;

        return message;
        end;
        $$;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('tmp_contribution_eco_com');
    }
}
