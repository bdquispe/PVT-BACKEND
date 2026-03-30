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
    {
        DB::statement("CREATE OR REPLACE FUNCTION public.get_periods_semester(id_eco_com_procedure bigint)
        RETURNS date []
        LANGUAGE plpgsql
        AS $$
        declare
           _periods date[] := array[]::date[];
           rec record;
        begin
           select extract(year from ecp.year) as year_c, ecp.semester as semester from eco_com_procedures ecp where ecp.id = id_eco_com_procedure into rec;
               case
                   when (rec.semester = 'Primer') then
                           for month_c in 7..12 loop
                               _periods :=  array_append(_periods, (rec.year_c||'-'||month_c||'-'||01)::date);
                           end loop;
                   when (rec.semester = 'Segundo') then
                           for month_c in 1..6 loop				  
                                _periods :=  array_append(_periods, (rec.year_c + 1||'-'||month_c||'-'||01)::date);					   
                           end loop;
                   else
                       _periods := array[]::date[];
               end case;
       return _periods;
       END;
       $$
       ;");
     DB::statement("CREATE OR REPLACE FUNCTION public.discount_amount_month(discount_id bigint)
     returns numeric
      language plpgsql
     as $$
     declare
     amount_semester numeric := 0;

     amount_month numeric := 0;

     begin
     --*********************************************************************************************--
     --Función para obtener monto por mes aporte del complemento económico para el auxilio mortuorio--
     --*********************************************************************************************--
         select
         amount
     into
         amount_semester
     from
         discount_type_economic_complement
     where
         id = discount_id;

     amount_month := round(amount_semester / 6, 2);

     return amount_month;
     end;
     $$;");

  DB::statement("CREATE OR REPLACE FUNCTION public.change_state_contribution_paid_eco_com(id_user bigint, id_economic_complements bigint)
  returns character varying
  language plpgsql
   as $$
   declare
   count_reg numeric:= 0;
   message varchar;
   cur_contribution cursor for (
    select
	    ec.id,
	    cp.id as contribution_id
    from
    	economic_complements ec
    inner join discount_type_economic_complement dtec
    on
    	ec.id = dtec.economic_complement_id
    inner join eco_com_states ecs
    on
    	ec.eco_com_state_id = ecs.id
    inner join contribution_passives cp
    on
    	cp.contributionable_id = dtec.id
    	and cp.contributionable_type = 'discount_type_economic_complement'
    where
    	dtec.discount_type_id = 7 --Descuento para Aporte Auxilio Mortuorio
    	and ecs.eco_com_state_type_id = 1 --Estado Pagado del Tramite de Complemento Economico
    	and contribution_state_id = 1 --Estado En proceso
    	and ec.id = id_economic_complements
        and cp.deleted_at is null);

   begin
    --************************************************************************************************--
    --Función para validar la contribución Cambio de Estado a Pagado en la tabla contribution_passives--
    --************************************************************************************************--

      for record_row in cur_contribution loop

   update
       public.contribution_passives
   set
       user_id = id_user,
       contribution_state_id = 2::bigint,
       updated_at = current_timestamp
   where
       contribution_passives.id = record_row.contribution_id and contribution_passives.deleted_at is null;
       count_reg:= count_reg + 1;
   end loop;
   message:=concat('Los aporte validados son: ',count_reg);
   return message ;
   end;
   $$
   ;");
  DB::statement("CREATE OR REPLACE FUNCTION public.change_state_contribution_process_eco_com(id_user bigint,id_economic_complements bigint)
  returns character varying
  language plpgsql
  AS $$
    declare
    count_reg numeric := 0;

    message varchar;

    cur_contribution cursor for (
    select
        ec.id,
        cp.id as contribution_id
    from
        economic_complements ec
    inner join discount_type_economic_complement dtec
    on
        ec.id = dtec.economic_complement_id
    inner join contribution_passives cp
    on
        cp.contributionable_id = dtec.id
        and cp.contributionable_type = 'discount_type_economic_complement'
    where
        dtec.discount_type_id = 7
        and ec.eco_com_state_id in (16)
        and contribution_state_id = 2--Pagado
        and ec.id = id_economic_complements
        and cp.deleted_at is null);

    begin
        --******************************************************************************--
        --Función para validar la contribución de Pagado a en Proceso tabla contribution_passives--
        --******************************************************************************--
       for record_row in cur_contribution loop

    update
        public.contribution_passives
    set
        user_id = id_user,
        contribution_state_id = 1,-- En Proceso
        updated_at = current_timestamp
    where
        contribution_passives.id = record_row.contribution_id;

    count_reg := count_reg + 1;
    end loop;

    message := concat('Los aporte que cambiaron de estado En Proceso son: ', count_reg);

    return message ;
    end;

    $$
    ;");

DB::statement("CREATE OR REPLACE FUNCTION public.import_contribution_eco_com(id_user bigint, eco_com_procedure bigint, id_eco_com bigint)
RETURNS character varying
LANGUAGE plpgsql
AS $$
      declare
      amount_semester numeric := 0;
      amount_month numeric := 0;
      rent_class varchar;
      array_length_months integer;
      periods date[] := array[]::date[];
      message varchar;
      is_validate boolean := false;
      is_change boolean := false;
      contribution_updated numeric := 0;
      contribution_created numeric := 0;
      tramit_number numeric := 0;
      not_valid_period varchar:='';
      contribution_passive RECORD ;

  --Declaración del cursor
        cur_discounts cursor for( select
          ec.id as eco_com_id,
          ec.affiliate_id,
          ec.total_rent,
          ec.dignity_pension,
          ec.eco_com_modality_id,
          ec.eco_com_procedure_id ,
          dtec.id as id_discont_type,
          dtec.amount,
          ecm.procedure_modality_id,
          ec.aps_total_cc,
          ec.aps_total_fsa,
          ec.aps_total_fs,
          ec.aps_total_death,
          ec.aps_disability
          from discount_type_economic_complement dtec
              inner join economic_complements ec
                  on ec.id = dtec.economic_complement_id
              inner join eco_com_modalities ecm
                  on ecm.id = ec.eco_com_modality_id
          where
              ec.id = id_eco_com                   			-- id del tramite
              and dtec.discount_type_id = 7					-- Amortización Auxilio Mortuorio
              and ec.eco_com_procedure_id = eco_com_procedure 	-- id semestre recibido como parámetro
              and ec.eco_com_state_id in (16)  				-- en proceso
              and ec.deleted_at is null
              and ec.wf_current_state_id =3);         			-- área técnica

      begin
      --***********************************************************************************--
      --Registro de contribuciones de los descuentos calculados para el auxilio mortorio--
      --***********************************************************************************--

      -- Procesa el cursor
      for record_row in cur_discounts loop
      --Declaración y asignación de información a variables----
      --Inicio Monto de descuento
        select amount into amount_semester from discount_type_economic_complement where id = record_row.id_discont_type;
        amount_month := round(amount_semester / 6, 2); --obtiene el aporte mensual
      --Fin Monto descuento---
          rent_class := (case when record_row.procedure_modality_id = 29 then 'VEJEZ' when record_row.procedure_modality_id = 30 then 'VIUDEDAD' end);
          periods := (select get_periods_semester(record_row.eco_com_procedure_id)); -- obtiene los periodos de aporte de acuerdo al semestre
          array_length_months := array_length(periods, 1);-- numero de meses

                for i in 1.. array_length_months loop      						  -- verificar si existe un periodo registrado
                  if ((select count(cp.id) from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = periods[i]::date and cp.contributionable_type <> 'discount_type_economic_complement')>=1)then
                          is_validate = true;
                          not_valid_period = periods[i]::date;
                 end if;
                end loop;

              --Realiza recorrido de meses
              if(is_validate is false)then
                tramit_number = 1;
              for i in 1.. array_length_months loop
                 select * from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = periods[i]::date into contribution_passive;
                  --No existe el registro de complemento economico
                  if not exists(select cp.id from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = periods[i]::date and cp.contributionable_type = 'discount_type_economic_complement' and cp.contributionable_id = record_row.id_discont_type) then
                  --Creación de Nuevos aportes--
                      insert into
                         public.contribution_passives (user_id,
                         affiliate_id,
                         month_year,
                         quotable,
                         rent_pension,
                         dignity_rent,
                         interest,total,
                         affiliate_rent_class,
                         contribution_state_id,
                         contributionable_type,
                         contributionable_id,
                         created_at,
                         updated_at,
                         aps_total_cc,
                         aps_total_fsa,
                         aps_total_fs,
                         aps_total_death,
                         aps_disability)
                        values(id_user,
                           record_row.affiliate_id,periods[i]::date,
                           record_row.total_rent::numeric,
                           record_row.total_rent::numeric,
                           0,
                           0,
                           amount_month::numeric,
                           rent_class::character varying,
                           1::bigint,
                           'discount_type_economic_complement',
                           record_row.id_discont_type,
                           current_timestamp,
                           current_timestamp,
                           record_row.aps_total_cc,
                           record_row.aps_total_fsa,
                           record_row.aps_total_fs,
                           CASE WHEN record_row.aps_total_death is not null THEN record_row.aps_total_death ELSE 0 END,
                           CASE WHEN record_row.aps_disability is not null THEN record_row.aps_disability ELSE 0 END
                          );
                          contribution_created = contribution_created + 1;
                  --Fin de Creación de Nuevos aportes--
                  else
                     if ((select count(cp.id) from contribution_passives cp where cp.deleted_at is null and cp.affiliate_id = record_row.affiliate_id and cp.month_year = periods[i]::date and cp.contributionable_type = 'discount_type_economic_complement' and cp.contributionable_id = record_row.id_discont_type)>=1) then
                  --Actualización de aportes--
                            if(contribution_passive.total <> amount_month)then
                              update public.contribution_passives
                              set user_id = id_user,
                                    total = amount_month::numeric,
                                    updated_at = current_timestamp
                              where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                            if(contribution_passive.quotable <> record_row.total_rent)then
                              update public.contribution_passives
                              set user_id = id_user,
                                  quotable = record_row.total_rent::numeric,
                                  rent_pension = record_row.total_rent::numeric,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                            if(contribution_passive.aps_total_cc <> record_row.aps_total_cc)then
                              update public.contribution_passives
                              set user_id = id_user,
                                  aps_total_cc = record_row.aps_total_cc,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                              if(contribution_passive.aps_total_fsa <> record_row.aps_total_fsa)then
                              update public.contribution_passives
                              set user_id = id_user,
                                  aps_total_fsa = record_row.aps_total_fsa,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                              if(contribution_passive.aps_total_fs <> record_row.aps_total_fs)then
                              update public.contribution_passives
                              set user_id = id_user,
                                  aps_total_fs = record_row.aps_total_fs,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                             if((contribution_passive.aps_total_death <> record_row.aps_total_death) or (record_row.aps_total_death is null and contribution_passive.aps_total_death > 0))then
                              update public.contribution_passives
                              set user_id = id_user,
                                  aps_total_death = CASE WHEN record_row.aps_total_death is not null THEN record_row.aps_total_death ELSE 0 END,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                             if((contribution_passive.aps_disability <> record_row.aps_disability) or (record_row.aps_disability is null and contribution_passive.aps_disability > 0))then
                              update public.contribution_passives
                              set user_id = id_user,
                                  aps_disability = CASE WHEN record_row.aps_disability is not null THEN record_row.aps_disability ELSE 0 END,
                                  updated_at = current_timestamp
                               where contribution_passives.id = contribution_passive.id;
                              is_change = true;
                            end if;
                            if(contribution_passive.affiliate_rent_class <> rent_class)then
                             update public.contribution_passives
                             set user_id = id_user,
                                 affiliate_rent_class = rent_class::character varying,
                                 updated_at = current_timestamp
                                 where contribution_passives.id = contribution_passive.id;
                             is_change = true;
                            end if;
                          if(is_change is true)then
                            contribution_updated = contribution_updated + 1;
                          end if;
                   --Fin de Actualizacion de Aportes--
                     end if;
                  end if;
              end loop;
           end if;
      end loop;
      message := tramit_number||','||contribution_created||','||contribution_updated||','||is_validate||','||not_valid_period;
      return message;
      end;
      $$;
      ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('function_get_periods_semester');
    }
};