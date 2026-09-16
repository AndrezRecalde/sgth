<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cálculo del viático pasa a ser el que aplica Gestión Financiera.
 *
 * Tres cambios en los datos:
 *
 * 1. `total_dias` pasa a llamarse `noches`. Guardaba los días calendario, el
 *    de regreso incluido, y se cobraban todos; se pagan las noches de
 *    pernocte, que es lo que el nombre dice ahora.
 *
 * 2. Las tarifas de subsistencia salen del catálogo —no existen— y entran las
 *    del exterior, que estaban escritas en el código del servicio de estados.
 *
 * 3. La liquidación guarda el resultado del cálculo: lo justificado dentro del
 *    70 %, lo reconocido al servidor y un saldo único con signo. Reemplaza a
 *    `diferencia_devolver`, que solo sabía de devoluciones y dejaba sin
 *    registrar lo que la institución debía pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->renameColumn('total_dias', 'noches');
        });

        // Los viáticos ya registrados tenían un día de más.
        DB::statement("
            UPDATE viaticos
            SET noches = GREATEST(
                DATE_PART('day', DATE_TRUNC('day', datetime_llegada) - DATE_TRUNC('day', datetime_salida)),
                0
            )
            WHERE datetime_salida IS NOT NULL AND datetime_llegada IS NOT NULL
        ");

        DB::table('tarifas_viatico')->where('tipo_tarifa', 'subsistencia')->delete();

        foreach ([['servidor', 185.00], ['autoridad', 220.00]] as [$nivel, $valor]) {
            DB::table('tarifas_viatico')->insertOrIgnore([
                'zona'         => 'exterior',
                'nivel'        => $nivel,
                'tipo_tarifa'  => 'con_pernocte',
                'valor_diario' => $valor,
                'pais_destino' => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            $table->decimal('total_justificado', 10, 2)->default(0)->after('total_facturas');
            $table->decimal('monto_reconocido', 10, 2)->default(0)->after('total_justificado');
            $table->decimal('saldo', 10, 2)->default(0)->after('monto_reconocido');
        });

        // Las liquidaciones existentes se rehacen con la regla nueva, para que
        // la pantalla y el PDF no muestren dos cuentas distintas.
        DB::statement('
            UPDATE liquidaciones_viatico AS l
            SET total_justificado = LEAST(l.total_facturas, ROUND(v.monto_calculado * 0.70, 2)),
                monto_reconocido  = LEAST(l.total_facturas, ROUND(v.monto_calculado * 0.70, 2))
                                    + (v.monto_calculado - ROUND(v.monto_calculado * 0.70, 2)),
                saldo             = LEAST(l.total_facturas, ROUND(v.monto_calculado * 0.70, 2))
                                    + (v.monto_calculado - ROUND(v.monto_calculado * 0.70, 2))
                                    - v.monto_anticipo
            FROM viaticos AS v
            WHERE v.id = l.viatico_id
        ');

        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            $table->dropColumn('diferencia_devolver');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            $table->decimal('diferencia_devolver', 8, 2)->default(0.00)->after('total_facturas');
        });

        DB::statement('
            UPDATE liquidaciones_viatico
            SET diferencia_devolver = GREATEST(-saldo, 0)
        ');

        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            $table->dropColumn(['total_justificado', 'monto_reconocido', 'saldo']);
        });

        DB::table('tarifas_viatico')->where('zona', 'exterior')->delete();

        foreach ([
            ['dentro_provincia', 'servidor', 40.00],
            ['fuera_provincia', 'servidor', 40.00],
            ['dentro_provincia', 'autoridad', 65.00],
            ['fuera_provincia', 'autoridad', 65.00],
        ] as [$zona, $nivel, $valor]) {
            DB::table('tarifas_viatico')->insertOrIgnore([
                'zona'         => $zona,
                'nivel'        => $nivel,
                'tipo_tarifa'  => 'subsistencia',
                'valor_diario' => $valor,
                'pais_destino' => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        DB::statement("
            UPDATE viaticos
            SET noches = noches + 1
            WHERE datetime_salida IS NOT NULL AND datetime_llegada IS NOT NULL
        ");

        Schema::table('viaticos', function (Blueprint $table) {
            $table->renameColumn('noches', 'total_dias');
        });
    }
};
