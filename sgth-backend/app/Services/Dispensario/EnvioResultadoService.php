<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\EstadoEnvioResultado;
use App\Enums\EstadoItemOrdenExamen;
use App\Enums\EstadoRevisionResultado;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\EnvioResultado;
use App\Models\Dispensario\ItemOrdenExamen;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Las órdenes de exámenes desde el Portal (fase 6 de docs/diseno-examenes-medicos.md,
 * 5.7): el servidor ve las suyas, baja el impreso y envía lo que le dio el
 * laboratorio. Nada de lo que envía cuenta hasta que un médico lo valida (D2).
 *
 * Siempre las del servidor de la cuenta: las de otro responden 404, como si no
 * existieran. Las de sus familiares tampoco, porque su historia no lleva su
 * `servidor_id` (D9: «solo lo suyo»).
 */
final class EnvioResultadoService
{
    /** El mismo disco privado que los resultados: es un dato de salud. */
    private const DISCO = 'local';

    public function __construct(private readonly OrdenExamenServiceInterface $ordenes) {}

    /** Las órdenes del servidor, de cualquier historia suya. */
    public function ordenesDelServidor(int $servidorId): Builder
    {
        return OrdenExamen::query()
            ->whereHas('historiaClinica', fn (Builder $q) => $q->where('servidor_id', $servidorId));
    }

    /** Una de las suyas, o 404. */
    public function ordenDeLaCuenta(User $usuario, int $ordenId): OrdenExamen
    {
        return $this->ordenesDelServidor((int) ($usuario->servidor_id ?? 0))->findOrFail($ordenId);
    }

    /** Uno de los suyos, o 404. */
    public function envioDeLaCuenta(User $usuario, int $envioId): EnvioResultado
    {
        return EnvioResultado::query()
            ->whereHas('ordenExamen.historiaClinica', fn (Builder $q) => $q->where('servidor_id', $usuario->servidor_id ?? 0))
            ->findOrFail($envioId);
    }

    /**
     * Envía el archivo del laboratorio contra una orden suya, diciendo qué
     * exámenes cubre. Con la orden bloqueada, como al adjuntar en el
     * Dispensario: entre la lectura y la escritura pudo llegar el resultado.
     *
     * @param array{items: list<int>, fecha_resultado: string, comentario?: ?string} $datos
     */
    public function enviar(User $usuario, int $ordenId, array $datos, UploadedFile $archivo): EnvioResultado
    {
        $orden = $this->ordenDeLaCuenta($usuario, $ordenId);

        $ruta = $archivo->store("resultados-medicos/portal/{$orden->id}", self::DISCO);
        if (! $ruta) {
            throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
        }

        try {
            return DB::transaction(function () use ($usuario, $orden, $datos, $ruta) {
                $orden = OrdenExamen::lockForUpdate()->findOrFail($orden->id);
                $itemIds = array_values(array_unique(array_map('intval', $datos['items'])));

                $this->exigirQueSePuedaEnviar($orden, $itemIds, $datos['fecha_resultado']);

                $comentario = trim((string) ($datos['comentario'] ?? ''));

                return EnvioResultado::create([
                    'orden_examen_id' => $orden->id,
                    'enviado_por'     => $usuario->id,
                    'items'           => $itemIds,
                    'fecha_resultado' => $datos['fecha_resultado'],
                    'comentario'      => $comentario !== '' ? $comentario : null,
                    'archivo'         => $ruta,
                    'estado'          => EstadoEnvioResultado::POR_VALIDAR,
                ]);
            });
        } catch (Throwable $e) {
            // Sin registro, el archivo no lo encontraría nadie.
            Storage::disk(self::DISCO)->delete($ruta);

            throw $e;
        }
    }

    /** Lo retira mientras espera: todavía no es nada. */
    public function retirar(User $usuario, int $envioId): EnvioResultado
    {
        $envio = $this->envioDeLaCuenta($usuario, $envioId);

        if ($envio->estado !== EstadoEnvioResultado::POR_VALIDAR) {
            throw new ReglaNegocioException(
                'Este envío ya está ' . mb_strtolower($envio->estado->etiqueta()) . ': ya no se retira.'
            );
        }

        $envio->update(['estado' => EstadoEnvioResultado::RETIRADO]);

        return $envio;
    }

    // ── El Dispensario ───────────────────────────────────────────

