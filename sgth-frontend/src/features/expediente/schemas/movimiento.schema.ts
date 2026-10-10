import { z } from 'zod/v4'
import type { CatalogoAccionesPersonal } from '@/types/api'
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

  // Datos de la contratación. Solo existen en el ingreso, que es la única
  // acción que da origen a un contrato; en el resto ni se muestran ni se
  // envían.
  tipo_nombramiento_propuesto: z.string().optional().nullable(),
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
export function crearMovimientoSchema(catalogo: CatalogoAccionesPersonal) {
  return camposDelFormulario.superRefine((data, ctx) => {
    const clase = buscarClase(catalogo, data.clase)

    if (!clase) return

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

    if (clase.pide_institucion_destino && !data.institucion_destino?.trim()) {
      ctx.addIssue({
        path: ['institucion_destino'], code: 'custom',
        message: 'Indique la institución de destino',
      })
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
    }

    if (data.caucionado && !data.caucion_numero) {
      ctx.addIssue({
        path: ['caucion_numero'], code: 'custom',
        message: 'Registre el número de caución',
      })
    }
  })
}
