<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Enums\EstadoContrato;
use App\Enums\EstadoCredencial;
use App\Enums\FormatoImpresionCredencial;
use App\Enums\MotivoRevocacionCredencial;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicacion\Credencial;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Estructura\ArbolUnidades;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * La credencial de los servidores (docs/diseno-comunicacion-social.md, 6).
 *
 * Comunicación no la emite: nace sola cuando el servidor tiene vínculo
 * vigente y foto, y se mantiene al día con su vínculo. La sincronizan la foto
 * al quedar vigente, el cambio de vínculo (ContratoServidorService) y la
 * tarea de cada noche, que además la vence con la fecha de fin del contrato.
 *
 * | Estado   | Cuándo                                       | QR           |
 * |----------|----------------------------------------------|--------------|
 * | vigente  | vínculo vigente y la fecha de fin no pasó     | valida       |
 * | vencida  | pasó la fecha de fin; el vínculo sigue abierto| «no vigente» |
 * | revocada | pérdida, robo o datos erróneos                | «no vigente» |
 * | anulada  | el vínculo terminó                           | «no vigente» |
 *
 * Una vencida vuelve a vigente, con el mismo código, si el contrato se
 * prorroga o se renueva sin interrupción: la tarjeta impresa sigue sirviendo,
 * aunque su «Válida hasta» quedó desactualizado. Revocada y anulada no vuelven.
 */
final class CredencialService implements CredencialServiceInterface
{
    private const POR_PAGINA = 24;
    private const MAXIMO_POR_PAGINA = 100;

    private const COLUMNAS_BUSQUEDA = [
        'cedula', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido',
    ];

    public function __construct(private readonly PdfCredencialService $pdf) {}

    // ── El ciclo de vida ────────────────────────────────────────────

    public function sincronizar(int $servidorId): ?Credencial
    {
        return $this->aplicar($servidorId)[0];
    }