    /**
     * Un médico lo acepta (D2): nace el resultado de la historia clínica,
     * contra la orden y por revisar, con el mismo archivo. Los exámenes los
     * confirma él; lo que marcó el servidor era una propuesta.
     *
     * El resultado queda a nombre de quien valida —es quien lo registra en la
     * historia— y en la consulta que pidió la orden, donde lo buscará quien la
     * atendió. Si la orden se cerró mientras esperaba, falla con el mismo
     * mensaje que una subida del Dispensario y el envío sigue por validar.
     *
     * @param array{items: list<int>, tipo: string, descripcion: string, fecha_resultado: string} $datos
     */
    public function validar(int $envioId, array $datos, User $medico): EnvioResultado
    {
        return DB::transaction(function () use ($envioId, $datos, $medico) {
            $envio = EnvioResultado::with('ordenExamen')->lockForUpdate()->findOrFail($envioId);
            $this->exigirPorValidar($envio);

            $resultado = ResultadoMedico::create([
                'historia_clinica_id' => $envio->ordenExamen->historia_clinica_id,
                'consulta_medica_id'  => $envio->ordenExamen->consulta_medica_id,
                'orden_examen_id'     => $envio->orden_examen_id,
                'subido_por'          => $medico->id,
                'tipo'                => $datos['tipo'],
                'descripcion'         => trim($datos['descripcion']),
                'fecha_resultado'     => $datos['fecha_resultado'],
                'archivo'             => $envio->archivo,
                'estado_revision'     => EstadoRevisionResultado::POR_REVISAR,
            ]);

            $this->ordenes->adjuntarResultado($resultado, array_map('intval', $datos['items']));

            $envio->update([
                'estado'              => EstadoEnvioResultado::VALIDADO,
                'atendido_por'        => $medico->id,
                'atendido_en'         => now(),
                'resultado_medico_id' => $resultado->id,
            ]);

            return $envio;
        });
    }

    /** No es del paciente, no se lee o ya estaba: vuelve al servidor con el motivo. */
    public function rechazar(int $envioId, string $motivo, User $medico): EnvioResultado
    {
        return DB::transaction(function () use ($envioId, $motivo, $medico) {
            $envio = EnvioResultado::lockForUpdate()->findOrFail($envioId);
            $this->exigirPorValidar($envio);

            $envio->update([
                'estado'         => EstadoEnvioResultado::RECHAZADO,
                'motivo_rechazo' => $motivo,
                'atendido_por'   => $medico->id,
                'atendido_en'    => now(),
            ]);

            return $envio;
        });
    }

    private function exigirPorValidar(EnvioResultado $envio): void
    {
        if ($envio->estado !== EstadoEnvioResultado::POR_VALIDAR) {
            throw new ReglaNegocioException(
                'Este envío ya está ' . mb_strtolower($envio->estado->etiqueta()) . ': solo se valida o se rechaza uno por validar.'
            );
        }
    }

    public function descargar(EnvioResultado $envio): StreamedResponse
    {
        $disco = Storage::disk(self::DISCO);

        if (! $disco->exists($envio->archivo)) {
            throw new ReglaNegocioException('El archivo de este envío ya no está disponible.');
        }

        return $disco->response(
            $envio->archivo,
            "envio-{$envio->id}." . pathinfo($envio->archivo, PATHINFO_EXTENSION),
        );
    }

    /**
     * La orden espera resultados; los exámenes son suyos y siguen sin
     * resultado, y ninguno espera ya otro envío. Uno marcado como no realizado
     * se admite: el paciente se lo hizo después de todo, como en el Dispensario.
     *
     * @param list<int> $itemIds
     */
    private function exigirQueSePuedaEnviar(OrdenExamen $orden, array $itemIds, string $fecha): void
    {
        if (! $orden->estado->abierta()) {
            throw new ReglaNegocioException(
                "La orden {$orden->folio} está {$orden->estado->value}: ya no recibe resultados."
            );
        }

        if ($orden->fecha_solicitud && $orden->fecha_solicitud->gt($fecha)) {
            throw new ReglaNegocioException(
                'El resultado no puede ser anterior a la orden, del ' . $orden->fecha_solicitud->format('d/m/Y') . '.'
            );
        }

        $items = $orden->items()->with('examen:id,nombre')->whereKey($itemIds)->get();

        if ($items->count() !== count($itemIds)) {
            throw new ReglaNegocioException("Hay exámenes que no son de la orden {$orden->folio}.");
        }

        $recibidos = $items->where('estado', EstadoItemOrdenExamen::RECIBIDO);
        if ($recibidos->isNotEmpty()) {
            throw new ReglaNegocioException(
                'El Dispensario ya tiene el resultado de ' . $this->nombres($recibidos) . '.'
            );
        }

        $enEspera = $orden->envios()
            ->where('estado', EstadoEnvioResultado::POR_VALIDAR)
            ->pluck('items')
            ->flatten()
            ->all();
        $repetidos = $items->whereIn('id', $enEspera);
        if ($repetidos->isNotEmpty()) {
            throw new ReglaNegocioException(
                'Ya envió el resultado de ' . $this->nombres($repetidos)
                . ' y espera validación. Si se equivocó de archivo, retire ese envío antes.'
            );
        }
    }

    /** @param \Illuminate\Support\Collection<int, ItemOrdenExamen> $items */
    private function nombres($items): string
    {
        return $items->map(fn (ItemOrdenExamen $i) => $i->examen?->nombre)->implode(', ');
    }
}
