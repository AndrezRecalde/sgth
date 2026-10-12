<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\User;
use ZipArchive;

/**
 * Lo que se hace con el lote como conjunto (diseño, 8.5; fase 7.2): hasta aquí
 * los documentos se descargaban y las acciones se notificaban de una en una,
 * y un lote de cuarenta obreros eran cuarenta descargas.
 *
 * - Los documentos, en un ZIP: lo que sigue en trámite, con la marca BORRADOR
 *   de la vista previa (fase 6.2), para revisarlo antes de registrar; lo
 *   registrado, el definitivo, para imprimirlo y firmarlo.
 * - La notificación por el portal y el correo de lo registrado que no se
 *   notificó (fase 6.3a), cada acto con su correo y su acuse. En persona
 *   sigue siendo de una en una: el «Recibí» o la negativa con testigo.
 */
class OperacionDelLoteService
{
    public function __construct(
        private readonly AccionPersonalPdfService $pdf,
        private readonly MovimientoPersonalStateService $estados,
    ) {
    }

    /**
     * El ZIP con el documento de cada acto. Devuelve la ruta del archivo
     * temporal: quien lo envía lo borra después.
     */
    public function documentos(LoteAccion $lote, ?User $usuario): string
    {
        $actos = $lote->movimientos()
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->with('servidor:id,cedula,apellido,nombre')
            ->orderBy('id')
            ->get();

        $ruta = tempnam(sys_get_temp_dir(), 'lote');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);

        $incluidos = 0;
        $omitidos = [];

        foreach ($actos as $i => $acto) {
            $enTramite = in_array($acto->estado, [EstadoAccionPersonal::BORRADOR, EstadoAccionPersonal::SUSCRITA], true);

            // La vista previa de lo propio no la tiene quien se la prepara: la
            // revisa otra persona de Talento Humano (fase 6.2).
            if ($enTramite && $usuario && $acto->esSobre($usuario)) {
                $omitidos[] = "{$this->nombre($acto)}: es su propia acción, la revisa otra persona de Talento Humano.";

                continue;
            }

            try {
                ['content' => $contenido] = $this->pdf->generarContent($acto->id, $enTramite);
            } catch (ReglaNegocioException $e) {
                $omitidos[] = "{$this->nombre($acto)}: {$e->getMessage()}";

                continue;
            }

            $zip->addFromString($this->archivo($i + 1, $acto, $enTramite), $contenido);
            $incluidos++;
        }

        if ($omitidos !== []) {
            $zip->addFromString('omitidos.txt', "Estos actos del lote #{$lote->id} no van en el ZIP:\n\n- ".implode("\n- ", $omitidos)."\n");
        }

        $zip->close();

        if ($incluidos === 0) {
            @unlink($ruta);

            throw new ReglaNegocioException('El lote no tiene documentos que descargar.');
        }

        return $ruta;
    }

    /**
     * Notifica por el portal lo registrado que no se notificó. Cada uno por su
     * lado: si uno no se puede, los demás siguen, y se dice por qué.
     *
     * @return array{notificados: int, omitidos: list<array{movimiento_id: int, nombre: string, motivo: string}>}
     */
    public function notificar(LoteAccion $lote, User $usuario): array
    {
        // Cada acto manda su correo: con cientos, más que el límite de una
        // petición. Solo se sube: sin límite (la consola, las pruebas), poner
        // 300 lo acortaría.
        $limite = (int) ini_get('max_execution_time');
        if ($limite > 0 && $limite < 300) {
            @set_time_limit(300);
        }

        $notificados = 0;
        $omitidos = [];

        $actos = $lote->movimientos()
            ->where('estado', EstadoAccionPersonal::REGISTRADA->value)
            ->with('servidor:id,cedula,apellido,nombre')
            ->orderBy('id')
            ->get();

        foreach ($actos as $acto) {
            $motivo = match (true) {
                ! ($acto->instrumento ?? InstrumentoActo::ACCION_PERSONAL)->seNotifica() =>
                    'No se notifica: aparece en su portal desde que se registra.',
                $acto->esSobre($usuario) => 'Es su propia acción: la notifica otra persona de Talento Humano.',
                default => null,
            };

            if ($motivo === null) {
                try {
                    $this->estados->transicionar($acto, EstadoAccionPersonal::NOTIFICADA, ['notificacion_medio' => 'portal']);
                    $notificados++;

                    continue;
                } catch (ReglaNegocioException $e) {
                    $motivo = $e->getMessage();
                }
            }

            $omitidos[] = ['movimiento_id' => $acto->id, 'nombre' => $this->nombre($acto), 'motivo' => $motivo];
        }

        return ['notificados' => $notificados, 'omitidos' => $omitidos];
    }

    /** «03_AP-2026-0123_0912345678.pdf», o «borrador» en lo que sigue en trámite. */
    private function archivo(int $orden, MovimientoPersonal $acto, bool $enTramite): string
    {
        $codigo = $enTramite || blank($acto->codigo_registro) ? "borrador-{$acto->id}" : $acto->codigo_registro;

        return sprintf('%02d_%s_%s.pdf', $orden, preg_replace('/[^A-Za-z0-9-]/', '-', (string) $codigo), $acto->servidor?->cedula ?? 'sin-cedula');
    }

    private function nombre(MovimientoPersonal $acto): string
    {
        return OcupantesDelPuesto::nombre($acto->servidor);
    }
}
