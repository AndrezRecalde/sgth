import { z } from 'zod/v4'
import type { CatalogoAccionesPersonal, ClaseDelCatalogo } from '@/types/api'
import { buscarClase } from '../utils/catalogoAcciones'

const camposDelFormulario = z.object({
  // La clase legal de la acción y, en la cesación, su causal. Qué valores
  // existen y qué exige cada uno lo dice el catálogo del backend, que se cruza
  // abajo: escritos aquí serían una copia más de sus reglas.
  clase:  z.string().min(1, 'Seleccione el tipo de acción de personal'),
  causal: z.string().optional().nullable(),

  descripcion:     z.string().min(1, 'La descripción es requerida').max(1000),
  fecha_efectiva:  z.string().min(1, 'La fecha efectiva es requerida'),
  fecha_inicio:    z.string().optional().nullable(),
  fecha_fin:       z.string().optional().nullable(),

  // Situación propuesta — solo la piden las clases que reubican al servidor.
  unidad_destino_id: z.number().optional().nullable(),
  puesto_destino_id: z.number().optional().nullable(),
  remuneracion_propuesta: z.number().optional().nullable(),
  partida_presupuestaria_id: z.number().optional().nullable(),
  lugar_trabajo:   z.string().max(255).optional().nullable(),

  // Comisiones e intercambio: la entidad del Estado a la que va (fase 2.3).
  institucion_destino: z.string().max(255).optional().nullable(),
  para_estudios_o_eventos: z.boolean().optional().nullable(),

  // El informe técnico de la UATH del traslado (fase 3.1).
  informe_uath_numero: z.string().max(100).optional().nullable(),
  // La sentencia de una restitución (fase 2.6).
  causa_judicial_numero: z.string().max(100).optional().nullable(),
  unidad_judicial: z.string().max(255).optional().nullable(),
  fecha_sentencia: z.string().optional().nullable(),
  fecha_ejecutoria: z.string().optional().nullable(),
  remuneraciones_dejadas_de_percibir: z.number().min(0, 'No puede ser negativo').optional().nullable(),

  // Datos de la contratación. Solo existen en el ingreso, que es la única
  // acción que da origen a un contrato; en el resto ni se muestran ni se
  // envían.
  tipo_nombramiento_propuesto: z.string().optional().nullable(),
  // Para qué se da el provisional, y el acta del de prueba (fase 3.2).
  supuesto_provisional_propuesto: z.string().optional().nullable(),
  acta_concurso_numero: z.string().max(100).optional().nullable(),
  acta_concurso_fecha: z.string().optional().nullable(),
  // El inicio del período de una autoridad electa (fase 4.1).
  credencial_cne_numero: z.string().max(100).optional().nullable(),
  credencial_cne_fecha: z.string().optional().nullable(),
  acta_posesion_numero: z.string().max(100).optional().nullable(),
  // El consentimiento escrito del cambio de ocupación de un obrero (CT 192;
  // fase 4.3).
  fecha_consentimiento: z.string().optional().nullable(),
  // La licencia del Consejo: con o sin remuneración y su prórroga (fase 4.1b).
  con_remuneracion: z.boolean().optional().nullable(),
  prorroga_causa: z.string().optional().nullable(),
  numero_contrato: z.string().max(100).optional().nullable(),
  fecha_fin_propuesta: z.string().optional().nullable(),
  puede_marcar: z.boolean().optional().nullable(),
  // Enlace de reemplazo: la comisión o licencia cuyo hueco cubre este ingreso.
  cubre_movimiento_id: z.number().optional().nullable(),

  requiere_dictamen_medico: z.boolean().optional().nullable(),
  resolucion_numero: z.string().max(100).optional().nullable(),
  observacion:     z.string().max(1000).optional().nullable(),
  caucionado:      z.boolean().optional().nullable(),
  caucion_numero:  z.string().max(100).optional().nullable(),
  caucion_fecha:   z.string().optional().nullable(),
})

export type MovimientoFormData = z.infer<typeof camposDelFormulario>

/**
 * El esquema del formulario de acción de personal, con las exigencias de cada
 * clase tomadas del catálogo: si pide causal, período, situación propuesta o
 * datos de contratación.
 */
