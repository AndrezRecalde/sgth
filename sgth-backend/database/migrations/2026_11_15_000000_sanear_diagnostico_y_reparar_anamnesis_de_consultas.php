<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\VersionConsultaMedica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

/**
 * Arregla lo que dejó la lista equivocada de campos HTML.
 *
 * `ConsultaMedica::CAMPOS_HTML` decía `enfermedad_actual` en lugar de
 * `diagnostico_detallado`, y la migración de saneado del 2026-09-05 la usó
 * igual. Eso dejó dos cosas que corregir en lo ya guardado:
 *
 * - **El diagnóstico detallado nunca se saneó**, y la pantalla lo pinta como
 *   HTML: lo que hubiera entrado por la API se ejecutaría al abrirlo. Se sanea
 *   el que es HTML y se escapa el que es texto (consultas de antes del editor).
 * - **La anamnesis se saneó siendo texto.** El saneador codificó `+`, `=`, `@`
 *   o `&` como entidades (`&#43;`…) que la pantalla enseña tal cual. Se
 *   deshacen. Lo que no se puede recuperar es lo que el saneador tiró por
 *   parecerle una etiqueta, como el `<` de «TA < 140».
 *
 * Las dos cosas, también en las versiones archivadas: se pintan igual al ver
 * qué decía una consulta antes de corregirla.
 *
 * Por Eloquent y no por SQL porque los campos van cifrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        ConsultaMedica::withTrashed()->chunkById(200, function ($consultas) {
            $consultas->each(fn (ConsultaMedica $c) => $this->reparar($c));
        });

        VersionConsultaMedica::query()->chunkById(200, function ($versiones) {
            $versiones->each(fn (VersionConsultaMedica $v) => $this->reparar($v));
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás a propósito: devolver el diagnóstico sin sanear
        // sería reponer el problema.
    }

    private function reparar(Model $registro): void
    {
        $cambios = [];

        $diagnostico = $registro->diagnostico_detallado;
        if (is_string($diagnostico)) {
            $seguro = $this->diagnosticoSeguro($diagnostico);
            if ($seguro !== $diagnostico) {
                $cambios['diagnostico_detallado'] = $seguro;
            }
        }

        $anamnesis = $registro->enfermedad_actual;
        if (is_string($anamnesis) && str_contains($anamnesis, '&')) {
            $texto = $this->sinEntidades($anamnesis);
            if ($texto !== $anamnesis) {
                $cambios['enfermedad_actual'] = $texto;
            }
        }

        if ($cambios) {
            // Sin tocar `updated_at`: es higiene del sistema, no una
            // corrección del médico, y no debe parecerlo.
            $registro->timestamps = false;
            $registro->update($cambios);
        }
    }

    /**
     * El HTML se sanea. El texto se escapa, deshaciendo antes las entidades
     * que pudiera traer: en una base donde la migración del 2026-09-05 corra
     * ya con la lista nueva, el diagnóstico en texto llegaría aquí saneado, y
     * escaparlo sin más dejaría `&amp;#43;` en vez de `+`.
     */
    private function diagnosticoSeguro(string $valor): string
    {
        return str_starts_with(ltrim($valor), '<')
            ? ConsultaMedica::comoHtml($valor)
            : ConsultaMedica::comoHtml($this->sinEntidades($valor));
    }

    private function sinEntidades(string $valor): string
    {
        return html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
};
