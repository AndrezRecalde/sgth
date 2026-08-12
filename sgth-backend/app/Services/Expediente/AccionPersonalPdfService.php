<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use Barryvdh\DomPDF\Facade\Pdf;

class AccionPersonalPdfService
{
    public function generarContent(int $movimientoId): array
    {
        $movimiento = MovimientoPersonal::with([
            'servidor',
            'unidadOrigen',
            'unidadDestino',
            'puestoOrigen.cargo',
            'puestoOrigen.grupoOcupacional',
            'puestoOrigen.partidaPresupuestaria',
            'puestoDestino.cargo',
            'puestoDestino.grupoOcupacional',
            'puestoDestino.partidaPresupuestaria',
            'partidaPresupuestaria',
            // Partida congelada al crear la acción: es la que debe imprimirse,
            // no la que el puesto tenga hoy.
            'partidaOrigen',
            'autorizadoPor',
            // Subrogación y encargo comparten tipo_movimiento; solo la fila
            // enlazada distingue cuál de los dos es, y el documento debe
            // decirlo por su nombre.
            'subrogacion.subrogado',
        ])->findOrFail($movimientoId);

        // El formato impreso es el de una Acción de Personal. Los movimientos
        // históricos genéricos —novedad de contrato, cambio de puesto— son
        // bitácora interna: registran un hecho, no son un acto administrativo
        // con firmantes. Imprimirlos produciría un documento de apariencia
        // oficial que nunca existió.
        if (! $movimiento->tipo_movimiento->tieneDocumentoImprimible()) {
            throw new ReglaNegocioException(
                "\"{$movimiento->tipo_movimiento->etiqueta()}\" es un registro interno del "
                    .'expediente, no una Acción de Personal: no tiene documento imprimible.'
            );
        }

        if (!in_array($movimiento->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)) {
            throw new ReglaNegocioException(
                'Solo se puede generar el PDF de Acción de Personal para movimientos en estado registrada o notificada.'
            );
        }

        $pdf = Pdf::loadView('pdf.expediente.accion-personal', [
            'movimiento'   => $movimiento,
            'servidor'     => $movimiento->servidor,
            'firmaAutoridad' => $this->firma($movimiento, 'firmante_autoridad', RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
            'firmaTalentoHumano' => $this->firma($movimiento, 'firmante_th', RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
            'logo'         => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            // codigo_registro es el que genera el sistema al registrar la
            // acción; 'codigo' es un campo libre que casi nunca se llena.
            'filename' => 'accion_personal_'
                .($movimiento->codigo_registro ?: $movimiento->codigo ?: $movimiento->id)
                .'.pdf',
        ];
    }

    /**
     * Los datos del firmante salen de las columnas selladas al suscribir, no de
     * una búsqueda en el momento de imprimir: quien firmó una acción de 2024
     * debe seguir apareciendo aunque hoy el cargo lo ocupe otra persona.
     *
     * Si la acción es anterior al sellado (o no había firmante designado),
     * se imprime el rótulo genérico del rol en vez de atribuirle la firma a
     * quien ocupe el cargo hoy.
     *
     * @return array{rotulo:string, nombre:?string, cargo:string}
     */
    private function firma(
        MovimientoPersonal $movimiento,
        string $prefijo,
        RolFirmaAccionPersonal $rol
    ): array {
        return [
            'rotulo' => $rol->rotuloDocumento(),
            'nombre' => $movimiento->{"{$prefijo}_nombre"},
            'cargo'  => $movimiento->{"{$prefijo}_cargo"} ?: $rol->cargoPorDefecto(),
        ];
    }
}
