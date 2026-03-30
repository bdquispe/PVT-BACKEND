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
        DB::statement("CREATE OR REPLACE FUNCTION public.fn_report_fr_contributions_resumen(fecha_inicio DATE, fecha_fin DATE)
            RETURNS TABLE (
            id bigint,
            identity_card character varying,
            first_name character varying,
            second_name character varying,
            last_name character varying,
            mothers_last_name character varying,
            surname_husband character varying,
            birth_date DATE,
            date_death DATE,
            code character varying,
            reception_date DATE,
            num_cert character varying,
            date_cert DATE,
            name_unit character varying,
            code_unit character varying,
            \"1_Servicio_Activo\" TEXT,
            \"2_Periodo_en_item_0_Con_Aporte\" TEXT,
            \"3_Periodo_en_item_0_Sin_Aporte\" TEXT,
            \"4_Periodo_de_Batallon_de_Seguridad_Fisica_Con_Aporte\" TEXT,
            \"5_Periodo_de_Batallon_de_Seguridad_Fisica_Sin_Aporte\" TEXT,
            \"6_Periodos_anteriores_a_Mayo_de_1976_Sin_Aporte\" TEXT,
            \"7_Periodo_Certificacion_Con_Aporte\" TEXT,
            \"8_Periodo_Certificacion_Sin_Aporte\" TEXT,
            \"9_Periodo_no_Trabajado\" TEXT,
            \"10_Disponibilidad\" TEXT,
            \"11_Periodo_pagado_con_anterioridad\" TEXT,
            \"12_Disponibilidad_Con_Aporte\" TEXT,
            \"13_Disponibilidad_Sin_Aporte\" TEXT,
            \"14_Inexistencia_de_Planilla_de_Haberes\" TEXT,
            fec_validacion DATE,
	        fec_derivacion DATE
        )
            LANGUAGE plpgsql
        AS $$
        BEGIN
        RETURN QUERY
        ----Ordenar y agrupar contribuciones mediante el metodo Gaps and islands over, generando identificador de fecha diferente cuando cambie contribution_type_id o no exista secuencia en month_year
        WITH ordered_contributions AS (
            SELECT
                c2.affiliate_id,
                c2.month_year,
                c2.contribution_type_id,
                c2.month_year - 
                (ROW_NUMBER() OVER (PARTITION BY c2.affiliate_id, c2.contribution_type_id ORDER BY c2.month_year)) * INTERVAL '1 month' AS grp
            FROM contributions c2
            WHERE deleted_at is null
        ),
        ---Agrupar periodos por contribution_type_id 
        grouped_periods AS (
            SELECT
                affiliate_id,
                contribution_type_id,
                MIN(month_year) AS min_month_year,
                MAX(month_year) AS max_month_year
            FROM ordered_contributions
            GROUP BY affiliate_id, contribution_type_id, grp
        ),
        ---Obtener datos de retirement_funds y afiliados
        data_rf AS (
            SELECT
                a.id,
                a.identity_card,
                a.first_name,
                a.second_name,
                a.last_name,
                a.mothers_last_name,
                a.surname_husband,
                a.birth_date,
                a.date_death,
                rf.code,
                rf.reception_date,
                rfc.code AS num_cert,
                rfc.date AS date_cert,
                u.name AS name_unit,
                u.code AS code_unit,
                wfr.fec_validacion,
		        wfr.fec_derivacion
            FROM retirement_funds rf
            JOIN affiliates a ON rf.affiliate_id = a.id
            LEFT JOIN ret_fun_correlatives rfc ON
                rfc.retirement_fund_id = rf.id
                AND rfc.wf_state_id = 22
                AND rfc.deleted_at IS NULL
            LEFT JOIN (
                SELECT DISTINCT ON (affiliate_id)
                    affiliate_id,
                    month_year,
                    unit_id
                FROM contributions
                WHERE deleted_at is null
                ORDER BY affiliate_id, month_year
            ) AS c ON c.affiliate_id = a.id
            LEFT JOIN (
                SELECT 
                    recordable_id,
                    MAX(CASE WHEN message ILIKE '%valid%' THEN TO_CHAR(date, 'YYYY-MM-DD') END) AS fec_validacion,
                    MAX(CASE WHEN message ILIKE '%deriv%' THEN TO_CHAR(date, 'YYYY-MM-DD') END) AS fec_derivacion
                FROM wf_records wr
                WHERE
                recordable_type ILIKE 'retirement_funds'
                AND record_type_id IN (3, 1)
                AND wf_state_id = 22
                GROUP BY recordable_id
            ) AS wfr ON wfr.recordable_id = rf.id
            LEFT JOIN units u ON u.id = c.unit_id
            WHERE rf.created_at::date BETWEEN fecha_inicio AND fecha_fin
            AND rf.wf_state_current_id IN (
                SELECT wfs.id
                FROM wf_states wfs
                WHERE wfs.module_id = 3
                AND wfs.sequence_number >= (
                    SELECT wfs2.sequence_number
                    FROM wf_states AS wfs2
                    WHERE wfs2.module_id = 3
                    AND wfs2.first_shortened = 'Cuentas Individuales'
                )
                AND wfs.deleted_at IS NULL
            )
            AND rf.code NOT ILIKE '%A'
        ),
        ----Unir datos de afiliados con los periodos agrupados
        data_main AS (
            SELECT
                drf.*,
                gp.contribution_type_id,
                gp.min_month_year,
                gp.max_month_year
            FROM data_rf drf
            JOIN grouped_periods gp ON gp.affiliate_id = drf.id
        )
        ---transformar encabezados de clasificadores y agrupar aportes clasificados min y max
        SELECT
            dm.id,
            dm.identity_card,
            dm.first_name,
            dm.second_name,
            dm.last_name,
            dm.mothers_last_name,
            dm.surname_husband,
            dm.birth_date,
            dm.date_death,
            dm.code,
            dm.reception_date,
            dm.num_cert,
            dm.date_cert,
            dm.name_unit,
            dm.code_unit,
            STRING_AGG(CASE WHEN dm.contribution_type_id = 1 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Servicio Activo\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 2 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo en item 0 Con Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 3 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo en item 0 Sin Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 4 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo de Batallón de Seguridad Fisica Con Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 5 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo de Batallón de Seguridad Fisica Sin Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 6 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodos anteriores a Mayo de 1976 Sin Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 7 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo Certificacion Con Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 8 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo Certificacion Sin Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 9 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo no Trabajado\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 10 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Disponibilidad\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 11 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Periodo pagado con anterioridad\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 12 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Disponibilidad Con Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 13 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Disponibilidad Sin Aporte\",
            STRING_AGG(CASE WHEN dm.contribution_type_id = 14 THEN TO_CHAR(dm.min_month_year, 'YYYY-MM-DD') || ' a ' || TO_CHAR(dm.max_month_year, 'YYYY-MM-DD') END, E',\n') AS \"Inexistencia de Planilla de Haberes\",
            dm.fec_validacion::DATE,
	        dm.fec_derivacion::DATE
        FROM data_main dm
        GROUP BY
            dm.id,
            dm.identity_card,
            dm.first_name,
            dm.second_name,
            dm.last_name,
            dm.mothers_last_name,
            dm.surname_husband,
            dm.birth_date,
            dm.date_death,
            dm.code,
            dm.reception_date,
            dm.num_cert,
            dm.date_cert,
            dm.name_unit,
            dm.code_unit,
            dm.fec_validacion,
	        dm.fec_derivacion
        ORDER BY dm.id;

        END;
        $$;"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      //
    }
};