    public function conciliar(): array
    {
        $cuentas = ['creadas' => 0, 'vencidas' => 0, 'reactivadas' => 0, 'anuladas' => 0];

        // Quienes pueden tener una: con foto vigente o con una credencial viva.
        Servidor::withTrashed()
            ->where(fn ($q) => $q->whereHas('fotografiaVigente')->orWhereHas('credencialViva'))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$cuentas) {
                $cambio = $this->aplicar($id)[1];

                if ($cambio !== null) {
                    $cuentas[$cambio]++;
                }
            });

        return $cuentas;
    }

    public function revocar(Credencial $credencial, MotivoRevocacionCredencial $motivo, ?string $detalle, User $usuario): array
    {
        $detalle = trim((string) $detalle);

        if ($motivo->pideDetalle() && mb_strlen($detalle) < 5) {
            throw new ReglaNegocioException('Diga por qué se revoca la credencial.');
        }

        return DB::transaction(function () use ($credencial, $motivo, $detalle, $usuario) {
            $this->bloquear($credencial->servidor_id);
            $credencial->refresh();

            if (! $credencial->estado->esViva()) {
                throw new ReglaNegocioException("La credencial ya está {$this->minuscula($credencial->estado)}: no se puede revocar.");
            }

            $credencial->update([
                'estado'             => EstadoCredencial::REVOCADA,
                'revocada_en'        => now(),
                'revocada_por'       => $usuario->id,
                'motivo_revocacion'  => $motivo,
                'detalle_revocacion' => $detalle === '' ? null : $detalle,
            ]);

            [$repuesta] = $this->aplicar($credencial->servidor_id, $credencial);

            return ['revocada' => $credencial, 'repuesta' => $repuesta];
        });
    }

    public function registrarDevolucion(Credencial $credencial, string $fecha, ?string $nota, User $usuario): Credencial
    {
        if ($credencial->estado->esViva()) {
            throw new ReglaNegocioException('La credencial sigue viva: la tarjeta se recibe cuando se anula o se revoca.');
        }

        if ($credencial->devuelta_en !== null) {
            throw new ReglaNegocioException("La tarjeta ya se recibió el {$credencial->devuelta_en->format('d/m/Y')}.");
        }

        if (! $credencial->impresiones()->exists()) {
            throw new ReglaNegocioException('Esta credencial nunca se imprimió: no hay tarjeta que recibir.');
        }

        $nota = trim((string) $nota);

        $credencial->update([
            'devuelta_en'               => $fecha,
            'devolucion_registrada_por' => $usuario->id,
            'devolucion_nota'           => $nota === '' ? null : $nota,
        ]);

        return $credencial;
    }

    // ── Consultas ───────────────────────────────────────────────────

    public function listar(array $filtros): LengthAwarePaginator
    {
        // Quienes trabajan hoy: los demás ya no llevan credencial.
        $query = Servidor::query()->where('estado', true);

        foreach (preg_split('/\s+/', trim((string) ($filtros['buscar'] ?? ''))) as $palabra) {
            if ($palabra === '') {
                continue;
            }
            $query->where(function ($q) use ($palabra) {
                foreach (self::COLUMNAS_BUSQUEDA as $columna) {
                    $q->orWhere($columna, 'ilike', "%{$palabra}%");
                }
            });
        }

        // Una dirección incluye a las unidades que cuelgan de ella.
        if (! empty($filtros['unidad_administrativa_id'])) {
            $query->whereIn(
                'unidad_administrativa_id',
                ArbolUnidades::conDescendientes((int) $filtros['unidad_administrativa_id']),
            );
        }

        $situacion = $filtros['situacion'] ?? null;

        match ($situacion) {
            // Sin credencial porque le falta la foto.
            'sin_foto'     => $query->whereDoesntHave('credencialViva')->whereDoesntHave('fotografiaVigente'),
            'sin_imprimir' => $query->whereHas('credencialViva', fn ($q) => $q->whereDoesntHave('impresiones')),
            'vencida'      => $query->whereHas('credencialViva', fn ($q) => $q->where('estado', EstadoCredencial::VENCIDA->value)),
            // Comparar lo impreso con el expediente no cabe en SQL: se
            // calcula sobre los que tienen tarjeta, y se pagina sobre esos ids.
            'desactualizada', 'al_dia' => $query->whereKey($this->segunLaImpresion(clone $query, $situacion === 'desactualizada')),
            default        => null,
        };

        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAXIMO_POR_PAGINA);

        $pagina = $query->with([
            ...DatosCredencial::RELACIONES_SERVIDOR,
            'credencialViva' => fn ($q) => $q->withCount('impresiones')->with('ultimaImpresion.impresaPor.servidor'),
        ])
            // El id desempata: sin él, dos homónimos se repiten o se pierden entre páginas.
            ->orderBy('apellido')->orderBy('nombre')->orderBy('id')
            ->paginate($porPagina);

        // La credencial ya trae a su servidor: armar sus datos no consulta de nuevo.
        $pagina->getCollection()->each(fn (Servidor $s) => $s->credencialViva?->setRelation('servidor', $s));

        return $pagina;
    }

    public function historial(Servidor $servidor): Collection
    {
        return $servidor->credenciales()
            ->with(['impresiones.impresaPor.servidor', 'revocadaPor.servidor', 'devolucionRegistradaPor.servidor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function deUnidad(int $unidadId): Collection
    {
        return Credencial::query()
            ->where('estado', EstadoCredencial::VIGENTE->value)
            ->whereHas('servidor', fn ($q) => $q
                ->where('estado', true)
                ->whereIn('unidad_administrativa_id', ArbolUnidades::conDescendientes($unidadId))
                ->whereHas('fotografiaVigente'))
            ->with('servidor')
            ->get()
            ->filter(fn (Credencial $c) => $c->estaVigente())
            ->sortBy(fn (Credencial $c) => Str::lower("{$c->servidor->apellido} {$c->servidor->nombre}"))
            ->values();
    }

    // ── Impresión ───────────────────────────────────────────────────

    public function imprimir(Collection $credenciales, FormatoImpresionCredencial $formato, User $usuario): string
    {
        if ($credenciales->isEmpty()) {
            throw new ReglaNegocioException('No hay credenciales para imprimir.');
        }

        $credenciales = (new EloquentCollection($credenciales->values()->all()))
            ->loadMissing(['servidor' => fn ($q) => $q->with(DatosCredencial::RELACIONES_SERVIDOR)]);

        $noImprimibles = $credenciales->reject(fn (Credencial $c) => $c->estaVigente() && $c->servidor->fotografiaVigente);
        if ($noImprimibles->isNotEmpty()) {
            $nombres = $noImprimibles->map(fn (Credencial $c) => trim("{$c->servidor->nombre} {$c->servidor->apellido}"))->implode(', ');

            throw new ReglaNegocioException("Solo se imprimen credenciales vigentes con foto. No lo están las de: {$nombres}.");
        }

        $tarjetas = $credenciales->map(fn (Credencial $c) => $this->tarjeta($c))->values();
        $pdf = $this->pdf->generar($tarjetas, $formato);

        // Se registra después de generar el PDF: si falla, no queda una
        // impresión que nunca salió.
        $lote = $credenciales->count() > 1 ? (string) Str::uuid() : null;

        DB::transaction(function () use ($credenciales, $tarjetas, $formato, $usuario, $lote) {
            $credenciales->values()->each(fn (Credencial $c, int $i) => $c->impresiones()->create([
                'impresa_por'    => $usuario->id,
                'formato'        => $formato,
                'lote'           => $lote,
                'datos_impresos' => $tarjetas[$i]['datos'],
            ]));
        });

        return $pdf;
    }

    public function pdfDigital(Credencial $credencial): string
    {
        $credencial->loadMissing(['servidor' => fn ($q) => $q->with(DatosCredencial::RELACIONES_SERVIDOR)]);

        return $this->pdf->generar(collect([$this->tarjeta($credencial)]), FormatoImpresionCredencial::CR80);
    }

    public function qr(Credencial $credencial): ?string
    {
        try {
            $svg = QrCode::format('svg')
                ->size(240)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($credencial->urlVerificacion());

            return $svg ? 'data:image/svg+xml;base64,'.base64_encode((string) $svg) : null;
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar el QR de la credencial', [
                'credencial_id' => $credencial->id,
                'motivo'        => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ── Verificación pública ────────────────────────────────────────

    public function verificar(string $codigo): ?array
    {
        $credencial = Credencial::where('codigo', $codigo)
            ->with(['servidor' => fn ($q) => $q->with(DatosCredencial::RELACIONES_SERVIDOR)])
            ->first();

        if (! $credencial) {
            return null;
        }

        // Una no vigente no dice de quién era (6.4).
        if (! $credencial->estaVigente() || ! $credencial->servidor->estado) {
            return ['vigente' => false];
        }

        $datos = DatosCredencial::de($credencial->servidor, $credencial);

        // Nunca la cédula, el tipo de sangre ni el contacto de emergencia.
        return [
            'vigente' => true,
            'nombre'  => trim("{$datos['nombres']} {$datos['apellidos']}"),
            'cargo'   => $datos['cargo'],
            'unidad'  => $datos['unidad'],
            'foto'    => $this->foto($credencial->servidor),
        ];
    }

    // ── Lo interno ──────────────────────────────────────────────────

    /**
     * Pone al día la credencial de un servidor y dice qué cambió.
     *
     * @return array{0: ?Credencial, 1: 'creadas'|'vencidas'|'reactivadas'|'anuladas'|null}
     */
    private function aplicar(int $servidorId, ?Credencial $reemplazaA = null): array
    {
        return DB::transaction(function () use ($servidorId, $reemplazaA) {
            $servidor = $this->bloquear($servidorId);

            if (! $servidor) {
                return [null, null];
            }

            $viva = Credencial::where('servidor_id', $servidorId)->vivas()->first();

            // Activo es `estado`, que desde la fase 1.5 de Acciones de Personal
            // significa «tiene un vínculo vigente» y lo pone
            // ContratoServidorService::sincronizarPuestoDesdeVinculo antes de
            // llamar aquí. No se vuelve a deducir del contrato: un expediente
            // antiguo sin su contrato cargado sigue siendo un servidor activo.
            if ($servidor->trashed() || ! $servidor->estado) {
                if (! $viva) {
                    return [null, null];
                }

                $viva->update(['estado' => EstadoCredencial::ANULADA, 'anulada_en' => now()]);

                return [$viva, 'anuladas'];
            }

            // El contrato da la fecha de fin, si la tiene: el vigente más
            // reciente, el mismo que lee `estado`.
            $vinculo = ContratoServidor::where('servidor_id', $servidorId)
                ->where('estado', EstadoContrato::VIGENTE->value)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->first();

            $validaHasta = $vinculo?->fecha_fin?->toDateString();
            $estado = $validaHasta !== null && $validaHasta < Carbon::today()->toDateString()
                ? EstadoCredencial::VENCIDA
                : EstadoCredencial::VIGENTE;

            if ($viva) {
                $antes = $viva->estado;
                $viva->fill(['valida_hasta' => $validaHasta, 'estado' => $estado]);

                if (! $viva->isDirty()) {
                    return [$viva, null];
                }

                $viva->save();

                return [$viva, match (true) {
                    $antes === $estado                    => null,
                    $estado === EstadoCredencial::VENCIDA => 'vencidas',
                    default                               => 'reactivadas',
                }];
            }

            // Sin foto no hay credencial que enseñar ni imprimir.
            if (! $servidor->fotografiaVigente()->exists()) {
                return [null, null];
            }

            $credencial = Credencial::create([
                'servidor_id'    => $servidorId,
                'codigo'         => $this->generarCodigo(),
                'estado'         => $estado,
                'valida_hasta'   => $validaHasta,
                'reemplaza_a_id' => $reemplazaA?->id,
            ]);

            return [$credencial, 'creadas'];
        });
    }

    /**
     * Toma la fila del servidor: la foto, el vínculo y la tarea de la noche
     * esperan su turno en vez de chocar con el índice de una sola viva.
     */
    private function bloquear(int $servidorId): ?Servidor
    {
        return Servidor::withTrashed()->whereKey($servidorId)->lockForUpdate()->first();
    }

    /**
     * Lo que lleva una tarjeta para el PDF: sus datos, la foto, el QR y los
     * logos vigentes hoy.
     *
     * @return array{datos: array<string, mixed>, foto: ?string, qr: ?string}
     */
    private function tarjeta(Credencial $credencial): array
    {
        $fotografia = $credencial->servidor->fotografiaVigente;
        $disco = Storage::disk(FotografiaService::DISCO);

        return [
            'datos' => DatosCredencial::de($credencial->servidor, $credencial),
            // Por ruta y no en data: URL: en un lote de 90, dompdf no tiene
            // que cargar el HTML con nueve megas de base64.
            'foto'  => $fotografia && $disco->exists($fotografia->archivo) ? $disco->path($fotografia->archivo) : null,
            'qr'    => $this->qr($credencial),
        ];
    }

    /** La foto vigente de 600×800 en data: URL, para la verificación pública. */
    private function foto(Servidor $servidor): ?string
    {
        $fotografia = $servidor->fotografiaVigente;
        $disco = Storage::disk(FotografiaService::DISCO);

        if (! $fotografia || ! $disco->exists($fotografia->archivo)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode((string) $disco->get($fotografia->archivo));
    }

    /**
     * Los ids de quienes tienen la tarjeta impresa, según esté al día o no.
     *
     * @return list<int>
     */
    private function segunLaImpresion($query, bool $desactualizada): array
    {
        return $query
            ->whereHas('credencialViva', fn ($q) => $q->whereHas('impresiones'))
            ->with([...DatosCredencial::RELACIONES_SERVIDOR, 'credencialViva.ultimaImpresion'])
            ->get()
            ->filter(function (Servidor $s) use ($desactualizada) {
                $cambios = DatosCredencial::cambios(
                    $s->credencialViva->ultimaImpresion,
                    DatosCredencial::de($s, $s->credencialViva),
                );

                return ($cambios !== []) === $desactualizada;
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Aleatorio, de 40 caracteres: es lo que lleva el QR y lo único que pide
     * la verificación sin sesión, así que no se puede adivinar ni recorrer.
     */
    private function generarCodigo(): string
    {
        do {
            $codigo = bin2hex(random_bytes(20));
        } while (Credencial::where('codigo', $codigo)->exists());

        return $codigo;
    }

    private function minuscula(EstadoCredencial $estado): string
    {
        return Str::lower($estado->etiqueta());
    }
}
