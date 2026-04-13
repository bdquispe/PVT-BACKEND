<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Función para identificar afiliados según criterios de similitud
        DB::statement("CREATE OR REPLACE FUNCTION public.identified_affiliate_regional(order_entry integer, identity_card_entry varchar, first_name_entry varchar, second_name_entry varchar, last_name_entry varchar, mothers_last_name_entry varchar, surname_husband_entry varchar, tipo_aportante_entry varchar) 
            RETURNS integer
            LANGUAGE plpgsql
            AS $$
            DECLARE
                affiliate_id integer;
            BEGIN
                CASE
                    WHEN (order_entry = 1 AND tipo_aportante_entry = 'T') THEN
                        SELECT id INTO affiliate_id FROM affiliates
                        WHERE identity_card ILIKE identity_card_entry
                        AND word_similarity(first_name, first_name_entry) >= 0.5
                        AND word_similarity(second_name, second_name_entry) >= 0.5
                        AND word_similarity(last_name, last_name_entry) >= 0.5
                        AND word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5
                        AND word_similarity(surname_husband, surname_husband_entry) >= 0.5;

                    WHEN (order_entry = 2 AND tipo_aportante_entry = 'T') THEN
                        SELECT id INTO affiliate_id FROM affiliates
                        WHERE identity_card ILIKE identity_card_entry
                        AND word_similarity(first_name , first_name_entry) >= 0.5
                        AND word_similarity(last_name, last_name_entry) >= 0.5
                        AND word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5;

                    WHEN (order_entry = 3 AND tipo_aportante_entry = 'T') THEN
                        SELECT id INTO affiliate_id FROM affiliates
                        WHERE split_part(identity_card,'-',1) ILIKE identity_card_entry
                        AND (word_similarity(first_name, first_name_entry) >= 0.5 OR word_similarity(first_name, second_name_entry) >= 0.5)
                        AND word_similarity(last_name, last_name_entry) >= 0.5;

                    WHEN (order_entry = 4 AND tipo_aportante_entry = 'T') THEN
                        SELECT id INTO affiliate_id FROM affiliates
                        WHERE word_similarity(identity_card, identity_card_entry) >= 0.4
                        AND (COALESCE(first_name, '') ILIKE COALESCE(first_name_entry, ''))
                        AND (COALESCE(last_name, '') ILIKE COALESCE(last_name_entry, ''))
                        AND (COALESCE(mothers_last_name, '') ILIKE COALESCE(mothers_last_name_entry, ''));
                    
                    WHEN (order_entry = 5 AND tipo_aportante_entry = 'T') THEN
                        SELECT id INTO affiliate_id FROM affiliates
                        WHERE word_similarity(identity_card, identity_card_entry) >= 0.5
                        AND (word_similarity(first_name, first_name_entry) >= 0.5 OR word_similarity(second_name, first_name_entry) >= 0.5)
                        AND (word_similarity(last_name, last_name_entry) >= 0.5 OR word_similarity(mothers_last_name, last_name_entry) >= 0.5);                    

                    WHEN (order_entry = 6 AND tipo_aportante_entry = 'V') THEN
                        SELECT s.affiliate_id INTO affiliate_id FROM spouses s
                        WHERE identity_card ILIKE identity_card_entry
                        AND word_similarity(first_name , first_name_entry) >= 0.5
                        AND word_similarity(second_name , second_name_entry) >= 0.5
                        AND word_similarity(last_name, last_name_entry) >= 0.5
                        AND word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5
                        AND word_similarity(surname_husband, surname_husband_entry) >= 0.5;

                    WHEN (order_entry = 7 AND tipo_aportante_entry = 'V') THEN
                        SELECT s.affiliate_id INTO affiliate_id FROM spouses s
                        WHERE identity_card ILIKE identity_card_entry
                        AND word_similarity(first_name , first_name_entry) >= 0.5
                        AND word_similarity(last_name, last_name_entry) >= 0.5
                        AND word_similarity(mothers_last_name, mothers_last_name_entry) >= 0.5;

                    WHEN (order_entry = 8 AND tipo_aportante_entry = 'V') THEN
                        SELECT s.affiliate_id INTO affiliate_id FROM spouses s
                        WHERE split_part(identity_card,'-',1) ILIKE identity_card_entry
                        AND (word_similarity(first_name, first_name_entry) >= 0.5 OR word_similarity(first_name, second_name_entry) >= 0.5)
                        AND word_similarity(last_name, last_name_entry) >= 0.5;

                    WHEN (order_entry = 9 AND tipo_aportante_entry = 'V') THEN
                        SELECT s.affiliate_id INTO affiliate_id FROM spouses s
                        WHERE word_similarity(identity_card, identity_card_entry) >= 0.4
                        AND (COALESCE(first_name, '') ILIKE COALESCE(first_name_entry, ''))
                        AND (COALESCE(last_name, '') ILIKE COALESCE(last_name_entry, ''))
                        AND (COALESCE(mothers_last_name, '') ILIKE COALESCE(mothers_last_name_entry, ''));
                    
                    WHEN (order_entry = 10 AND tipo_aportante_entry = 'V') THEN
                        SELECT s.affiliate_id INTO affiliate_id FROM spouses s
                        WHERE word_similarity(identity_card, identity_card_entry) >= 0.5
                        AND (word_similarity(first_name, first_name_entry) >= 0.5 OR word_similarity(second_name, first_name_entry) >= 0.5)
                        AND (word_similarity(last_name, last_name_entry) >= 0.5 OR word_similarity(mothers_last_name, last_name_entry) >= 0.5);   

                    ELSE
                        affiliate_id := 0;
                END CASE;

                -- IF affiliate_id IS NULL THEN
                --     affiliate_id := 0;
                -- END IF;
                IF affiliate_id  is not NULL THEN
                    affiliate_id := affiliate_id;
                ELSE
                    affiliate_id := 0;
                END IF;
                RETURN affiliate_id;
            END;
            $$;"
        );

        // Función para vincular registros de tabla temporal con afiliados
        DB::statement("CREATE OR REPLACE FUNCTION public.search_affiliate_regional(conection_db_aux character varying, date_import date)
            RETURNS character varying
            LANGUAGE plpgsql
            AS $$
            DECLARE
                type_state varchar;
                affiliate_id_result integer;
                -- affiliates
                criterion_one integer := 1;
                criterion_two integer := 2;
                criterion_three integer := 3;
                criterion_four integer := 4;
                criterion_five integer := 5;
                -- spouses
                criterion_six integer := 6;
                criterion_seven integer := 7;
                criterion_eight integer := 8;
                criterion_nine integer := 9;
                criterion_ten integer := 10;

                cur_payroll CURSOR FOR (
                    SELECT * 
                    FROM dblink(conection_db_aux,
                        'SELECT id, carnet, tipo_aportante, nom, nom2, pat, mat, ap_casada, recibo, fecha_deposito, total_depositado, mes, a_o, total_pension, renta_dignidad, cotizable, aporte, porcentaje_aporte, affiliate_id, state, criteria
                        FROM payroll_copy_regionals
                        WHERE state = ''unrealized'' AND created_at::date = ' || quote_literal(date_import) || '')
                AS payroll_copy_regionals(
                    id integer,
                    carnet varchar(255),
                    tipo_aportante varchar(255),
                    nom varchar(255),
                    nom2 varchar(255),
                    pat varchar(255),
                    mat varchar(255),
                    ap_casada varchar(255),
                    recibo varchar(255),
                    fecha_deposito date,
                    total_depositado decimal(13,2),
                    mes integer,
                    a_o integer,
                    total_pension decimal(13,2),
                    renta_dignidad decimal(13,2),
                    cotizable decimal(13,2),
                    aporte decimal(13,2),
                    porcentaje_aporte decimal(13,2),
                    affiliate_id integer,
                    state varchar(255),
                    criteria varchar(255)
            ));
            BEGIN
                FOR record_row IN cur_payroll LOOP
                    IF record_row.tipo_aportante = 'T' THEN
                        affiliate_id_result:=0;
                        type_state:=NULL;
                        IF identified_affiliate_regional(criterion_one, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_one, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '1-CI-PN-SN-PA-SA-AC';
                        ELSIF identified_affiliate_regional(criterion_two, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_two, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '2-CI-sPN-sPA-sSA';
                        ELSIF identified_affiliate_regional(criterion_three, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_three, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '3-partCI-sPN-sPA';
                        ELSIF identified_affiliate_regional(criterion_four, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_four, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '4-sCI-PN-PA-SA';
                        ELSIF identified_affiliate_regional(criterion_five, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_five, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '5-sCI-sPN';
                        END IF;
                        IF affiliate_id_result IS NULL OR affiliate_id_result = 0 THEN
                            affiliate_id_result := 0;
                            type_state := '11-no-identificado';
                        END IF;
                        PERFORM dblink_exec(
                            conection_db_aux,
                            'UPDATE payroll_copy_regionals
                            SET state = ' || quote_literal('accomplished') || ',
                                criteria = ' || quote_literal(type_state) || ',
                                affiliate_id = ' || COALESCE(affiliate_id_result, 0) || ', 
                                     error_message = 
                                        CASE 
                                            WHEN ' || quote_literal(type_state) || ' IN (''11-no-identificado'',''5-sCI-sPN'',''10-sCI-sPN'') THEN
                                        CASE
                                            WHEN error_message IS NULL OR error_message = '''' THEN ''Persona no identificada''
                                            ELSE error_message || '' - Persona no identificada''
                                        END
                                    ELSE error_message
                                END
                            WHERE id = ' || record_row.id || '
                            AND created_at::date = ' || quote_literal(date_import)
                        );
                    END IF;
                    IF record_row.tipo_aportante = 'V' THEN
                        affiliate_id_result := 0;
                        type_state := NULL;
                        IF identified_affiliate_regional(criterion_six, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_six, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '6-CI-PN-SN-PA-SA-AC';
                        ELSIF identified_affiliate_regional(criterion_seven, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_seven, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '7-CI-sPN-sPA-sSA';
                        ELSIF identified_affiliate_regional(criterion_eight, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_eight, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '8-partCI-sPN-sPA';
                        ELSIF identified_affiliate_regional(criterion_nine, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_nine, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '9-sCI-PN-PA-SA';
                        ELSIF identified_affiliate_regional(criterion_ten, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante) > 0 THEN
                            affiliate_id_result := identified_affiliate_regional(criterion_ten, record_row.carnet, record_row.nom, record_row.nom2, record_row.pat, record_row.mat, record_row.ap_casada, record_row.tipo_aportante);
                            type_state := '10-sCI-sPN';
                        END IF;
                        IF affiliate_id_result IS NULL OR affiliate_id_result = 0 THEN
                            affiliate_id_result := 0;
                            type_state := '11-no-identificado';
                        END IF;

                        PERFORM dblink_exec(
                            conection_db_aux,
                            'UPDATE payroll_copy_regionals
                            SET state = ' || quote_literal('accomplished') || ',
                                criteria = ' || quote_literal(type_state) || ',
                                affiliate_id = ' || COALESCE(affiliate_id_result, 0) || ', 
                                     error_message = 
                                        CASE 
                                            WHEN ' || quote_literal(type_state) || ' IN (''11-no-identificado'',''5-sCI-sPN'',''10-sCI-sPN'') THEN
                                        CASE
                                            WHEN error_message IS NULL OR error_message = '''' THEN ''Persona no identificada''
                                            ELSE error_message || '' - Persona no identificada''
                                        END
                                    ELSE error_message
                                END
                            WHERE id = ' || record_row.id || '
                            AND created_at::date = ' || quote_literal(date_import)
                        );
                    END IF;
                END LOOP;
                RETURN true;
            END;
            $$;"
        );

        // Función que transfiere registros validados desde la tabla temporal a payroll_regionals
        DB::statement("CREATE OR REPLACE FUNCTION public.registration_payroll_regionals(conection_db_aux varchar, date_import date)
            RETURNS numeric
            LANGUAGE plpgsql
            AS $$
            DECLARE
                num_validated int := 0;
                is_validated_update varchar := 'validated';
                record_row RECORD;
            BEGIN
                FOR record_row IN
                    SELECT *
                    FROM dblink(conection_db_aux,
                        'SELECT carnet, tipo_aportante, nom, nom2, pat, mat, ap_casada, recibo, fecha_deposito,
                                total_depositado, mes, a_o, total_pension, renta_dignidad, cotizable, aporte,
                                porcentaje_aporte, affiliate_id, criteria
                        FROM payroll_copy_regionals
                        WHERE error_message IS NULL
                        AND deleted_at IS NULL
                        AND state = ''accomplished''
                        AND affiliate_id IS NOT NULL
                        AND affiliate_id <> 0
                        AND (criteria IS NULL OR criteria NOT IN (''11-no-identificado'', ''5-sCI-sPN'', ''10-sCI-sPN''))
                        AND created_at::date = ' || quote_literal(date_import) || '')
                    AS payroll_copy_regionals(
                        carnet varchar(255),
                        tipo_aportante varchar(255),
                        nom varchar(255),
                        nom2 varchar(255),
                        pat varchar(255),
                        mat varchar(255),
                        ap_casada varchar(255),
                        recibo varchar(255),
                        fecha_deposito date,
                        total_depositado decimal(13,2),
                        mes integer,
                        a_o integer,
                        total_pension decimal(13,2),
                        renta_dignidad decimal(13,2),
                        cotizable decimal(13,2),
                        aporte decimal(13,2),
                        porcentaje_aporte decimal(13,2),
                        affiliate_id integer,
                        criteria varchar(255)
                    )
                LOOP
                    INSERT INTO payroll_regionals
                    VALUES(
                        default, 
                        record_row.affiliate_id, 
                        record_row.carnet, 
                        record_row.tipo_aportante,
                        record_row.nom, 
                        record_row.nom2, 
                        record_row.pat, 
                        record_row.mat, 
                        record_row.ap_casada,
                        record_row.recibo, 
                        record_row.fecha_deposito, 
                        record_row.total_depositado,
                        record_row.mes, 
                        record_row.a_o, 
                        record_row.total_pension,
                        record_row.renta_dignidad, 
                        record_row.cotizable, 
                        record_row.aporte,
                        record_row.porcentaje_aporte, 
                        CURRENT_TIMESTAMP, 
                        CURRENT_TIMESTAMP
                    );
                    -- Actualizar la tabla auxiliar payroll_copy_regionals
                    PERFORM dblink(conection_db_aux,
                        'UPDATE payroll_copy_regionals
                        SET state = ''' || is_validated_update || ''' 
                        WHERE error_message IS NULL
                        AND deleted_at IS NULL
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

        // Crea la función donde recorre la tabla payroll_regionals y genera los aportes correspondientes
        DB::statement("CREATE OR REPLACE FUNCTION public.import_contribution_regional(user_reg integer, import_date date)
        RETURNS varchar
        AS $$
        DECLARE
            action varchar;
            -- Declaración EXPLÍCITA del cursor
            cur_contribution CURSOR FOR SELECT * FROM payroll_regionals WHERE created_at::date = import_date;
            registro payroll_regionals%ROWTYPE;
            BEGIN
                -- Función importar planilla
                FOR registro IN cur_contribution LOOP
                    -- Imprimir los campos deseados del registro
                    RAISE NOTICE 'Procesando registro: ID = %, Año = %, Mes = %, Afiliado ID = %',
                        registro.id, registro.year, registro.month, registro.affiliate_id;

                    -- Crear contribución
                    PERFORM create_contribution_regional(
                        registro.affiliate_id,
                        user_reg,
                        registro.id::INTEGER,
                        registro.year::INTEGER,
                        registro.month::INTEGER
                    );
                END LOOP;

                action := 'Importación realizada con éxito';
                RETURN action;
            END;
        $$ LANGUAGE plpgsql;");

        // Crea la función que busca si ya existe un aporte para un afiliado en un período específico
        DB::statement(" CREATE OR REPLACE FUNCTION search_affiliate_period_regional(affiliate bigint, year_copy integer, month_copy integer)
        RETURNS integer
        as $$
        DECLARE
        id_contribution_passive integer;
            BEGIN
            -- Función par buscar id de la contribución de un afiliado de un periodo determinado
                SELECT cp.id INTO id_contribution_passive FROM contribution_passives cp WHERE cp.deleted_at IS NULL AND cp.affiliate_id = affiliate AND EXTRACT(YEAR FROM cp.month_year) = year_copy AND EXTRACT(MONTH FROM cp.month_year) = month_copy;
                    IF id_contribution_passive IS NULL THEN
                        return 0;
                    ELSE
                        RETURN id_contribution_passive;
                    END IF;
            end;
        $$ LANGUAGE 'plpgsql';
        ");

        // Crea la función que registra un nuevo aporte o actualiza uno existente según criterios específicos
        DB::statement("CREATE OR REPLACE FUNCTION public.create_contribution_regional(affiliate bigint, user_reg integer, payroll_regional_id integer, year_copy integer, month_copy integer)
        RETURNS varchar
        as $$
        DECLARE
            type_action varchar;
            id_contribution_passive int;
            BEGIN
                -- Buscar si ya existe contribución para ese afiliado y periodo
                id_contribution_passive:= search_affiliate_period_regional(affiliate,year_copy,month_copy);
                IF id_contribution_passive = 0 THEN
                    type_action:= 'created';
                -- Creación de un nuevo registro en contribution_passives con Pagado = 2
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
                        user_reg AS user_id, 
                        affiliate,
                        TO_DATE(prs.year || '-' || prs.month || '-01', 'YYYY-MM-DD') as month_year, 
                        prs.quotable, 
                        prs.total_pension,
                        prs.dignity_rent, 
                        0 AS interest, 
                        prs.contribution,
                        current_timestamp AS created_at,
                        current_timestamp AS updated_at, 
                    CASE prs.rent_class
                            WHEN 'V' THEN 'VIUDEDAD'
                            ELSE 'VEJEZ'
                            END
                        AS affiliate_rent_class,
                        2 AS contribution_state_id,
                        'payroll_regionals'::character varying AS contributionable_type,
                        payroll_regional_id AS contributionable_id
                        FROM payroll_regionals prs
                        WHERE prs.id = payroll_regional_id;
                ELSE
                    type_action:= 'updated';
                    -- Actualizar el registro existente donde el aporte 
                        -- 1. contributionable_type es NULL
                        -- 2. total actual = total de planilla o total actual = 0
                    UPDATE contribution_passives cp
                    SET 
                        quotable = prs.quotable,
                        rent_pension = prs.total_pension,
                        dignity_rent = prs.dignity_rent, 
                        total = prs.contribution,
                        updated_at = current_timestamp,
                        affiliate_rent_class = CASE prs.rent_class
                            WHEN 'V' THEN 'VIUDEDAD'
                            ELSE 'VEJEZ'
                        END,
                        contribution_state_id = 2,
                        contributionable_type = 'payroll_regionals'::character varying,
                        contributionable_id = payroll_regional_id
                    FROM payroll_regionals prs
                    WHERE cp.id = id_contribution_passive
                    AND cp.contributionable_type IS NULL
                    AND (cp.total = prs.contribution OR cp.total = 0)
                    AND cp.affiliate_id = prs.affiliate_id
                    AND EXTRACT(YEAR FROM cp.month_year) = prs.year 
                    AND EXTRACT(MONTH FROM cp.month_year) = prs.month;
                END IF;
                RETURN type_action;
            END;
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
