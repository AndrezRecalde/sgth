<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * La identidad institucional (docs/diseno-comunicacion-social.md, 4; fase 2.1):
 * las versiones de cada variante del logo, con la fecha desde la que rigen, y
 * los datos de la institución.
 *
 * Siembra como primera versión los archivos que el sistema usa hoy, para que
 * el despliegue no cambie nada: el logo horizontal de los PDF, la hoja
 * membretada del informe de actividades y el isotipo del sidebar (copiado del
 * frontend a `resources/identidad/`, porque el backend no ve esa carpeta en
 * producción). Y los datos que hoy están escritos en el código.
 */
return new class extends Migration
{
    /** Variante => archivo de hoy, relativo a la raíz del backend. */
    private const ARCHIVOS_INICIALES = [
        'isotipo'              => 'resources/identidad/isotipo.png',
        'imagotipo_horizontal' => 'public/images/logo-gadpe.png',
        'hoja_membretada'      => 'public/images/membrete-gadpe.jpg',
    ];

    /** Los que hoy viven en PdfRecetaService::INSTITUCION, el sidebar y la hoja membretada. */
    private const DATOS_INICIALES = [
        'nombre_oficial' => 'GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS',
        'siglas'         => 'GADPE',
        'nombre_corto'   => 'Esmeraldas',
        'ruc'            => '0860000160001',
        'direccion'      => '10 de Agosto entre Bolívar y Pedro Vicente Maldonado, Esmeraldas',
        'telefono'       => '06-2721433',
        'sitio_web'      => 'www.gadpe.gob.ec',
    ];

    public function up(): void
    {
        Schema::create('recursos_identidad', function (Blueprint $table) {
            $table->id();
            $table->string('variante', 40);
            $table->string('archivo');
            $table->string('mime', 40);
            $table->unsignedInteger('ancho');
            $table->unsignedInteger('alto');
            $table->unsignedInteger('peso');
            // Desde qué día rige. Un documento toma la versión vigente a su fecha.
            $table->date('vigente_desde');
            $table->string('origen', 20);
            $table->foreignId('reutiliza_id')->nullable()->constrained('recursos_identidad')->nullOnDelete();
            $table->string('nota', 255)->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            // Solo una versión programada, que todavía no rigió, se puede anular.
            $table->timestamp('anulada_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['variante', 'vigente_desde']);
        });

        Schema::create('datos_institucionales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_oficial', 200);
            $table->string('siglas', 20);
            $table->string('nombre_corto', 60);
            $table->string('ruc', 13);
            $table->string('direccion', 255);
            $table->string('telefono', 40);
            $table->string('sitio_web', 120)->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->sembrar();
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_institucionales');
        Schema::dropIfExists('recursos_identidad');
    }

    private function sembrar(): void
    {
        $disco = Storage::disk('local');
        $hoy = now()->toDateString();

        foreach (self::ARCHIVOS_INICIALES as $variante => $relativo) {
            $origen = base_path($relativo);
            $medidas = is_file($origen) ? @getimagesize($origen) : false;
            if ($medidas === false) {
                continue;
            }

            $destino = "identidad/inicial/{$variante}.".pathinfo($origen, PATHINFO_EXTENSION);
            $disco->put($destino, (string) file_get_contents($origen));

            DB::table('recursos_identidad')->insert([
                'variante'      => $variante,
                'archivo'       => $destino,
                'mime'          => $medidas['mime'],
                'ancho'         => $medidas[0],
                'alto'          => $medidas[1],
                'peso'          => filesize($origen),
                'vigente_desde' => $hoy,
                'origen'        => 'carga_inicial',
                'nota'          => 'El archivo que usaba el sistema antes del módulo de identidad.',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        DB::table('datos_institucionales')->insert([
            ...self::DATOS_INICIALES,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