export function crearMovimientoSchema(
  catalogo: CatalogoAccionesPersonal,
  /** El nombramiento vigente del servidor: de él depende si el traslado pide el informe. */
  tipoNombramiento?: string | null,
) {
  return camposDelFormulario.superRefine((data, ctx) => {
    const clase = buscarClase(catalogo, data.clase)

    if (!clase) return

    // El traslado, previo informe de la UATH (LOSEP Art. 35; fase 3.1). A
    // quién se le pide lo dice el catálogo.
    if (pideInformeUath(clase, tipoNombramiento) && !data.informe_uath_numero?.trim()) {
      ctx.addIssue({
        path: ['informe_uath_numero'], code: 'custom',
        message: 'Indique el número del informe técnico de la UATH',
      })
    }

    if (clase.causales.length > 0 && !data.causal) {
      ctx.addIssue({
        path: ['causal'], code: 'custom',
        message: `Seleccione la causal de la ${clase.etiqueta}`,
      })
    }

    if (clase.pide_periodo) {
      if (!data.fecha_inicio) {
        ctx.addIssue({
          path: ['fecha_inicio'], code: 'custom',
          message: `La ${clase.etiqueta} requiere fecha de inicio`,
        })
      }
      if (!data.fecha_fin) {
        ctx.addIssue({
          path: ['fecha_fin'], code: 'custom',
          message: `La ${clase.etiqueta} requiere fecha de fin`,
        })
      }
    }

    // La autoridad electa empieza con la credencial del CNE, y el período
    // termina en su fecha (fase 4.1). Que no pase de cuatro años lo dice el
    // backend.
    if (clase.pide_datos_del_periodo) {
      if (!data.credencial_cne_numero?.trim()) {
        ctx.addIssue({ path: ['credencial_cne_numero'], code: 'custom', message: 'Indique el número de la credencial del CNE' })
      }
      if (!data.credencial_cne_fecha) {
        ctx.addIssue({ path: ['credencial_cne_fecha'], code: 'custom', message: 'Indique la fecha de la credencial' })
      }
      if (!data.fecha_fin_propuesta) {
        ctx.addIssue({ path: ['fecha_fin_propuesta'], code: 'custom', message: 'Indique cuándo termina el período' })
      } else if (data.fecha_efectiva && data.fecha_fin_propuesta <= data.fecha_efectiva) {
        ctx.addIssue({ path: ['fecha_fin_propuesta'], code: 'custom', message: 'El período termina después de empezar' })
      }
    }

    // El acto de una autoridad lleva su documento de origen (fases 4.1 y
    // 4.1b), salvo el inicio del período, que tiene la credencial.
    if (pideDocumentoDeOrigen(clase, tipoNombramiento) && !data.resolucion_numero?.trim()) {
      ctx.addIssue({
        path: ['resolucion_numero'], code: 'custom',
        message: 'Indique el documento de origen: la resolución del Consejo, el resultado del CNE, la renuncia aceptada o la partida de defunción',
      })
    }

    // El acto del contrato civil lleva el número del documento que firmaron
    // las partes (fase 4.2). El backend lo pide al suscribir.
    if (pideDocumentoFirmado(clase, tipoNombramiento, data.causal) && !data.resolucion_numero?.trim()) {
      ctx.addIssue({
        path: ['resolucion_numero'], code: 'custom',
        message: clase.pide_adenda
          ? 'Indique el número de la adenda firmada'
          : 'Indique el número del acta de terminación o de la partida de defunción',
      })
    }

    // La adenda dice lo que cambia (fase 4.2). Que de verdad cambie, y que el
    // plazo no salga del año del contrato, lo dice el backend, que lo conoce.
    if (clase.pide_adenda) {
      if (!data.puesto_destino_id && data.remuneracion_propuesta == null && !data.fecha_fin_propuesta) {
        ctx.addIssue({
          path: ['puesto_destino_id'], code: 'custom',
          message: 'Indique lo que cambia la adenda: el puesto, los honorarios o el plazo',
        })
      } else if (data.unidad_destino_id && !data.puesto_destino_id) {
        ctx.addIssue({ path: ['puesto_destino_id'], code: 'custom', message: 'Indique el puesto de la unidad a la que pasa' })
      }
      if (data.fecha_fin_propuesta && data.fecha_efectiva && data.fecha_fin_propuesta < data.fecha_efectiva) {
        ctx.addIssue({
          path: ['fecha_fin_propuesta'], code: 'custom',
          message: 'El plazo nuevo no puede terminar antes de que rija la adenda',
        })
      }
    }

    // Lo que cambia del contrato de trabajo de un obrero, con su adenda (fase
    // 4.3). Que la remuneración no baje, que el puesto sea del catálogo de
    // obreros y que algo cambie de verdad lo dice el backend, que conoce el
    // contrato.
    const cambio = clase.cambio_del_obrero
    if (cambio) {
      if (!data.resolucion_numero?.trim()) {
        ctx.addIssue({ path: ['resolucion_numero'], code: 'custom', message: 'Indique el número de la adenda firmada' })
      }

      if (cambio === 'ocupacion') {
        if (!data.puesto_destino_id) {
          ctx.addIssue({ path: ['puesto_destino_id'], code: 'custom', message: 'Indique el puesto de la ocupación nueva' })
        }
        if (!data.fecha_consentimiento) {
          ctx.addIssue({
            path: ['fecha_consentimiento'], code: 'custom',
            message: 'Indique la fecha del consentimiento escrito del trabajador',
          })
        } else if (data.fecha_efectiva && data.fecha_consentimiento > data.fecha_efectiva) {
          ctx.addIssue({
            path: ['fecha_consentimiento'], code: 'custom',
            message: 'El consentimiento es previo al cambio',
          })
        }
      }

      if (cambio === 'lugar') {
        if (!data.puesto_destino_id && !data.lugar_trabajo) {
          ctx.addIssue({
            path: ['lugar_trabajo'], code: 'custom',
            message: 'Indique la unidad y el puesto, o el lugar de trabajo',
          })
        } else if (data.unidad_destino_id && !data.puesto_destino_id) {
          ctx.addIssue({ path: ['puesto_destino_id'], code: 'custom', message: 'Indique el puesto de la unidad a la que pasa' })
        }
      }

      if (cambio === 'remuneracion' && data.remuneracion_propuesta == null) {
        ctx.addIssue({
          path: ['remuneracion_propuesta'], code: 'custom',
          message: 'Indique la remuneración nueva, mayor que la actual',
        })
      }
    }

    // La licencia del Consejo dice si es con o sin remuneración (fase 4.1b).
    if (clase.pide_datos_de_licencia_del_consejo && data.con_remuneracion == null) {
      ctx.addIssue({
        path: ['con_remuneracion'], code: 'custom',
        message: 'Indique si es con o sin remuneración, como dice la resolución',
      })
    }

    // El ascenso se gana en concurso: trae el acta (LOSEP Art. 68; fase 3.3).
    if (clase.pide_acta_concurso) {
      if (!data.acta_concurso_numero?.trim()) {
        ctx.addIssue({ path: ['acta_concurso_numero'], code: 'custom', message: 'Indique el número del acta del concurso' })
      }
      if (!data.acta_concurso_fecha) {
        ctx.addIssue({ path: ['acta_concurso_fecha'], code: 'custom', message: 'Indique la fecha del acta del concurso' })
      }
    }

    if (clase.pide_institucion_destino && !data.institucion_destino?.trim()) {
      ctx.addIssue({
        path: ['institucion_destino'], code: 'custom',
        message: 'Indique la institución de destino',
      })
    }

    // La restitución, con los cuatro datos de la causa y las remuneraciones
    // [TH N15]. Que la ejecutoría no sea futura ni anterior al cese lo dice el
    // backend, que conoce la cesación.
    if (clase.pide_datos_judiciales) {
      const obligatorios = [
        ['causa_judicial_numero', 'Indique el número de la causa'],
        ['unidad_judicial', 'Indique la unidad judicial o tribunal'],
        ['fecha_sentencia', 'Indique la fecha de la sentencia'],
        ['fecha_ejecutoria', 'Indique la fecha de ejecutoría'],
      ] as const

      for (const [campo, mensaje] of obligatorios) {
        if (!data[campo]?.trim()) ctx.addIssue({ path: [campo], code: 'custom', message: mensaje })
      }

      if (data.remuneraciones_dejadas_de_percibir == null) {
        ctx.addIssue({
          path: ['remuneraciones_dejadas_de_percibir'], code: 'custom',
          message: 'Indique las remuneraciones dejadas de percibir (0 si no hay)',
        })
      }

      if (data.fecha_sentencia && data.fecha_ejecutoria && data.fecha_ejecutoria < data.fecha_sentencia) {
        ctx.addIssue({
          path: ['fecha_ejecutoria'], code: 'custom',
          message: 'La ejecutoría no puede ser anterior a la sentencia',
        })
      }
    }

    // Lo que reubica al servidor necesita a dónde: sin puesto destino no hay
    // situación propuesta que registrar, y el backend rechaza el registro.
    if (clase.pide_situacion_propuesta && !data.puesto_destino_id) {
      ctx.addIssue({
        path: ['puesto_destino_id'], code: 'custom',
        message: clase.pide_contratacion ? 'Seleccione el puesto' : 'Indique el puesto al que será asignado',
      })
    }

    // El ingreso es la única acción que crea un vínculo, así que es la única
    // que exige nombramiento y unidad desde el formulario.
    if (clase.pide_contratacion) {
      if (!data.tipo_nombramiento_propuesto) {
        ctx.addIssue({
          path: ['tipo_nombramiento_propuesto'], code: 'custom',
          message: 'Seleccione el tipo de nombramiento',
        })
      }
      if (!data.unidad_destino_id) {
        ctx.addIssue({
          path: ['unidad_destino_id'], code: 'custom',
          message: 'Seleccione la unidad administrativa',
        })
      }

      // El provisional dice para qué se da (LOSEP 17 b), y el de prueba trae
      // el acta del concurso (fase 3.2).
      if (data.tipo_nombramiento_propuesto === 'nombramiento_provisional') {
        if (!data.supuesto_provisional_propuesto) {
          ctx.addIssue({
            path: ['supuesto_provisional_propuesto'], code: 'custom',
            message: 'Seleccione el supuesto del nombramiento provisional',
          })
        } else if (data.supuesto_provisional_propuesto === 'de_prueba') {
          if (!data.acta_concurso_numero?.trim()) {
            ctx.addIssue({ path: ['acta_concurso_numero'], code: 'custom', message: 'Indique el número del acta del concurso' })
          }
          if (!data.acta_concurso_fecha) {
            ctx.addIssue({ path: ['acta_concurso_fecha'], code: 'custom', message: 'Indique la fecha del acta del concurso' })
          }
        }
      }
    }

    if (data.caucionado && !data.caucion_numero) {
      ctx.addIssue({
        path: ['caucion_numero'], code: 'custom',
        message: 'Registre el número de caución',
      })
    }
  })
}

