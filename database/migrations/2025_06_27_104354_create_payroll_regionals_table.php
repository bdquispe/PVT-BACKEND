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
        Schema::create('payroll_regionals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id')->unsigned()->comment('Id del afiliado titular'); // Id del afiliado titular
            $table->foreign('affiliate_id')->references('id')->on('affiliates');
            $table->string('identity_card')->nullable()->comment('Cédula de Identidad');
            $table->string('rent_class')->nullable()->comment('Clase de aportante');
            $table->string('first_name')->comment('Primer nombre');
            $table->string('second_name')->nullable()->comment('Segundo nombre');
            $table->string('last_name')->nullable()->comment('Apellido paterno');
            $table->string('mothers_last_name')->nullable()->comment('Apellido materno');
            $table->string('surname_husband')->nullable()->comment('Apellido de casada');
            $table->string('voucher_number')->nullable()->comment('Número de recibo de pago');
            $table->date('payment_date')->nullable()->comment('Fecha de depósito de pago');
            $table->decimal('payment_total', 13, 2)->comment('Total depositado');
            $table->integer('month')->comment('Mes del periodo de aporte');
            $table->integer('year')->comment('Año del periodo de aporte');
            $table->decimal('total_pension', 13, 2)->comment('Total pensión');
            $table->decimal('dignity_rent', 13, 2)->comment('Renta dignidad');
            $table->decimal('quotable', 13, 2)->comment('Cotizable');
            $table->decimal('contribution', 13, 2)->comment('Monto de aporte');
            $table->decimal('contribution_percentage', 13, 2)->comment('Porcentaje de aporte');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_regionals');
    }
};
