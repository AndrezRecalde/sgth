<?php

namespace App\Http\Resources\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoNombramiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una clase del catálogo de acciones de personal, con lo que el formulario
 * necesita saber de ella.
 *
 * Va como recurso y no como arreglo armado en el controlador para que Scramble
 * describa su forma: dentro de un `array_map` la ve como `unknown[]` y el tipo
 * llegaba inservible al frontend.
 *
 * @mixin \App\Enums\ClaseAccionPersonal
 * @property ClaseAccionPersonal $resource
 */
class ClaseAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clase = $this->resource;

        return [
            'codigo'                      => $clase,
            'etiqueta'                    => $clase->etiqueta(),
            'familia'                     => $clase->familia(),
            'se_crea_desde_formulario'    => $clase->seCreaDesdeElFormulario(),
            'requiere_vinculo'            => $clase->requiereVinculo(),
            /** @var list<TipoNombramiento> */
            'nombramientos_elegibles'     => $clase->nombramientosElegibles(),
            'causales'                    => CausalAccionPersonalResource::collection($clase->causales()),
            // La que el formulario propone para la explicación del documento;
            // con causal, manda la de la causal (fase 6.1).
            'base_legal'                  => $clase->baseLegal(),
            'pide_situacion_propuesta'    => $clase->pideSituacionPropuesta(),
            'pide_periodo'                => $clase->pidePeriodo(),
            'pide_institucion_destino'    => $clase->pideInstitucionDestino(),
            'admite_para_estudios_o_eventos' => $clase->admiteParaEstudiosOEventos(),
            'pide_datos_judiciales'       => $clase->pideDatosJudiciales(),
            /** @var list<TipoNombramiento> */
            'pide_informe_uath_para'      => $clase->pideInformeUathPara(),
            'pide_contratacion'           => $clase->pideContratacion(),
            // El acta final del concurso: la pide el ascenso (fase 3.3).
            'pide_acta_concurso'          => $clase->pideActaConcurso(),
            // La credencial, la posesión y el fin del período (fase 4.1).
            'pide_datos_del_periodo'      => $clase->pideDatosDelPeriodo(),
            // Con o sin remuneración y la prórroga de la licencia del Consejo (4.1b).
            'pide_datos_de_licencia_del_consejo' => $clase->pideDatosDeLicenciaDelConsejo(),
            // Lo que cambia la adenda del contrato civil (fase 4.2).
            'pide_adenda'                 => $clase->pideAdenda(),
            // Lo que cambia del contrato de trabajo de un obrero (fase 4.3):
            // 'ocupacion', 'lugar' o 'remuneracion'.
            'cambio_del_obrero'           => $clase->cambioDelObrero(),
            // Con estos nombramientos no es acción sino registro del contrato
            // (fase 2.7): el formulario no pide firmantes.
            /** @var list<TipoNombramiento> */
            'registro_de_contrato_para'   => InstrumentoActo::nombramientosConRegistroDeContrato($clase),
            // Y con estos es acto de autoridad electa (fase 4.1): tampoco firma nadie.
            /** @var list<TipoNombramiento> */
            'acto_de_autoridad_para'      => InstrumentoActo::nombramientosConActoDeAutoridad($clase),
            // Y con estos es del contrato civil (fase 4.2): lo firmaron las
            // partes, y la ficha médica abre desmarcada [TH 25].
            /** @var list<TipoNombramiento> */
            'contrato_civil_para'         => InstrumentoActo::nombramientosConContratoCivil($clase),
            // Y con estos, del Código del Trabajo (fase 4.3): la hoja de
            // registro interno la firma solo Talento Humano.
            /** @var list<TipoNombramiento> */
            'documento_ct_para'           => InstrumentoActo::nombramientosConDocumentoCt($clase),
            'dictamen_medico_por_defecto' => $clase->dictamenMedicoPorDefecto(),
            'aviso'                       => $clase->aviso(),
        ];
    }
}
