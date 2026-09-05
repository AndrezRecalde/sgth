<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `unaccent`, para poder buscar el CIE-10 sin teclear las tildes.
     *
     * 2325 de las 8918 descripciones del catálogo llevan tilde o eñe, y la
     * búsqueda las comparaba tal cual: quien escribía «migrana» o «hipertension»
     * —que es como se teclea con un paciente delante— no encontraba nada.
     *
     * La extensión ya estaba creada en desarrollo, pero no constaba en ninguna
     * migración: una instalación nueva, o la base de pruebas, se quedaba sin
     * ella y la búsqueda reventaba en vez de fallar de menos.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        // No se retira: otras búsquedas pueden haber empezado a apoyarse en
        // ella, y quitarla las rompería sin avisar.
    }
};
