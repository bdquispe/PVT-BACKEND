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
        Schema::connection('db_aux')->create('payroll_copy_regionals', function (Blueprint $table) {
            $table->id();
            $table->string('carnet')->comment('Cédula de Identidad');
            $table->string('tipo_aportante')->nullable()->comment('Clase de aportante');
            $table->string('nom')->comment('Primer nombre');
            $table->string('nom2')->nullable()->comment('Segundo nombre');
            $table->string('pat')->nullable()->comment('Apellido paterno');
            $table->string('mat')->nullable()->comment('Apellido materno');
            $table->string('ap_casada')->nullable()->comment('Apellido de casada');
            $table->string('recibo')->nullable()->comment('Número de recibo de pago');
            $table->date('fecha_deposito')->nullable()->comment('Fecha de depósito de pago');
            $table->decimal('total_depositado')->default(0)->comment('Total depositado');
            $table->integer('mes')->comment('Mes del periodo de aporte');
            $table->integer('a_o')->comment('Año del periodo de aporte');
            $table->decimal('total_pension')->default(0)->comment('Total pensión');
            $table->decimal('renta_dignidad')->default(0)->comment('Renta dignidad');
            $table->decimal('cotizable')->default(0)->comment('Cotizable');
            $table->decimal('aporte')->default(0)->comment('Monto de aporte');
            $table->decimal('porcentaje_aporte')->default(0)->comment('Porcentaje de aporte');
            $table->text('error_message')->nullable()->comment('Mensaje del error');
            $table->unsignedBigInteger('affiliate_id')->nullable()->comment('Id del afiliado titular');
            $table->enum('state', ['accomplished','unrealized','validated'])->default('unrealized')->comment('Estado si fue encontrado o no encontrado');
            $table->string('criteria')->nullable()->comment('criterio de identificación del afiliado');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
    */
    public function down(): void
    {
        Schema::connection('db_aux')->dropIfExists('payroll_copy_regionals');
    }
};
