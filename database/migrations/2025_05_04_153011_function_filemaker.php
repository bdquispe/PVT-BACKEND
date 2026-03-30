<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm;"); //Para crear la extensión pg_trgm para usar la función similarity()
        
        DB::statement("CREATE OR REPLACE FUNCTION public.identified_affiliate_filemaker(order_entry integer, identity_card_entry character varying, registration_entry character varying, first_name_entry character varying, second_name_entry character varying, last_name_entry character varying, mothers_last_name_entry character varying, surname_husband_entry character varying)
        RETURNS integer
        LANGUAGE plpgsql
        AS $$
           DECLARE
                affiliate_id integer;
                begin
                     CASE
                        WHEN (order_entry = 1 ) THEN --Busqueda de afiliado por CI igual, nombre, paterno, materno y apellido de casada similares
                            select id into affiliate_id from affiliates where
                            identity_card ILIKE identity_card_entry
                            AND word_similarity(first_name , first_name_entry) >= 0.5
                            AND word_similarity(last_name, last_name_entry) >= 0.5
                            AND word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5;

                        WHEN (order_entry = 2 ) THEN --Busqueda de afiliado por CI sin complemento,nombre, paterno similares
                            select id into affiliate_id from affiliates where
                            split_part(identity_card,'-',1) ILIKE identity_card_entry
                            AND (word_similarity(first_name, first_name_entry) >= 0.5 or word_similarity(first_name, second_name_entry) >= 0.5)
                            AND word_similarity(last_name, last_name_entry) >= 0.4;

                        WHEN (order_entry = 3 ) then --Busqueda de afiliado por CI similar matricula, nombre, paterno igual--
                            select id into affiliate_id from affiliates where
                            word_similarity(identity_card, identity_card_entry) >= 0.4
                            AND (COALESCE(registration, '') ILIKE COALESCE(registration_entry, ''))
                            AND (COALESCE(first_name, '') ILIKE COALESCE(first_name_entry, ''))
                            AND (COALESCE(last_name, '') ILIKE COALESCE(last_name_entry, ''));

                        WHEN (order_entry = 4 ) then  --Busqueda de afiliado por matricula igual y apellido de casada del archivo con apellido paterno de affiliates, verificando que la aportante es la conyugue
                            select id into affiliate_id from affiliates where registration is not null
                             AND registration_entry is not null                           
                             AND COALESCE(registration, '') ILIKE COALESCE(registration_entry, '')
                             AND word_similarity(last_name, surname_husband_entry) >= 0.5;

                        WHEN (order_entry = 5 ) THEN --Busqueda de conyuge por CI sin complemento,nombre, paterno , materno similares
                            select s.affiliate_id into affiliate_id from spouses s where
                            split_part(identity_card,'-',1) ILIKE identity_card_entry
                            AND (word_similarity(first_name , first_name_entry) >= 0.5 or word_similarity(second_name , second_name_entry) >= 0.5)
                            AND (word_similarity(last_name, last_name_entry) >= 0.5 or word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5);

                        WHEN (order_entry = 6 ) then --Busqueda de conyuge por CI similar matricula, nombre, paterno igual--
                            select s.affiliate_id into affiliate_id from spouses s where
                            word_similarity(identity_card, identity_card_entry) >= 0.4
                            AND (COALESCE(registration, '') ILIKE COALESCE(registration_entry, ''))
                            AND (COALESCE(first_name, '') ILIKE COALESCE(first_name_entry, ''))
                            AND (COALESCE(last_name, '') ILIKE COALESCE(last_name_entry, ''));

                        ELSE
                         affiliate_id := 0;
                        END CASE;

               IF affiliate_id  is not NULL THEN
                  affiliate_id := affiliate_id;
               ELSE
                  affiliate_id := 0;
               END IF;
            return affiliate_id;
            END;
            $$;"
        );

        DB::statement("CREATE OR REPLACE FUNCTION public.search_affiliate_filemaker(conection_db_aux character varying, date_import date)
        RETURNS character varying
        LANGUAGE plpgsql
        AS $$
                 declare
                 type_state varchar;
                 affiliate_id_result integer;
                 criterion_one integer:= 1;
                 criterion_two integer:= 2;
                 criterion_three integer:= 3;
                 criterion_four integer:= 4;--spouses
                 criterion_five integer:= 5;--spouses
                 criterion_six integer:= 6; --spouses
                 --date_period date := (year||'-'||month||'-'||01)::date;
                 ------------------------------
                 cant varchar;
                ---------------------------------
              -- Declaración EXPLICITA del cursor
              cur_payroll CURSOR for (select * from dblink( conection_db_aux,'SELECT id, a_o, mes, carnet, matricula, pat, mat, nom, nom2, ap_casada, grado, cor_afi, fecha_pago, recibo, monto, observacion, affiliate_id_frcam, tipo_aportante, affiliate_id, state, criteria FROM  payroll_copy_filemakers where state = ''unrealized'' and created_at::date = ' || quote_literal(date_import) || '')
              as  payroll_copy_filemakers( id integer,  a_o integer, mes integer, carnet character varying(250), matricula character varying(250), pat character varying(250), mat character varying(250), nom character varying(250), nom2 character varying(250), ap_casada character varying(250), grado character varying(250), cor_afi integer, fecha_pago date, recibo character varying(250), monto decimal(13,2), observacion character varying(250), affiliate_id_frcam integer, tipo_aportante character varying(250), affiliate_id integer, state character varying(250), criteria character varying(250)));
              begin
                   --************************************************************
                   --*Funcion filemaker busqueda de afiliados y affiliate_id de spouses  
                   --************************************************************
                   -- Procesa el cursor
              FOR record_row IN cur_payroll loop
                IF COALESCE(record_row.tipo_aportante, '') IN ('', 'VEJEZ') THEN
                  if identified_affiliate_filemaker(criterion_one, record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada ) > 0 then
                      affiliate_id_result := identified_affiliate_filemaker( criterion_one, record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='1-CI-sPN-sPA-sSA';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VEJEZ'' WHERE payroll_copy_filemakers.id= '||record_row.id||''));

                  elsif identified_affiliate_filemaker(criterion_two,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada) > 0 THEN
                      affiliate_id_result := identified_affiliate_filemaker(criterion_two,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='2-partCI-sPN-sPA';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VEJEZ''  WHERE payroll_copy_filemakers.id= '||record_row.id||''));

                  elsif identified_affiliate_filemaker(criterion_three,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada) > 0 THEN
                      affiliate_id_result := identified_affiliate_filemaker(criterion_three,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='3-sCI-MAT-PN-PA';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VEJEZ''  WHERE payroll_copy_filemakers.id= '||record_row.id||''));
                  END IF;
                ELSIF COALESCE(record_row.tipo_aportante, '') IN ('', 'VIUDEDAD') THEN
                  if identified_affiliate_filemaker(criterion_four,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada) > 0 THEN
                      affiliate_id_result := identified_affiliate_filemaker(criterion_four,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='4-MAT-APCAS';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VIUDEDAD''  WHERE payroll_copy_filemakers.id= '||record_row.id||''));

                  elsif identified_affiliate_filemaker(criterion_five,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada) > 0 THEN
                      affiliate_id_result := identified_affiliate_filemaker(criterion_five,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='5-cCI-sPN-sPA';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VIUDEDAD''  WHERE payroll_copy_filemakers.id= '||record_row.id||''));

                  elsif identified_affiliate_filemaker(criterion_six,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada) > 0 THEN
                      affiliate_id_result := identified_affiliate_filemaker(criterion_six,record_row.carnet, record_row.matricula, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada);
                      type_state:='6-partcCI-sPN-sPA';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''',affiliate_id='||affiliate_id_result||', tipo_aportante=''VIUDEDAD''  WHERE payroll_copy_filemakers.id= '||record_row.id||''));
                  END IF;
                ELSE -- si NO es VEJEZ ni VIUDEDAD o no se identifica tipo_aportante
                      type_state:='7-no-identificado';
                      cant:= (select dblink_exec(conection_db_aux, 'UPDATE payroll_copy_filemakers SET state=''accomplished'',criteria='''||type_state||''' WHERE payroll_copy_filemakers.id= '||record_row.id||''));
                END IF;
              END LOOP;
              return true;
              end;
              $$;"
        );

        DB::statement("CREATE OR REPLACE FUNCTION public.registration_payroll_filemakers(conection_db_aux character varying, date_import date)
        RETURNS numeric
        LANGUAGE plpgsql
        AS $$
        declare
            ----variables----
            num_validated int := 0;
            is_validated_update varchar := 'validated';
            record_row RECORD;
        BEGIN
            FOR record_row IN  
                SELECT * 
                FROM dblink(conection_db_aux,
                'SELECT a_o, mes, carnet, matricula, pat, mat, nom, nom2, ap_casada, grado, fecha_pago, recibo, monto, observacion, tipo_aportante, affiliate_id, criteria 
                FROM payroll_copy_filemakers 
                WHERE error_message is null 
                AND deleted_at is null 
                AND state =''accomplished'' 
                AND affiliate_id is not null
                AND created_at::date = ' || quote_literal(date_import) || '')
                AS payroll_copy_filemakers(
                    a_o integer, 
                    mes integer, 
                    carnet varchar(250), 
                    matricula varchar(250), 
                    pat varchar(250), 
                    mat varchar(250), 
                    nom varchar(250), 
                    nom2 varchar(250), 
                    ap_casada varchar(250),
                    grado varchar(250),
                    fecha_pago date,
                    recibo varchar(250), 
                    monto decimal(13,2), 
                    observacion varchar(250),
                    tipo_aportante varchar(250), 
                    affiliate_id integer, 
                    criteria varchar(250)
                )
            LOOP
                -- Insertar en la tabla principal
                INSERT INTO payroll_filemakers  
                VALUES (
                    default,
                    record_row.affiliate_id, 
                    record_row.a_o, 
                    record_row.mes,
                    record_row.tipo_aportante, 
                    record_row.carnet, 
                    record_row.matricula, 
                    record_row.pat, 
                    record_row.mat, 
                    record_row.nom, 
                    record_row.nom2, 
                    record_row.ap_casada, 
                    record_row.grado,
                    record_row.fecha_pago,
                    record_row.recibo,
                    record_row.monto, 
                    record_row.observacion,
                    current_timestamp, 
                    current_timestamp
                );
        
                -- Actualizar la tabla auxiliar payroll_copy_filemakers
                PERFORM dblink(conection_db_aux,
                    'UPDATE payroll_copy_filemakers 
                    SET state = ''' || is_validated_update || ''' 
                    WHERE error_message is null 
                    AND deleted_at is null
                    AND affiliate_id = ' || record_row.affiliate_id || ' 
                    AND a_o = ' || record_row.a_o || ' 
                    AND mes = ' || record_row.mes || '
                    AND created_at::date = ' || quote_literal(date_import) || ''
                );
        
                num_validated := num_validated + 1;
            END LOOP;
            RETURN num_validated;
            END $$;
        ");
        //4.1
        DB::statement("CREATE OR REPLACE FUNCTION public.import_contribution_filemaker ( user_reg integer, import_date date)
        RETURNS varchar
        AS $$
        DECLARE
            acction varchar;
            -- Declaración EXPLÍCITA del cursor
            cur_contribution CURSOR FOR SELECT * FROM payroll_filemakers WHERE created_at::date = import_date;
            registro payroll_filemakers%ROWTYPE;
            BEGIN
                --***************************************
                -- Función importar planilla
                --***************************************

                -- Procesar el cursor
                FOR registro IN cur_contribution LOOP
                    -- Imprimir los campos deseados del registro
                    RAISE NOTICE 'Procesando registro: ID = %, Año = %, Mes = %, Afiliado ID = %',
                        registro.id, registro.year_p, registro.month_p, registro.affiliate_id;

                    -- Crear contribución
                    PERFORM create_contribution_filemaker(
                        registro.affiliate_id,
                        user_reg,
                        registro.id::INTEGER,
                        registro.year_p::INTEGER,
                        registro.month_p::INTEGER
                    );

                END LOOP;

                acction := 'Importación realizada con éxito';
                RETURN acction;
            END;
        $$ LANGUAGE plpgsql;");


        DB::statement(" CREATE OR REPLACE FUNCTION search_affiliate_period_filemaker(affiliate bigint, year_copy integer, month_copy integer)
        RETURNS integer
        as $$
        DECLARE
        id_contribution_passive integer;
            begin
            --************************************************************************************
            --Funcion par buscar id de la contribucion de un afiliado de un periodo determinado
            --************************************************************************************ 
                SELECT cp.id INTO id_contribution_passive  FROM contribution_passives cp WHERE cp.deleted_at is null and cp.affiliate_id = affiliate AND EXTRACT(YEAR FROM cp.month_year) = year_copy AND  EXTRACT(MONTH FROM cp.month_year) = month_copy;
                    IF id_contribution_passive is NULL THEN
                        return 0;
                    ELSE
                        RETURN  id_contribution_passive;
                    END IF;
            end;
        $$ LANGUAGE 'plpgsql';
        ");
        //4.2
        DB::statement("CREATE OR REPLACE FUNCTION public.create_contribution_filemaker(affiliate bigint, user_reg integer, payroll_filemaker_id integer, year_copy integer, month_copy integer)
        RETURNS varchar
        as $$
        declare

        type_acction varchar;
        id_contribution_passive int;
            begin
                --*******************************************************************************
                --Funcion par crear un nuevo registro en la tabla contribution_passive--
                --*******************************************************************************
                id_contribution_passive:= search_affiliate_period_filemaker(affiliate,year_copy,month_copy);
                IF id_contribution_passive = 0 then
                    type_acction:= 'created';

                -- Creacion de un nuevo registro de la contribucion con Pagado = 2
                    INSERT INTO public.contribution_passives(
                    user_id, 
                    affiliate_id, 
                    month_year, 
                    quotable, 
                    rent_pension, 
                    dignity_rent, 
                    interest, 
                    total, 
                    created_at,
                    updated_at,
                    affiliate_rent_class,
                    contribution_state_id, 
                    contributionable_type, 
                    contributionable_id
                    )
                    SELECT 
                    user_reg as user_id, 
                    affiliate,
                    TO_DATE(pfs.year_p || '-' || pfs.month_p || '-' || 1, 'YYYY-MM-DD') as month_year, 
                    0 as quotable, 
                    0 as rent_pension,
                    0 as dignity_rent, 
                    0 as interest, 
                    pfs.discount_contribution as total,
                    current_timestamp as created_at,
                    current_timestamp as updated_at, 
                    CASE pfs.rent_class
                            when 'VIUDEDAD' then 'VIUDEDAD'
                            else 'VEJEZ'
                            end
                        as affiliate_rent_class,
                        2 as contribution_state_id,
                        'payroll_filemakers'::character varying as contributionable_type, 
                        payroll_filemaker_id as contributionable_id 
                        from payroll_filemakers pfs
                        WHERE pfs.id=payroll_filemaker_id;
                ELSE
                    type_acction:= 'updated';
                    -- Actualizar el registro existente donde el aporte 1. Es el mismo monto o 2. El monto en contribution_passives es cero
                    UPDATE contribution_passives cp
                    SET 
                        total = pfs.discount_contribution,
                        updated_at = current_timestamp,
                        affiliate_rent_class = CASE pfs.rent_class
                            when 'VIUDEDAD' then 'VIUDEDAD'
                            else 'VEJEZ'
                            end,
                        contribution_state_id = 2,
                        contributionable_type = 'payroll_filemakers'::character varying,
                        contributionable_id = payroll_filemaker_id
                    FROM payroll_filemakers pfs
                    WHERE cp.id = id_contribution_passive
                    AND cp.contributionable_type is NULL
                    AND (cp.total = pfs.discount_contribution OR cp.total = 0)
                    AND cp.affiliate_id = pfs.affiliate_id
                    AND EXTRACT(YEAR FROM cp.month_year) = pfs.year_p 
                    AND EXTRACT(MONTH FROM cp.month_year) = pfs.month_p;
                END IF;
                RETURN type_acction ;
            end;
        $$ LANGUAGE 'plpgsql'
        ");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