/**
 * ¿Es un acto de autoridad electa (fase 4.1)? Sin acción de personal ni
 * firmantes. Lo responde el catálogo: el inicio del período siempre, y el resto
 * de clases según el nombramiento vigente.
 */
export function esActoDeAutoridad(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
): boolean {
  if (!clase) return false
  if (clase.pide_datos_del_periodo) return true

  return !!tipoNombramiento && clase.acto_de_autoridad_para.some((n) => n === tipoNombramiento)
}

/** El acto de autoridad lleva su documento de origen; el inicio del período, la credencial. */
export function pideDocumentoDeOrigen(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
): boolean {
  return esActoDeAutoridad(clase, tipoNombramiento) && !clase?.pide_datos_del_periodo
}

/**
 * ¿Es un acto del contrato civil de Servicios Profesionales (fase 4.2)? Sin
 * acción de personal ni firmantes. Lo responde el catálogo: en el ingreso, por
 * el nombramiento que propone; en el resto, por el vigente.
 */
export function esDelContratoCivil(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
  propuesto?: string | null,
): boolean {
  if (!clase) return false

  const nombramiento = clase.pide_contratacion ? propuesto : tipoNombramiento

  return !!nombramiento && clase.contrato_civil_para.some((n) => n === nombramiento)
}

