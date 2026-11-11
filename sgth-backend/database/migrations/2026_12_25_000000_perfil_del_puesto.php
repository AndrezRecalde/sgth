<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| El perfil del puesto, como la ficha del Manual de Puestos
| (docs/diseno-manual-de-puestos.md, P1; TH, D1 y D2, 2026-11-10).
|
| - `puesto_perfiles`: una fila por puesto, con la misión, las relaciones, el
|   nivel, la instrucción (área), la experiencia (especificidad), la
|   capacitación y el subproceso, como texto (D2, opción B: el puesto sigue en
|   la gestión). Sin estados ni versiones (D6).
| - `puesto_requisitos_instruccion`: una fila por cada alternativa de
|   instrucción del manual, con su experiencia («técnico superior y 6 años, o
|   tercer nivel y 2 años 6 meses»).
| - La misión pasa del cargo al perfil (D1): cada puesto del cargo la recibe, y
|   la columna del cargo, que no mostraba ninguna pantalla, se va.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puesto_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->unique()->constrained('puestos')->cascadeOnDelete();
            $table->text('mision')->nullable();
            $table->text('relaciones')->nullable();
            $table->string('nivel', 20)->nullable();
            $table->text('area_conocimiento')->nullable();
            $table->text('especificidad_experiencia')->nullable();
            $table->text('capacitacion')->nullable();
            $table->string('subproceso', 200)->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE puesto_perfiles ADD CONSTRAINT puesto_perfiles_nivel_check CHECK (nivel IN ('profesional', 'no_profesional'))");

        Schema::create('puesto_requisitos_instruccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_perfil_id')->constrained('puesto_perfiles')->cascadeOnDelete();
            $table->string('nivel_instruccion', 40);
            // «Tercer año o sexto semestre aprobado»: lo que no dice el nivel.
            $table->string('detalle', 200)->nullable();
            $table->unsignedSmallInteger('meses_experiencia')->default(0);
            $table->unsignedSmallInteger('orden');
            $table->timestamps();

            $table->unique(['puesto_perfil_id', 'orden']);
        });
        DB::statement("ALTER TABLE puesto_requisitos_instruccion ADD CONSTRAINT puesto_requisitos_nivel_check CHECK (nivel_instruccion IN ('bachiller', 'tecnico_superior', 'tecnologico_superior', 'estudios_superiores_en_curso', 'tercer_nivel', 'cuarto_nivel'))");

        // La misión de cada cargo, a todos sus puestos.
        DB::statement(<<<'SQL'
            INSERT INTO puesto_perfiles (puesto_id, mision, created_at, updated_at)
            SELECT p.id, c.mision, now(), now()
            FROM puestos p
            JOIN cargos c ON c.id = p.cargo_id
            WHERE c.mision IS NOT NULL AND btrim(c.mision) <> ''
            SQL);

        Schema::table('cargos', fn (Blueprint $table) => $table->dropColumn('mision'));
    }

    public function down(): void
    {
        Schema::table('cargos', fn (Blueprint $table) => $table->text('mision')->nullable());

        // De vuelta al cargo, la de su primer puesto: si dos puestos del mismo
        // cargo tenían misiones distintas, solo una cabe ahí.
        DB::statement(<<<'SQL'
            UPDATE cargos c SET mision = (
                SELECT pp.mision FROM puesto_perfiles pp
                JOIN puestos p ON p.id = pp.puesto_id
                WHERE p.cargo_id = c.id AND pp.mision IS NOT NULL
                ORDER BY p.id LIMIT 1
            )
            SQL);

        Schema::dropIfExists('puesto_requisitos_instruccion');
        Schema::dropIfExists('puesto_perfiles');
    }
};
