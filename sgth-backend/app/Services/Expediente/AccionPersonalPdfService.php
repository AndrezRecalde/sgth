<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use Barryvdh\DomPDF\Facade\Pdf;

class AccionPersonalPdfService
{
    public function generarContent(int $movimientoId, bool $vistaPrevia = false): array
    {
        ['vista' => $vista, 'datos' => $datos, 'archivo' => $archivo] = $this->documento($movimientoId, $vistaPrevia);

        return [
            'content'  => Pdf::loadView($vista, $datos)->setPaper('a4', 'portrait')->output(),
            'filename' => $archivo,
        ];
    }

    /**
     * El documento de la acción sin pasarlo a PDF: qué plantilla, con qué
     * datos y cómo se llama el archivo. Lo usa la descarga, y lo pueden
     * renderizar las pruebas y la vista previa sin dompdf.
     *
     * La vista previa (fase 6.2; TH 27) es de lo que todavía no se registra
     * —borrador o suscrita—: el mismo documento, con la marca BORRADOR, sin
     * número y, en el borrador, con los firmantes que firmarían hoy.
     *
     * @return array{vista: string, datos: array<string, mixed>, archivo: string}
     */
    public function documento(int $movimientoId, bool $vistaPrevia = false): array
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
            // El grupo y la denominación con que se firmó (fase 7.1b).
            'grupoOcupacionalOrigen',
            'cargoOrigen',
            'grupoOcupacionalDestino',
            'cargoDestino',
            'partidaPresupuestaria',
            // Partida congelada al crear la acción: es la que debe imprimirse,
            // no la que el puesto tenga hoy.
            'partidaOrigen',
            'autorizadoPor',
            // Subrogación y encargo comparten tipo_movimiento; solo la fila
            // enlazada distingue cuál de los dos es, y el documento debe
            // decirlo por su nombre.
            'subrogacion.subrogado',
            // A quién reemplaza un ingreso de reemplazo (fase 6.1).
            'cubreMovimiento.servidor',
            'cubreMovimiento.puestoOrigen.cargo',
        ])->findOrFail($movimientoId);

        // El formato impreso es el de una Acción de Personal. Desde la fase 1.2
        // todos los tipos que quedan en esta tabla son actos —la bitácora pasó
        // a `eventos_vinculo`—, pero la puerta se queda: un tipo nuevo que no
        // lo sea no debe imprimir un documento de apariencia oficial.
        if ($movimiento->esRegistroDeContrato()) {
            throw new ReglaNegocioException(
                'El registro del contrato no tiene documento de acción de personal: el contrato '
                    .'se firma aparte y aquí solo se registra.'
            );
        }

        // Quien tiene Servicios Profesionales no es servidor (fase 4.2): su
        // contrato, sus adendas y su terminación los firmaron las partes.
        if ($movimiento->esDelContratoCivil()) {
            throw new ReglaNegocioException(
                'Un acto del contrato civil no tiene documento de acción de personal: el contrato, sus adendas y '
                    .'su terminación los firman las partes, y aquí solo se registran.'
            );
        }

        if (! $movimiento->tieneDocumentoImprimible()) {
            throw new ReglaNegocioException(
                "\"{$movimiento->tipo_movimiento->etiqueta()}\" es un registro interno del "
                    .'expediente, no una Acción de Personal: no tiene documento imprimible.'
            );
        }

        /*
        | ANULADA entró el 2026-09-29, con el resto de la anulación de actos ya
        | registrados. Un acto que existió y se anuló sigue teniendo documento:
        | lo que cambia es que el papel debe decir que está sin efecto. Negarle
        | el PDF dejaba a Talento Humano con un correlativo emitido, muy
        | probablemente ya impreso y entregado, y ninguna forma de emitir la
        | versión que lo desmiente.
        |
        | El guard del correlativo, más abajo, es el que separa lo anulado que
        | llegó a registrarse de lo anulado en borrador, que nunca fue nada.
        */
        $imprimibles = [
            EstadoAccionPersonal::REGISTRADA,
            EstadoAccionPersonal::NOTIFICADA,
            EstadoAccionPersonal::ANULADA,
        ];

        // La vista previa va por el otro lado: solo lo que todavía no tiene su
        // documento, y sin número, que no lo tiene (fase 6.2).
        if ($vistaPrevia) {
            if (! in_array($movimiento->estado, [EstadoAccionPersonal::BORRADOR, EstadoAccionPersonal::SUSCRITA], true)) {
                throw new ReglaNegocioException(
                    'La vista previa es de lo que todavía no se registra: esta acción ya tiene su documento, descárguelo.'
                );
            }
        } elseif (!in_array($movimiento->estado, $imprimibles, true)) {
            throw new ReglaNegocioException(
                'Solo se puede generar el PDF de Acción de Personal para movimientos en '
                    .'estado registrada, notificada o anulada.'
            );
        }

        /*
        | Sin correlativo no hay documento.
        |
        | El estado por sí solo no basta. Hasta la fase 1.2 había filas que
        | nacían directamente en REGISTRADA sin pasar por la máquina de estados,
        | porque eran constancia de un hecho consumado y no un acto —la
        | finalización anticipada de una subrogación, su cancelación, la novedad
        | de contrato—, y con el guard anterior generaban un documento oficial
        | de Acción de Personal con los firmantes en blanco. Esas filas ya viven
        | en `eventos_vinculo`; el guard se queda para cualquier acto al que le
        | falte el flujo, que es lo que de verdad mide.
        |
        | El correlativo AP-AAAA-NNNN lo estampa `aplicarRegistro()` y nadie
        | más, así que su presencia es exactamente «esto pasó por el flujo
        | guardado». Y no es un discriminante de conveniencia: es el
        | identificador que el propio documento imprime, de modo que un
        | movimiento sin él no puede producir un documento identificable.
        |
        | `categoria` parecía el candidato natural —null en las constancias— y
        | no servía: `ContratoServidorService` la derivaba del nombramiento, así
        | que una novedad de contrato de un servidor LOSEP era bitácora y
        | llevaba 'accion_de_personal' igualmente.
        */
        if (! $vistaPrevia && blank($movimiento->codigo_registro)) {
            throw new ReglaNegocioException(
                'Este movimiento es una constancia del expediente, no un acto administrativo: '
                    .'no pasó por la suscripción ni tiene correlativo, así que no hay documento que emitir.'
            );
        }

        // Una multa o una suspensión no cambian la situación del servidor: lo
        // que el documento tiene que decir —es el que recibe Financiero— es
        // cuánto se descuenta. Se calcula sobre la remuneración que la acción
        // congeló, así que reimprimirlo da siempre la misma cifra.
        $descuento = \App\Models\Disciplinario\SancionDisciplinaria::where('movimiento_personal_id', $movimiento->id)
            ->first()
            ?->descuentoReferencial(
                $movimiento->remuneracion_origen !== null ? (float) $movimiento->remuneracion_origen : null
            );

        // A una autoridad electa no se le emite acción de personal: su acto
        // tiene una constancia, con su número AU, que firma Talento Humano [TH 6;
        // fase 4.1b].
        if ($movimiento->instrumento === \App\Enums\InstrumentoActo::ACTO_AUTORIDAD) {
            return [
                'vista' => 'pdf.expediente.constancia-autoridad',
                'datos' => [
                    'movimiento'         => $movimiento,
                    'anulada'            => $movimiento->estado === EstadoAccionPersonal::ANULADA,
                    'servidor'           => $movimiento->servidor,
                    'firmaTalentoHumano' => $this->firma($movimiento, 'firmante_th', RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO, $vistaPrevia),
                    'logo'               => public_path('images/logo-gadpe.png'),
                    'vistaPrevia'        => $vistaPrevia,
                ],
                'archivo' => $vistaPrevia
                    ? "vista_previa_constancia_{$movimiento->id}.pdf"
                    : 'constancia_'.$movimiento->codigo_registro
                        .($movimiento->estado === EstadoAccionPersonal::ANULADA ? '_ANULADA' : '').'.pdf',
            ];
        }

        // A un obrero tampoco: cada acto suyo deja la hoja de registro interno,
        // con su número RI, que firma Talento Humano [TH N8; fase 4.3]. Es la
        // que va a Financiero con la multa.
        if ($movimiento->esDelRegistroInterno()) {
            return [
                'vista' => 'pdf.expediente.registro-interno',
                'datos' => [
                    'movimiento'         => $movimiento,
                    'descuento'          => $descuento,
                    // La liquidación referencial de su terminación (fase 4.5).
                    'liquidacion'        => app(TerminacionObreroService::class)->liquidacion($movimiento),
                    'anulada'            => $movimiento->estado === EstadoAccionPersonal::ANULADA,
                    'servidor'           => $movimiento->servidor,
                    'firmaTalentoHumano' => $this->firma($movimiento, 'firmante_th', RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO, $vistaPrevia),
                    'logo'               => public_path('images/logo-gadpe.png'),
                    'vistaPrevia'        => $vistaPrevia,
                ],
                'archivo' => $vistaPrevia
                    ? "vista_previa_registro_interno_{$movimiento->id}.pdf"
                    : 'registro_interno_'.$movimiento->codigo_registro
                        .($movimiento->estado === EstadoAccionPersonal::ANULADA ? '_ANULADA' : '').'.pdf',
            ];
        }

        return [
            'vista' => 'pdf.expediente.accion-personal',
            'datos' => [
                'movimiento'   => $movimiento,
                'descuento'    => $descuento,
                // El formato del Ministerio (fase 6.1): la modalidad del vínculo en
                // las dos situaciones y a quién reemplaza.
                ...$this->modalidades($movimiento),
                'reemplazo'    => $this->reemplazoDe($movimiento),
                'anulada'      => $movimiento->estado === EstadoAccionPersonal::ANULADA,
                'servidor'     => $movimiento->servidor,
                'firmaAutoridad' => $this->firma($movimiento, 'firmante_autoridad', RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, $vistaPrevia),
                'firmaTalentoHumano' => $this->firma($movimiento, 'firmante_th', RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO, $vistaPrevia),
                'logo'         => public_path('images/logo-gadpe.png'),
                'vistaPrevia'  => $vistaPrevia,
            ],
            // codigo_registro es el que genera el sistema al registrar la
            // acción; 'codigo' es un campo libre que casi nunca se llena.
            'archivo' => $vistaPrevia ? "vista_previa_accion_personal_{$movimiento->id}.pdf" : 'accion_personal_'
                .($movimiento->codigo_registro ?: $movimiento->codigo ?: $movimiento->id)
                // Para que el archivo anulado no se confunda con el vigente en
                // la carpeta de quien descarga los dos.
                .($movimiento->estado === EstadoAccionPersonal::ANULADA ? '_ANULADA' : '')
                .'.pdf',
        ];
    }

    /**
     * La modalidad del vínculo antes y después del acto (diseño, 6.5). La de
     * antes es la del contrato que regía cuando la acción tomó efecto —no el
     * de hoy: un documento reimpreso tras una cesación tiene que seguir
     * diciendo cuál tenía—; la propuesta, la que el acto fija, o la misma si
     * no la cambia.
     *
     * @return array{modalidadActual: ?TipoNombramiento, modalidadPropuesta: ?TipoNombramiento}
     */
    private function modalidades(MovimientoPersonal $movimiento): array
    {
        $anterior = $movimiento->tipo_movimiento?->creaVinculo()
            ? null
            : $movimiento->servidor?->contratos()
                ->where('estado', '!=', 'cancelado')
                ->whereDate('fecha_inicio', '<', $movimiento->fecha_efectiva ?? now())
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('id')
                ->first()?->tipo_nombramiento;

        $propuesta = match (true) {
            $movimiento->tipo_nombramiento_propuesto !== null              => $movimiento->tipo_nombramiento_propuesto,
            (bool) $movimiento->tipo_movimiento?->convierteNombramiento()  => TipoNombramiento::PERMANENTE,
            $movimiento->proponeSituacion()                                => $anterior,
            default                                                        => null,
        };

        return ['modalidadActual' => $anterior, 'modalidadPropuesta' => $propuesta];
    }

    /**
     * «Reemplaza a / en el puesto de / quien cesó por / acción N.º», en la
     * segunda página (diseño, 6.5). Sale de la ausencia que cubre un ingreso
     * de reemplazo, del titular de una subrogación, o de la cesación de quien
     * ocupaba antes el puesto. Null si no hay a quién. `rotulo_por` dice si
     * el anterior cesó o solo está ausente: el titular de una comisión no cesó.
     *
     * @return array{a: string, puesto: ?string, rotulo_por: string, por: ?string, accion: ?string}|null
     */
    private function reemplazoDe(MovimientoPersonal $movimiento): ?array
    {
        $nombre = fn ($s) => $s ? trim("{$s->apellido} {$s->nombre}") : null;

        if ($ausencia = $movimiento->cubreMovimiento) {
            return [
                'a'      => $nombre($ausencia->servidor) ?? '—',
                'puesto' => $ausencia->puestoOrigen?->cargo?->nombre,
                'rotulo_por' => 'Ausente por',
                'por'    => $ausencia->etiquetaAusencia() ?? $ausencia->etiqueta(),
                'accion' => $ausencia->codigo_registro,
            ];
        }

        if ($titular = $movimiento->subrogacion?->subrogado) {
            return [
                'a'      => $nombre($titular),
                'puesto' => $movimiento->puestoDestino?->cargo?->nombre,
                'rotulo_por' => 'Ausente por',
                'por'    => $movimiento->subrogacion->motivo?->etiqueta(),
                'accion' => null,
            ];
        }

        // Quien ocupaba el puesto al que llega: la última cesación desde él.
        $llegaA = in_array($movimiento->tipo_movimiento, [
            TipoMovimientoPersonal::INGRESO, TipoMovimientoPersonal::ASCENSO,
            TipoMovimientoPersonal::RESTITUCION, TipoMovimientoPersonal::SUBROGACION,
        ], true) ? $movimiento->puesto_destino_id : null;

        if (! $llegaA) {
            return null;
        }

        $cesacion = MovimientoPersonal::query()
            ->with('servidor')
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('puesto_origen_id', $llegaA)
            ->where('servidor_id', '!=', $movimiento->servidor_id)
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereDate('fecha_efectiva', '<=', $movimiento->fecha_efectiva ?? now())
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id')
            ->first();

        return $cesacion ? [
            'a'      => $nombre($cesacion->servidor) ?? '—',
            'puesto' => $movimiento->puestoDestino?->cargo?->nombre,
            'rotulo_por' => 'Quien cesó por',
            'por'    => $cesacion->causal()?->etiqueta() ?? $cesacion->etiqueta(),
            'accion' => $cesacion->codigo_registro,
        ] : null;
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
     * En la vista previa de un borrador todavía no hay nadie sellado: va quien
     * firmaría hoy según el organigrama, marcado como provisional (fase 6.2).
     *
     * @return array{rotulo:string, nombre:?string, cargo:string, provisional?: bool}
     */
    private function firma(
        MovimientoPersonal $movimiento,
        string $prefijo,
        RolFirmaAccionPersonal $rol,
        bool $vistaPrevia = false,
    ): array {
        if ($vistaPrevia && $movimiento->estado === EstadoAccionPersonal::BORRADOR) {
            $provisional = app(FirmanteAccionPersonalService::class)->provisional($rol);

            return [
                'rotulo'      => $rol->rotuloDocumento(),
                'nombre'      => $provisional['nombre'],
                'cargo'       => $provisional['cargo'] ?: $rol->cargoPorDefecto(),
                'provisional' => true,
            ];
        }

        return [
            'rotulo' => $rol->rotuloDocumento(),
            'nombre' => $movimiento->{"{$prefijo}_nombre"},
            'cargo'  => $movimiento->{"{$prefijo}_cargo"} ?: $rol->cargoPorDefecto(),
        ];
    }
}
