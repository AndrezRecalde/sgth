<?php

namespace App\Services\Expediente;

use App\Enums\EstadoSolicitudCarga;
use App\Enums\TipoParentesco;
use App\Enums\TipoSolicitudCarga;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\SolicitudCargaFamiliar;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las cargas familiares que el servidor pide desde «Mi perfil», y su
 * validación por Talento Humano (docs/diseno-cargas-familiares-portal.md,
 * decidido el 2026-10-19).
 *
 * El Portal ya tuvo un registro directo, retirado el 2026-10-03 porque
 * cualquier servidor daba de alta familiares que entraban como pacientes del
 * Dispensario. Ahora el servidor solo presenta solicitudes, y nada cuenta
 * hasta que Talento Humano las valida: entonces se aplican sobre
 * `cargas_familiares` con CargaFamiliarService, el mismo código que usa el
 * expediente.
 */
final class SolicitudCargaFamiliarService
{
    private const DISCO = 'local';

    /** No dice a nombre de quién está la cédula (regla 4; D6). */
    private const CEDULA_YA_CONSTA = 'Esta cédula ya consta en el sistema. Consulte con Talento Humano.';

    private const PENDIENTE_DEL_FAMILIAR = 'Ya hay una solicitud pendiente para este familiar.';

    /** Los datos del familiar que trae un registro o una corrección. */
    private const CAMPOS = ['nombres', 'apellidos', 'parentesco', 'fecha_nacimiento', 'genero'];

    public function __construct(private readonly CargaFamiliarService $cargas) {}

    // ── El servidor ──────────────────────────────────────────────

    /** @param array<string, mixed> $datos */
    public function presentar(User $usuario, array $datos, ?UploadedFile $documento): SolicitudCargaFamiliar
    {
        $servidor = Servidor::find($usuario->servidor_id)
            ?? throw new ReglaNegocioException('Su cuenta no está vinculada a un expediente: no puede registrar familiares.');
        $tipo = TipoSolicitudCarga::from($datos['tipo']);
        $datos = $this->limpiar($datos);

        return DB::transaction(function () use ($usuario, $servidor, $tipo, $datos, $documento) {
            // Una a la vez por servidor mientras se comprueba: dos envíos
            // seguidos del mismo formulario no son dos solicitudes.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("solicitud_carga_servidor_{$servidor->id}")]);

            // Una ajena responde 404, como si no existiera (regla 1).
            $carga = $tipo === TipoSolicitudCarga::ALTA
                ? null
                : CargaFamiliar::where('servidor_id', $servidor->id)->findOrFail((int) $datos['carga_familiar_id']);

            match ($tipo) {
                TipoSolicitudCarga::ALTA       => $this->exigirRegistro($servidor, $datos),
                TipoSolicitudCarga::CORRECCION => $this->exigirCorreccion($servidor, $carga, $datos, $documento),
                TipoSolicitudCarga::BAJA       => $this->exigirBaja($carga),
            };

            $ruta = $documento?->store("expediente/cargas/{$servidor->id}", self::DISCO);
            if ($documento && ! $ruta) {
                throw new ReglaNegocioException('No se pudo guardar el documento. Inténtelo de nuevo.');
            }

            try {
                return SolicitudCargaFamiliar::create([
                    'servidor_id'       => $servidor->id,
                    'tipo'              => $tipo,
                    'carga_familiar_id' => $carga?->id,
                    'cedula'            => $tipo === TipoSolicitudCarga::ALTA ? $datos['cedula'] : null,
                    ...($tipo === TipoSolicitudCarga::BAJA ? [] : array_intersect_key($datos, array_flip(self::CAMPOS))),
                    'motivo'            => $datos['motivo'] ?? null,
                    'documento_ruta'    => $ruta ?: null,
                    'documento_nombre'  => $documento?->getClientOriginalName(),
                    'estado'            => EstadoSolicitudCarga::PENDIENTE,
                    'registrado_por'    => $usuario->id,
                ])->fresh(['cargaFamiliar']);
            } catch (UniqueConstraintViolationException) {
                // Otra solicitud pendiente para el mismo familiar entró a la
                // vez; los índices parciales la frenan (regla 3).
                if ($ruta) {
                    Storage::disk(self::DISCO)->delete($ruta);
                }
                throw new ReglaNegocioException(self::PENDIENTE_DEL_FAMILIAR);
            }
        });
    }

