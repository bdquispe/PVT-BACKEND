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

        schema::connection('db_aux')->table('payroll_copy_commands', function (Blueprint $table) {
            $table->integer('dtr')->default(30)->after('sex')->comment('Dias trabajados');
        });
        Schema::table('payroll_commands', function(Blueprint $table){
            $table->integer('days_worked')->default(30)->comment('Dias trabajados');
        });
        Schema::table('contributions', function(Blueprint $table){
            $table->integer('days_worked')->default(30)->comment('Dias trabajados');
        });
        Schema::table('reimbursements', function(Blueprint $table){
            $table->integer('days_worked')->default(30)->comment('Dias trabajados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('db_aux')->table('payroll_copy_commands', function (Blueprint $table) {
            $table->dropColumn('dtr');
        });
        Schema::table('payroll_commands', function(Blueprint $table){
            $table->dropColumn('days_worked');
        });
        Schema::table('contributions', function(Blueprint $table){
            $table->dropColumn('days_worked');
        });
        Schema::table('reimbursements', function(Blueprint $table){
            $table->dropColumn('days_worked');
        });
    }
};
