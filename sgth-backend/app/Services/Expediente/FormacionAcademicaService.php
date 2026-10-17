<?php

namespace App\Services\Expediente;

use App\Enums\EstadoFormacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los títulos y cursos que el servidor registra desde «Mi perfil» del portal,
 * y su validación por Talento Humano (decisión del 2026-10-17).
 *
 * El servidor los registra con el PDF del título o del certificado y quedan
 * por validar. Talento Humano los valida o los rechaza con motivo, y solo los
 * validados salen en la hoja de vida. Lo que registra Talento Humano desde el
 * expediente entra validado.
 */
final class FormacionAcademicaService
{
    private const DISCO = 'local';

    /** @param array<string, mixed> $datos */
    public function registrarPropia(
        Servidor $servidor,
        array $datos,
        UploadedFile $documento,
        User $usuario,
    ): HistorialAcademicoServidor {
        $ruta = $documento->store("expediente/formacion/{$servidor->id}", self::DISCO);

        return HistorialAcademicoServidor::create([
            ...$datos,
            'servidor_id'      => $servidor->id,
            'estado'           => EstadoFormacion::POR_VALIDAR,
            'documento_ruta'   => $ruta,
            'documento_nombre' => $documento->getClientOriginalName(),
            'registrado_por'   => $usuario->id,
        ]);
    }

    /**
     * El servidor retira uno suyo mientras no esté validado: el validado ya es
     * parte del expediente y solo Talento Humano lo quita.
     */
    public function retirarPropio(HistorialAcademicoServidor $registro): void
    {
        if ($registro->estado === EstadoFormacion::VALIDADO) {
            throw new ReglaNegocioException(
                'Este registro ya fue validado y es parte de su expediente: si hay que corregirlo, '
                .'pídaselo a Talento Humano.'
            );
        }

        $ruta = $registro->documento_ruta;
        $registro->delete();

        if ($ruta) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    public function validar(HistorialAcademicoServidor $registro, User $usuario): HistorialAcademicoServidor
    {
        return $this->resolver($registro, $usuario, EstadoFormacion::VALIDADO, null);
    }

    public function rechazar(HistorialAcademicoServidor $registro, User $usuario, string $motivo): HistorialAcademicoServidor
    {
        return $this->resolver($registro, $usuario, EstadoFormacion::RECHAZADO, $motivo);
    }

    /**
     * Lo que espera a Talento Humano, del más viejo al más nuevo: el primero
     * que llegó es el primero que se atiende.
     *
     * @return Collection<int, HistorialAcademicoServidor>
     */
    public function porValidar(): Collection
    {
        return HistorialAcademicoServidor::with([
            'servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido,unidad_administrativa_id',
            'servidor.unidadAdministrativa:id,nombre',
        ])
            ->where('estado', EstadoFormacion::POR_VALIDAR)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function descargar(HistorialAcademicoServidor $registro): StreamedResponse
    {
        if (! $registro->documento_ruta || ! Storage::disk(self::DISCO)->exists($registro->documento_ruta)) {
            throw new ReglaNegocioException('Este registro no tiene documento adjunto.');
        }

        return Storage::disk(self::DISCO)->download(
            $registro->documento_ruta,
            $registro->documento_nombre ?? 'documento.pdf',
        );
    }

    /**
     * Solo desde «por validar», y una sola vez. Nadie valida lo suyo: el
     * título es requisito de puestos y de concursos, y que lo dé por bueno su
     * propio titular no le serviría de nada a nadie.
     */
    private function resolver(
        HistorialAcademicoServidor $registro,
        User $usuario,
        EstadoFormacion $estado,
        ?string $motivo,
    ): HistorialAcademicoServidor {
        return DB::transaction(function () use ($registro, $usuario, $estado, $motivo) {
            $registro = HistorialAcademicoServidor::lockForUpdate()->findOrFail($registro->id);

            if ($registro->estado !== EstadoFormacion::POR_VALIDAR) {
                throw new ReglaNegocioException(
                    "Este registro ya está {$this->enMinuscula($registro->estado)}: solo se valida o se rechaza uno por validar."
                );
            }

            if ($usuario->servidor_id !== null && (int) $usuario->servidor_id === (int) $registro->servidor_id) {
                throw new ReglaNegocioException(
                    'No puede validar ni rechazar su propia formación: la revisa otra persona de Talento Humano.'
                );
            }

            $registro->update([
                'estado'         => $estado,
                'validado_por'   => $usuario->id,
                'validado_en'    => now(),
                'motivo_rechazo' => $motivo,
            ]);

            return $registro;
        });
    }

    private function enMinuscula(EstadoFormacion $estado): string
    {
        return mb_strtolower($estado->etiqueta(), 'UTF-8');
    }
}