/**
 * El acto del contrato civil lleva el número del documento firmado: la adenda o
 * el acta. No el contrato, que tiene el suyo, ni su vencimiento.
 */
export function pideDocumentoFirmado(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
  causal?: string | null,
): boolean {
  return !clase?.pide_contratacion
    && causal !== 'contrato_finalizado'
    && esDelContratoCivil(clase, tipoNombramiento)
}

/**
 * ¿Es un acto de un obrero, con su hoja de registro interno (fase 4.3)? Sin
 * acción de personal; la hoja la firma solo Talento Humano. Misma regla que el
 * contrato civil: en el ingreso decide el nombramiento propuesto.
 */
export function esDelRegistroInterno(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
  propuesto?: string | null,
): boolean {
  if (!clase) return false

  const nombramiento = clase.pide_contratacion ? propuesto : tipoNombramiento

  return !!nombramiento && clase.documento_ct_para.some((n) => n === nombramiento)
}

/** ¿El traslado de este servidor pide el informe de la UATH? Lo responde el catálogo. */
export function pideInformeUath(
  clase: ClaseDelCatalogo | undefined,
  tipoNombramiento?: string | null,
): boolean {
  return !!tipoNombramiento && !!clase?.pide_informe_uath_para.some((n) => n === tipoNombramiento)
}
