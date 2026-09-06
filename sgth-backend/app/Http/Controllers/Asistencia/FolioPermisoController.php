<?php

namespace App\Http\Controllers\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;

class FolioPermisoController extends Controller
{
    /** Estados en los que el documento ampara una ausencia. */
    private const ESTADOS_VIGENTES = [
        EstadoPermiso::PENDIENTE,
        EstadoPermiso::ACTIVO,
        EstadoPermiso::VALIDADO_TRABAJO_SOCIAL,
    ];

    private const TIPO_ETIQUETAS = [
        'personal'   => 'Personal',
        'oficial'    => 'Oficial',
        'enfermedad' => 'Por enfermedad',
        'calamidad'  => 'Calamidad doméstica',
    ];

    private const ESTADO_ETIQUETAS = [
        'pendiente'               => 'Pendiente de recepción',
        'activo'                  => 'Confirmado por Recepción',
        'validado_trabajo_social' => 'Validado por Trabajo Social',
        'anulado'                 => 'Anulado',
        'rechazado'               => 'Rechazado',
        'falta_injustificada'     => 'Falta injustificada',
    ];

    /**
     * Endpoint PÚBLICO sin autenticación para verificación mediante escaneo del QR.
     * Es utilizado por agentes externos o seguridad para validar que el permiso
     * emitido por el sistema existe y no ha sido adulterado.
     *
     * Devolvía el modelo entero con la relación `servidor` cargada: las 24
     * columnas de la tabla —incluida la observación, que en un permiso por
     * enfermedad es un dato de salud— más la cédula y el expediente completo
     * del servidor, a cualquiera, sin sesión. Con folios secuenciales y por
     * tanto adivinables, eso era un volcado del padrón a pedido.
     *
     * Lo que hace falta para verificar un papel en la puerta es mucho menos:
     * que el folio exista, de quién es, cuándo y por cuánto tiempo, y si sigue
     * vigente. La cédula va enmascarada —basta para contrastarla contra el
     * documento que la persona tiene en la mano, no para llevársela.
     */
    public function verificar(string $folio)
    {
        $permiso = PermisoServidor::with([
            'servidor:id,cedula,nombre,segundo_nombre,apellido,segundo_apellido,unidad_administrativa_id',
            'servidor.unidadAdministrativa:id,nombre',
            'unidadAdministrativa:id,nombre',
        ])
            ->where('folio', $folio)
            ->first();

        if (!$permiso) {
            return ApiResponse::noEncontrado('El folio escaneado no existe o es inválido.');
        }

        $estado = $permiso->estado instanceof EstadoPermiso
            ? $permiso->estado
            : EstadoPermiso::tryFrom((string) $permiso->estado);

        $tipo = $permiso->tipo instanceof TipoPermiso
            ? $permiso->tipo
            : TipoPermiso::tryFrom((string) $permiso->tipo);

        $vigente = $estado !== null && in_array($estado, self::ESTADOS_VIGENTES, true);

        return ApiResponse::ok([
            'folio'          => $permiso->folio,
            'vigente'        => $vigente,
            'estado'         => $estado?->value,
            'estado_label'   => self::ESTADO_ETIQUETAS[$estado?->value] ?? '—',
            'tipo'           => $tipo?->value,
            'tipo_label'     => self::TIPO_ETIQUETAS[$tipo?->value] ?? '—',
            'servidor'       => $this->nombreCompleto($permiso),
            'cedula_parcial' => $this->enmascararCedula($permiso->servidor?->cedula),
            'unidad'         => $permiso->unidadAdministrativa?->nombre
                ?? $permiso->servidor?->unidadAdministrativa?->nombre,
            'fecha'          => $permiso->fecha?->format('Y-m-d'),
            'hora_inicio'    => substr((string) $permiso->getRawOriginal('hora_inicio'), 0, 5),
            'hora_fin'       => substr((string) $permiso->getRawOriginal('hora_fin'), 0, 5),
            'emitido_en'     => $permiso->created_at?->toIso8601String(),
            'verificado_en'  => now()->toIso8601String(),
        ], 'Documento validado exitosamente como auténtico e inalterado.');
    }

    private function nombreCompleto(PermisoServidor $permiso): string
    {
        $servidor = $permiso->servidor;

        return mb_strtoupper(implode(' ', array_filter([
            $servidor?->apellido,
            $servidor?->segundo_apellido,
            $servidor?->nombre,
            $servidor?->segundo_nombre,
        ])), 'UTF-8') ?: '—';
    }

    /**
     * `0801234562` → `08*****562`. Suficiente para contrastar contra la cédula
     * física, inservible para copiarla.
     */
    private function enmascararCedula(?string $cedula): ?string
    {
        if (! $cedula) {
            return null;
        }

        $largo = mb_strlen($cedula);

        if ($largo <= 5) {
            return str_repeat('*', $largo);
        }

        return mb_substr($cedula, 0, 2)
            . str_repeat('*', $largo - 5)
            . mb_substr($cedula, -3);
    }
}
