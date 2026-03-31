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
        DB::statement("CREATE OR REPLACE FUNCTION public.registration_payroll_command(conection_db_aux character varying, month_copy integer, year_copy integer, reimbursement_copy boolean, user_id_into integer)
        RETURNS numeric
        LANGUAGE plpgsql
       AS $$
               declare
                         ----variables----
                        num_validated bigint := 0;
                        is_validated_update boolean := TRUE;
                        record_row RECORD;
                        affiliate_id_into bigint:=0;
                        affiliate_id_into2 bigint:=0;
                        breakdown_id_into int:=0;
                        unit_id_into int:=0;
                        hierarchy_id_into int:=0;
                        degree_id_into int:=0;
                        category_id_into int:=0;
                        affiliate_state_id_into int:=0;
                        message varchar := 'REGULAR';
                       -------------------------------------------------------------------------
                       ----FUNCIÓN PARA REGISTRAR LOS DATOS VALIDADOS DE PLANILLA DE COMANDO----
                       -------------------------------------------------------------------------
               BEGIN
               FOR record_row IN  
               select *
                from dblink(conection_db_aux,
                            'select id,uni,desg,mes,a_o,car,car_formato,pat,mat,apes,nom,nom2,eciv,niv,gra,sex,
               sue,sue_formato,cat,cat_formato,est,est_formato,carg,carg_formato,fro,fro_formato,ori,ori_formato,
               gan,gan_formato,mus,mus_formato,lpag,lpag_formato,nac,nac_formato,ing,ing_formato,is_validated,reimbursement,dtr FROM payroll_copy_commands
               ') 
               AS payroll_copy_commands(id bigint,uni varchar,desg integer,mes integer,a_o integer,car varchar,
               car_formato varchar,pat varchar,mat varchar,apes varchar,nom varchar,nom2 varchar,eciv varchar,niv varchar,
               gra varchar,sex varchar,sue varchar,sue_formato NUMERIC(13,2),cat varchar,cat_formato NUMERIC(13,2),est varchar,est_formato NUMERIC(13,2),
               carg varchar,carg_formato NUMERIC(13,2),fro varchar,fro_formato NUMERIC(13,2),ori varchar,ori_formato NUMERIC(13,2),
               gan varchar,gan_formato NUMERIC(13,2),mus varchar,mus_formato NUMERIC(13,2),lpag varchar,lpag_formato NUMERIC(13,2),nac varchar,
               nac_formato date,ing varchar,ing_formato date,is_validated boolean,reimbursement boolean,dtr integer)
               where mes = month_copy and a_o = year_copy and reimbursement = reimbursement_copy and is_validated = false
              loop
                  message:='REGULAR';
                  affiliate_id_into:= (SELECT identified_affiliate_command(record_row.car_formato,insert_text(record_row.pat),insert_text(record_row.mat),
                  insert_text(record_row.apes),insert_text(record_row.nom),insert_text(record_row.nom2),record_row.nac_formato,record_row.ing_formato));
                  breakdown_id_into:=  (select get_breakdown_id(record_row.desg));
                  unit_id_into := (select get_unit_id(breakdown_id_into,record_row.uni));
                  hierarchy_id_into := (select get_hierarchy_id(record_row.niv,record_row.gra));
                  degree_id_into := (select get_degree_id(hierarchy_id_into,record_row.gra));
                  category_id_into := (select get_category_id(record_row.cat_formato,record_row.sue_formato));
                  affiliate_state_id_into := (select get_affiliate_state_id(record_row.desg));

                  if affiliate_id_into <=0 then
                          INSERT INTO affiliates (
                       identity_card, affiliate_state_id,type,
                       unit_id,degree_id,category_id,
                       user_id,last_name,mothers_last_name,
                       surname_husband,first_name,second_name,civil_status,
                       gender, birth_date,date_entry,
                       created_at,updated_at)
                       VALUES (record_row.car_formato,get_affiliate_state_id(record_row.desg),get_type(record_row.desg),
                       unit_id_into,degree_id_into,category_id_into,
                       user_id_into,replace_character(insert_text(record_row.pat)),replace_character(insert_text(record_row.mat)),
                       replace_character(insert_text(record_row.apes)),replace_character(insert_text(record_row.nom)), replace_character(insert_text(record_row.nom2)),record_row.eciv,
                       record_row.sex,record_row.nac_formato,record_row.ing_formato, current_timestamp,current_timestamp);
                       message:='NUEVO';
                   end if;
                      affiliate_id_into2:= (SELECT identified_affiliate_command(record_row.car_formato,insert_text(record_row.pat),insert_text(record_row.mat),
                      insert_text(record_row.apes),insert_text(record_row.nom),insert_text(record_row.nom2),record_row.nac_formato,record_row.ing_formato));

                      INSERT INTO payroll_commands
                      VALUES (default,affiliate_id_into2,affiliate_state_id_into,unit_id_into,breakdown_id_into,category_id_into, record_row.mes,
                      record_row.a_o, record_row.car_formato, replace_character(insert_text(record_row.pat)),replace_character(insert_text(record_row.mat)),
                      replace_character(insert_text(record_row.apes)),replace_character(insert_text(record_row.nom)), replace_character(insert_text(record_row.nom2)),
                      record_row.eciv,hierarchy_id_into,degree_id_into,record_row.sex,record_row.sue_formato,
                      record_row.cat_formato,record_row.est_formato,record_row.carg_formato,record_row.fro_formato,
                      record_row.ori_formato,record_row.gan_formato,record_row.mus_formato,
                      record_row.lpag_formato,record_row.nac_formato,record_row.ing_formato,message,
                      current_timestamp,current_timestamp,null,record_row.reimbursement,record_row.dtr);
                      num_validated:=num_validated+1;
              END LOOP;
              RETURN num_validated;
              END $$;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP FUNCTION registration_payroll_command");
    }
};
