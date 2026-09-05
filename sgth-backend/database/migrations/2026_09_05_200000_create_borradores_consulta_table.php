<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que el médico lleva escrito antes de guardar la consulta.
     *
     * Hasta ahora la nota vivía solo en el formulario: una sesión caducada, un
     * navegador cerrado por error o un equipo que se reinicia se llevaban por
     * delante una anamnesis entera, con el paciente delante y sin forma de
     * recuperarla.
     *
     * Va al servidor y no al navegador a propósito. Los campos clínicos de
     * `consultas_medicas` se guardan cifrados; dejar ese mismo texto en claro en
     * el `localStorage` de un equipo de consultorio —compartido, y donde queda
     * hasta que alguien lo borre— sería deshacer esa decisión por la puerta de
     * atrás. Aquí el contenido va cifrado igual, y el borrador es de quien lo
     * escribe: nadie más lo lee.
     */
    public function up(): void
    {
        Schema::create('borradores_consulta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agenda_medica_id')
                ->constrained('agendas_medicas')
                ->cascadeOnDelete();

            $table->foreignId('medico_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // El formulario entero, cifrado. Es un borrador: su forma la manda
            // la pantalla y cambia con ella, así que no se reparte en columnas
            // que habría que migrar cada vez que se añada un campo.
            $table->text('contenido');

            $table->timestamps();

            // Un borrador por turno y por médico: el de cada quien es suyo.
            $table->unique(['agenda_medica_id', 'medico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borradores_consulta');
    }
};
