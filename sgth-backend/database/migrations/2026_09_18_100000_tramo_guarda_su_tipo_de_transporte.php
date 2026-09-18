<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| El tramo guarda su tipo de transporte, y la empresa pasa a ser opcional.
|
| Antes el tramo solo guardaba la empresa y de ella sacaba el tipo. Un
| vehículo institucional, uno particular, un taxi o una lancha no tienen
| empresa en el catálogo, así que no había forma de registrarlos: la columna
| era obligatoria y el backend la exigía.
|
| La empresa sigue siendo obligatoria cuando el tipo tiene empresas (bus,
| avión); eso lo valida el controlador.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tramos_viatico', 'catalogo_transporte_id')) {
            Schema::table('tramos_viatico', function (Blueprint $table) {
                $table->foreignId('catalogo_transporte_id')
                    ->nullable()
                    ->after('destino_ciudad')
                    ->constrained('catalogo_transportes');
            });
        }

        // Los tramos que ya existen toman el tipo de su empresa.
        DB::statement(<<<'SQL'
            UPDATE tramos_viatico
               SET catalogo_transporte_id = e.catalogo_transporte_id
              FROM empresas_transporte e
             WHERE e.id = tramos_viatico.empresa_transporte_id
               AND tramos_viatico.catalogo_transporte_id IS NULL
        SQL);

        DB::statement('ALTER TABLE tramos_viatico ALTER COLUMN catalogo_transporte_id SET NOT NULL');
        DB::statement('ALTER TABLE tramos_viatico ALTER COLUMN empresa_transporte_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Un tramo sin empresa no cabe en el esquema anterior: se borra, con
        // su autorización de vuelo si la tuviera.
        DB::table('autorizaciones_vuelo')
            ->whereIn('tramo_viatico_id', DB::table('tramos_viatico')->whereNull('empresa_transporte_id')->select('id'))
            ->delete();
        DB::table('tramos_viatico')->whereNull('empresa_transporte_id')->delete();

        DB::statement('ALTER TABLE tramos_viatico ALTER COLUMN empresa_transporte_id SET NOT NULL');

        if (Schema::hasColumn('tramos_viatico', 'catalogo_transporte_id')) {
            Schema::table('tramos_viatico', function (Blueprint $table) {
                $table->dropConstrainedForeignId('catalogo_transporte_id');
            });
        }
    }
};
