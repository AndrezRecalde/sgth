<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recodifica el orgánico con códigos jerárquicos: `GADPE` → `GADPE-01` →
 * `GADPE-01-03`.
 *
 * Los códigos anteriores los generaba el seeder con
 * `substr(slug(nombre), 0, 15) . '-' . rand(10,99)`, y el resultado era
 * `VICEPREFECTURA--19`, `GESTION-DE-TALE-88`, `DESARROLLO-DE-S-17`: truncados
 * a mitad de palabra, con guiones dobles y un sufijo aleatorio que no
 * significa nada. Esos códigos salen impresos en el PDF público del
 * organigrama.
 *
 * El código nuevo dice dónde está la unidad en el árbol. El sufijo aleatorio
 * solo servía para evitar choques, y un secuencial por hermanos hace lo mismo
 * sin perder legibilidad.
 *
 * Seguro de aplicar hoy: nada referencia estos códigos por valor. El único
 * consumidor es `CodigoViaticoService`, que los usa como prefijo de los
 * códigos de viático, y no hay ningún viático emitido.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $raiz = DB::table('unidades_administrativas')
                ->whereNull('unidad_padre_id')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (! $raiz) {
                return;
            }

            // La raíz conserva un código legible sacado de su acrónimo o de su
            // nombre; es el prefijo del que cuelga todo lo demás.
            $codigoRaiz = $this->codigoRaiz($raiz);

            DB::table('unidades_administrativas')
                ->where('id', $raiz->id)
                ->update(['codigo' => $codigoRaiz]);

            $this->recodificarHijos($raiz->id, $codigoRaiz);
        });
    }

    /**
     * La bajada es irreversible en el sentido útil: los códigos anteriores
     * eran aleatorios, así que no hay nada fiel que restaurar. Se deja la
     * estructura intacta y se documenta.
     */
    public function down(): void
    {
        // Sin reverso: los códigos originales llevaban un sufijo `rand()` que
        // no se puede reconstruir. Revertir devolvería códigos inventados,
        // que es peor que quedarse con los jerárquicos.
    }

    private function codigoRaiz(object $raiz): string
    {
        $base = $raiz->acronimo ?: $raiz->nombre;
        $codigo = Str::upper(Str::slug($base, ''));

        return Str::limit($codigo, 10, '') ?: 'INST';
    }

    private function recodificarHijos(int $padreId, string $prefijo): void
    {
        $hijos = DB::table('unidades_administrativas')
            ->where('unidad_padre_id', $padreId)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get();

        $n = 0;

        foreach ($hijos as $hijo) {
            $codigo = sprintf('%s-%02d', $prefijo, ++$n);

            DB::table('unidades_administrativas')
                ->where('id', $hijo->id)
                ->update(['codigo' => $codigo]);

            $this->recodificarHijos($hijo->id, $codigo);
        }
    }
};
