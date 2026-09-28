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

        /*
        | Sin correlativo no hay documento.
        |
        | El estado por sí solo no basta: hay filas que nacen directamente en
        | REGISTRADA sin pasar por la máquina de estados, porque son constancia
        | de un hecho consumado y no un acto que alguien apruebe —la
        | finalización anticipada de una subrogación, su cancelación, la novedad
        | de contrato—. Con el guard anterior, esas filas ofrecían y generaban
        | un documento oficial de Acción de Personal, con los firmantes en
        | blanco porque nunca se suscribieron y `sellarEn()` no corrió.
        |
        | El correlativo AP-AAAA-NNNN lo estampa `aplicarRegistro()` y nadie
        | más, así que su presencia es exactamente «esto pasó por el flujo
        | guardado». Y no es un discriminante de conveniencia: es el
        | identificador que el propio documento imprime, de modo que un
        | movimiento sin él no puede producir un documento identificable.
        |
        | `categoria` parecía el candidato natural —null en las constancias— y
        | no sirve: `ContratoServidorService` la deriva del nombramiento con
        | `CategoriaEventoVinculo::paraTipoNombramiento()`, así que una novedad
        | de contrato de un servidor LOSEP es bitácora y lleva
        | 'accion_de_personal' igualmente.
        */
        if (blank($movimiento->codigo_registro)) {
            throw new ReglaNegocioException(
                'Este movimiento es una constancia del expediente, no un acto administrativo: '
                    .'no pasó por la suscripción ni tiene correlativo, así que no hay documento que emitir.'
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