    /** La retira mientras está pendiente: es suya, y todavía no es nada. */
    public function retirar(User $usuario, int $solicitudId): SolicitudCargaFamiliar
    {
        $solicitud = $this->deLaCuenta($usuario, $solicitudId);

        if ($solicitud->estado !== EstadoSolicitudCarga::PENDIENTE) {
            throw new ReglaNegocioException("La solicitud está {$this->enMinuscula($solicitud->estado)}: ya no se retira.");
        }

        $solicitud->forceFill([
            'estado'      => EstadoSolicitudCarga::RETIRADA,
            'retirada_en' => now(),
        ])->save();

        return $solicitud->fresh(['cargaFamiliar']);
    }

    /** Una de las suyas, o 404. */
    public function deLaCuenta(User $usuario, int $solicitudId): SolicitudCargaFamiliar
    {
        return SolicitudCargaFamiliar::where('servidor_id', $usuario->servidor_id ?? 0)->findOrFail($solicitudId);
    }

    /**
     * Las suyas, de la más nueva a la más vieja. Las retiradas no: quedan en
     * la bitácora (diseño, 3.3).
     *
     * @return Collection<int, SolicitudCargaFamiliar>
     */
    public function delServidor(int $servidorId): Collection
    {
        return SolicitudCargaFamiliar::with('cargaFamiliar')
            ->where('servidor_id', $servidorId)
            ->where('estado', '!=', EstadoSolicitudCarga::RETIRADA)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Sus cargas, con la marca de las que tienen algo pendiente para que el
     * Portal no ofrezca pedir dos veces lo mismo.
     *
     * @return Collection<int, CargaFamiliar>
     */
    public function cargasDelServidor(int $servidorId): Collection
    {
        return CargaFamiliar::where('servidor_id', $servidorId)
            ->withExists(['solicitudes as tiene_solicitud_pendiente' => fn ($q) => $q
                ->where('estado', EstadoSolicitudCarga::PENDIENTE)])
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->orderBy('id')
            ->get();
    }

    // ── Talento Humano ───────────────────────────────────────────

    /**
     * Lo que espera a Talento Humano, de lo más viejo a lo más nuevo: lo que
     * llegó primero se atiende primero.
     *
     * @return Collection<int, SolicitudCargaFamiliar>
     */
    public function porValidar(?int $servidorId = null): Collection
    {
        return SolicitudCargaFamiliar::with([
            'servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido,unidad_administrativa_id',
            'servidor.unidadAdministrativa:id,nombre',
            'cargaFamiliar',
        ])
            ->where('estado', EstadoSolicitudCarga::PENDIENTE)
            ->when($servidorId, fn ($q) => $q->where('servidor_id', $servidorId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Aplica la solicitud sobre `cargas_familiares` y la deja validada. Las
     * reglas se vuelven a mirar: entre presentarla y validarla pudo cambiar
     * la ficha.
     */
    public function validar(int $solicitudId, User $usuario): SolicitudCargaFamiliar
    {
        return DB::transaction(function () use ($solicitudId, $usuario) {
            $solicitud = $this->paraResolver($solicitudId, $usuario);
            $servidor = Servidor::findOrFail($solicitud->servidor_id);

            $carga = $this->aplicar($solicitud, $servidor);

            $solicitud->forceFill([
                'estado'            => EstadoSolicitudCarga::VALIDADA,
                'carga_familiar_id' => $carga->id,
                'resuelto_por'      => $usuario->id,
                'resuelto_en'       => now(),
            ])->save();

            return $solicitud->fresh(['cargaFamiliar', 'servidor']);
        });
    }

    /** La rechaza con el motivo, que el servidor ve en «Mi perfil». */
    public function rechazar(int $solicitudId, string $motivo, User $usuario): SolicitudCargaFamiliar
    {
        return DB::transaction(function () use ($solicitudId, $motivo, $usuario) {
            $solicitud = $this->paraResolver($solicitudId, $usuario);

            $solicitud->forceFill([
                'estado'         => EstadoSolicitudCarga::RECHAZADA,
                'motivo_rechazo' => trim($motivo),
                'resuelto_por'   => $usuario->id,
                'resuelto_en'    => now(),
            ])->save();

            return $solicitud->fresh(['cargaFamiliar', 'servidor']);
        });
    }

    /**
     * Lo que quien valida tiene que saber antes de confirmar (diseño, 6.2).
     * Avisar no impide validar, salvo el cónyuge activo, que `validar` frena.
     *
     * @return list<string>
     */
    public function avisos(SolicitudCargaFamiliar $solicitud): array
    {
        if ($solicitud->estado !== EstadoSolicitudCarga::PENDIENTE) {
            return [];
        }

        $avisos = [];

        if ($solicitud->tipo === TipoSolicitudCarga::ALTA) {
            if ($this->cargas->hayBorradaConCedula($solicitud->cedula)) {
                $avisos[] = 'Esta cédula tuvo un registro anterior: al validar, se recupera con su historia clínica del Dispensario.';
            } elseif (HistoriaClinica::where('cedula_paciente', $solicitud->cedula)->exists()) {
                $avisos[] = 'Esta cédula ya tiene historia clínica en el Dispensario: al validar, el familiar queda unido a ella.';
            }

            if (! $solicitud->documento_ruta) {
                $avisos[] = 'Llegó sin documento de respaldo: compruebe el parentesco antes de validar.';
            }
        }

        if ($this->pasaASerConyuge($solicitud)
            && $this->conyugeActivo($solicitud->servidor_id, $solicitud->carga_familiar_id)) {
            $avisos[] = 'El servidor ya tiene un cónyuge o conviviente activo: valide primero su baja.';
        }

        return $avisos;
    }

    public function descargar(SolicitudCargaFamiliar $solicitud): StreamedResponse
    {
        if (! $solicitud->documento_ruta || ! Storage::disk(self::DISCO)->exists($solicitud->documento_ruta)) {
            throw new ReglaNegocioException('Esta solicitud no tiene documento adjunto.');
        }

        return Storage::disk(self::DISCO)->download(
            $solicitud->documento_ruta,
            $solicitud->documento_nombre ?? 'documento.pdf',
        );
    }

    // ── Reglas ───────────────────────────────────────────────────

    /**
     * Lo que impide registrar a un familiar (reglas 4, 5 y 7). Al validar se
     * vuelve a mirar, sin contar la propia solicitud.
     *
     * @param array<string, mixed> $datos
     */
    private function exigirRegistro(Servidor $servidor, array $datos, ?SolicitudCargaFamiliar $propia = null): void
    {
        $cedula = (string) $datos['cedula'];

        if ($cedula === $servidor->cedula) {
            throw new ReglaNegocioException('No puede registrarse a usted mismo como carga familiar.');
        }

        // El Dispensario busca primero entre los servidores: como carga no
        // le sumaría nada (D7).
        if (Servidor::where('cedula', $cedula)->exists()) {
            throw new ReglaNegocioException(
                'Esta cédula es de un servidor del GAD: el Dispensario ya lo atiende como servidor, '
                    .'así que no se registra como carga familiar.'
            );
        }

        $existente = CargaFamiliar::where('cedula', $cedula)->first(['id', 'servidor_id']);
        if ($existente) {
            throw new ReglaNegocioException($existente->servidor_id === $servidor->id
                ? 'Este familiar ya consta en su ficha.'
                : self::CEDULA_YA_CONSTA);
        }

        $pendiente = SolicitudCargaFamiliar::where('tipo', TipoSolicitudCarga::ALTA)
            ->where('estado', EstadoSolicitudCarga::PENDIENTE)
            ->where('cedula', $cedula)
            ->when($propia, fn ($q) => $q->whereKeyNot($propia->id))
            ->first(['id', 'servidor_id']);
        if ($pendiente) {
            throw new ReglaNegocioException($pendiente->servidor_id === $servidor->id
                ? 'Ya tiene una solicitud pendiente para este familiar.'
                : self::CEDULA_YA_CONSTA);
        }

        if ($this->parentescoDe($datos) === TipoParentesco::CONYUGUE) {
            $this->exigirSinOtroConyuge($servidor, null, alValidar: $propia !== null);
        }
    }

    /**
     * Una corrección cambia algo, y si toca la fecha de nacimiento o el
     * parentesco de un hijo, trae su documento (D11).
     *
     * @param array<string, mixed> $datos
     */
    private function exigirCorreccion(Servidor $servidor, CargaFamiliar $carga, array $datos, ?UploadedFile $documento): void
    {
        $antes = $this->datosDe($carga);
        $cambia = fn (string $campo) => (string) ($antes[$campo] ?? '') !== (string) ($datos[$campo] ?? '');

        if (! collect(self::CAMPOS)->contains($cambia)) {
            throw new ReglaNegocioException('No cambió ningún dato del familiar.');
        }

        $esHijo = $carga->parentesco === TipoParentesco::HIJO
            || $this->parentescoDe($datos) === TipoParentesco::HIJO;
        if ($esHijo && ($cambia('fecha_nacimiento') || $cambia('parentesco')) && ! $documento) {
            throw ValidationException::withMessages([
                'documento' => 'Adjunte la partida de nacimiento o la cédula de su hijo en PDF: cambia su fecha de nacimiento o su parentesco.',
            ]);
        }

        $this->exigirSinPendienteDe($carga);

        if ($this->parentescoDe($datos) === TipoParentesco::CONYUGUE && $carga->parentesco !== TipoParentesco::CONYUGUE) {
            $this->exigirSinOtroConyuge($servidor, $carga, alValidar: false);
        }
    }

    private function exigirBaja(CargaFamiliar $carga): void
    {
        if (! $carga->estado) {
            throw new ReglaNegocioException('Este familiar ya no está activo como carga familiar.');
        }

        $this->exigirSinPendienteDe($carga);
    }

    private function exigirSinPendienteDe(CargaFamiliar $carga): void
    {
        $pendiente = SolicitudCargaFamiliar::where('carga_familiar_id', $carga->id)
            ->where('estado', EstadoSolicitudCarga::PENDIENTE)
            ->exists();

        if ($pendiente) {
            throw new ReglaNegocioException(self::PENDIENTE_DEL_FAMILIAR);
        }
    }

    /**
     * Un solo cónyuge o conviviente activo a la vez (D5). Al presentar, se
     * admite si el actual ya tiene su baja pedida; al validar, la baja tiene
     * que estar validada.
     */
    private function exigirSinOtroConyuge(Servidor $servidor, ?CargaFamiliar $salvo, bool $alValidar): void
    {
        $activo = $this->conyugeActivo($servidor->id, $salvo?->id);

        if ($activo && $alValidar) {
            throw new ReglaNegocioException('El servidor ya tiene un cónyuge o conviviente activo: valide primero su baja.');
        }

        if ($activo) {
            $bajaPedida = SolicitudCargaFamiliar::where('carga_familiar_id', $activo->id)
                ->where('tipo', TipoSolicitudCarga::BAJA)
                ->where('estado', EstadoSolicitudCarga::PENDIENTE)
                ->exists();

            if (! $bajaPedida) {
                throw new ReglaNegocioException(
                    'Ya tiene un cónyuge o conviviente activo. Para registrar a otro, pida primero su baja.'
                );
            }
        }

        if (! $alValidar) {
            $otroPendiente = SolicitudCargaFamiliar::where('servidor_id', $servidor->id)
                ->where('estado', EstadoSolicitudCarga::PENDIENTE)
                ->whereIn('tipo', [TipoSolicitudCarga::ALTA, TipoSolicitudCarga::CORRECCION])
                ->where('parentesco', TipoParentesco::CONYUGUE)
                ->when($salvo, fn ($q) => $q->where(fn ($q) => $q
                    ->whereNull('carga_familiar_id')->orWhere('carga_familiar_id', '!=', $salvo->id)))
                ->exists();

            if ($otroPendiente) {
                throw new ReglaNegocioException('Ya tiene pendiente el registro de un cónyuge o conviviente.');
            }
        }
    }

    private function conyugeActivo(int $servidorId, ?int $salvoId): ?CargaFamiliar
    {
        return CargaFamiliar::where('servidor_id', $servidorId)
            ->where('parentesco', TipoParentesco::CONYUGUE)
            ->where('estado', true)
            ->when($salvoId, fn ($q) => $q->whereKeyNot($salvoId))
            ->first(['id']);
    }

    private function pasaASerConyuge(SolicitudCargaFamiliar $solicitud): bool
    {
        if ($solicitud->parentesco !== TipoParentesco::CONYUGUE) {
            return false;
        }

        return $solicitud->tipo === TipoSolicitudCarga::ALTA
            || ($solicitud->tipo === TipoSolicitudCarga::CORRECCION
                && $solicitud->cargaFamiliar?->parentesco !== TipoParentesco::CONYUGUE);
    }

    // ── Validar ──────────────────────────────────────────────────

    /** La pendiente, bloqueada, y que no sea de la familia de quien resuelve (regla 8). */
    private function paraResolver(int $solicitudId, User $usuario): SolicitudCargaFamiliar
    {
        $solicitud = SolicitudCargaFamiliar::lockForUpdate()->findOrFail($solicitudId);

        if ($solicitud->estado !== EstadoSolicitudCarga::PENDIENTE) {
            throw new ReglaNegocioException(
                "Esta solicitud ya está {$this->enMinuscula($solicitud->estado)}: solo se valida o se rechaza una pendiente."
            );
        }

        if ($usuario->servidor_id !== null && (int) $usuario->servidor_id === (int) $solicitud->servidor_id) {
            throw new ReglaNegocioException(
                'No puede validar ni rechazar las solicitudes de su propia familia: las revisa otra persona de Talento Humano.'
            );
        }

        return $solicitud;
    }

    private function aplicar(SolicitudCargaFamiliar $solicitud, Servidor $servidor): CargaFamiliar
    {
        $datos = $this->datosPropuestos($solicitud);

        if ($solicitud->tipo === TipoSolicitudCarga::ALTA) {
            $datos['cedula'] = $solicitud->cedula;
            $this->exigirRegistro($servidor, $datos, $solicitud);

            // Recupera la fila borrada de esa cédula, si la hay (regla 6).
            return $this->cargas->registrar($servidor->id, $datos);
        }

        $carga = CargaFamiliar::where('servidor_id', $servidor->id)->find($solicitud->carga_familiar_id)
            ?? throw new ReglaNegocioException('El familiar ya no está en la ficha del servidor: rechace la solicitud.');

        if ($solicitud->tipo === TipoSolicitudCarga::BAJA) {
            return $this->cargas->desactivar($carga);
        }

        if ($this->parentescoDe($datos) === TipoParentesco::CONYUGUE && $carga->parentesco !== TipoParentesco::CONYUGUE) {
            $this->exigirSinOtroConyuge($servidor, $carga, alValidar: true);
        }

        return $this->cargas->actualizar($carga, $datos);
    }

    // ── Auxiliares ───────────────────────────────────────────────

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function limpiar(array $datos): array
    {
        foreach (['nombres', 'apellidos', 'motivo'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $datos[$campo] = filled($datos[$campo]) ? trim((string) $datos[$campo]) : null;
            }
        }

        return $datos;
    }

    /** @return array<string, mixed> */
    private function datosPropuestos(SolicitudCargaFamiliar $solicitud): array
    {
        return [
            'nombres'          => $solicitud->nombres,
            'apellidos'        => $solicitud->apellidos,
            'parentesco'       => $solicitud->parentesco?->value,
            'fecha_nacimiento' => $solicitud->fecha_nacimiento?->toDateString(),
            'genero'           => $solicitud->genero,
        ];
    }

    /** @return array<string, mixed> */
    private function datosDe(CargaFamiliar $carga): array
    {
        return [
            'nombres'          => $carga->nombres,
            'apellidos'        => $carga->apellidos,
            'parentesco'       => $carga->parentesco?->value,
            'fecha_nacimiento' => $carga->fecha_nacimiento?->toDateString(),
            'genero'           => $carga->genero,
        ];
    }

    /** @param array<string, mixed> $datos */
    private function parentescoDe(array $datos): ?TipoParentesco
    {
        $valor = $datos['parentesco'] ?? null;

        return $valor instanceof TipoParentesco ? $valor : TipoParentesco::tryFrom((string) $valor);
    }

    private function enMinuscula(EstadoSolicitudCarga $estado): string
    {
        return mb_strtolower($estado->etiqueta());
    }
}
