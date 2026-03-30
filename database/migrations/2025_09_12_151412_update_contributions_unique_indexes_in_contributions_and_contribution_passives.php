<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // contributions
        Schema::table('contributions', function ($table) {
            $table->dropUnique('contributions_affiliate_id_month_year_unique');
        });

        DB::statement('
            CREATE UNIQUE INDEX contributions_affiliate_month_active_unique
            ON contributions (affiliate_id, month_year)
            WHERE deleted_at IS NULL
        ');

        // contribution_passives
        Schema::table('contribution_passives', function ($table) {
            $table->dropUnique('contribution_passives_affiliate_id_month_year_unique');
        });

        DB::statement('
            CREATE UNIQUE INDEX contribution_passives_affiliate_month_active_unique
            ON contribution_passives (affiliate_id, month_year)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        // contributions
        DB::statement('DROP INDEX IF EXISTS contributions_affiliate_month_active_unique');

        Schema::table('contributions', function ($table) {
            $table->unique(['affiliate_id', 'month_year'], 'contributions_affiliate_id_month_year_unique');
        });

        // contribution_passives
        DB::statement('DROP INDEX IF EXISTS contribution_passives_affiliate_month_active_unique');

        Schema::table('contribution_passives', function ($table) {
            $table->unique(['affiliate_id', 'month_year'], 'contribution_passives_affiliate_id_month_year_unique');
        });
    }
};

