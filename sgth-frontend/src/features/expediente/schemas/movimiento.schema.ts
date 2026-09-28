import { z } from 'zod/v4'
import {
  SUBTIPOS_DE_ACCION, TIPOS_DEL_FORMULARIO, esComision, requiereSubtipo,
  reubicaAlServidor,
} from '../utils/taxonomiaAccionPersonal'

export const movimientoSchema = z.object({
  // Las dos listas salen de la taxonomía, que es el espejo del enum del
  // backend. Escritas aquí a mano eran una tercera copia —y una que nadie
  // comprobaba contra las otras dos—.
  tipo_movimiento: z.enum(TIPOS_DEL_FORMULARIO),
  subtipo_movimiento: z.enum(SUBTIPOS_DE_ACCION).optional().nullable(),

  descripcion:     z.string().min(1, 'La descripción es requerida').max(1000),
  fecha_efectiva:  z.string().min(1, 'La fecha efectiva es requerida'),
  fecha_inicio:    z.string().optional().nullable(),
  fecha_fin:       z.string().optional().nullable(),

  // Situación propuesta — solo la piden los subtipos que reubican al servidor.
  unidad_destino_id: z.number().optional().nullable(),
  puesto_destino_id: z.number().optional().nullable(),
  remuneracion_propuesta: z.number().optional().nullable(),
  partida_presupuestaria_id: z.number().optional().nullable(),
  lugar_trabajo:   z.string().max(255).optional().nullable(),

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
  // 'codigo' se retiró del esquema: ningún campo del formulario lo captura. Es
  // el campo libre del backend, distinto de `codigo_registro` —el correlativo
  // AP-AAAA-NNNN que el sistema asigna al registrar—, y declararlo aquí hacía
  // creer que el formulario lo pedía.
  caucionado:      z.boolean().optional().nullable(),
  caucion_numero:  z.string().max(100).optional().nullable(),
  caucion_fecha:   z.string().optional().nullable(),
}).superRefine((data, ctx) => {
  // Las tres condiciones —qué tipo exige subtipo, qué es una comisión y qué
  // reubica al servidor— las decide la taxonomía. Repetidas aquí, cambiar una
  // regla de Talento Humano obligaba a acordarse de los dos sitios.
  if (requiereSubtipo(data.tipo_movimiento) && !data.subtipo_movimiento) {
    ctx.addIssue({
      path: ['subtipo_movimiento'], code: 'custom',
      message: 'Seleccione el subtipo de la acción de personal',
    })
  }

  if (esComision(data.subtipo_movimiento)) {
    if (!data.fecha_inicio) {
      ctx.addIssue({
        path: ['fecha_inicio'], code: 'custom',
        message: 'La comisión de servicios requiere fecha de inicio',
      })
    }
    if (!data.fecha_fin) {
      ctx.addIssue({
        path: ['fecha_fin'], code: 'custom',
        message: 'La comisión de servicios requiere fecha de fin',
      })
    }
  }

  // Traslado y traspaso reubican al servidor: sin puesto destino no hay
  // situación propuesta que registrar, y el backend rechaza el registro.
  if (reubicaAlServidor(data.subtipo_movimiento) && !data.puesto_destino_id) {
    ctx.addIssue({
      path: ['puesto_destino_id'], code: 'custom',
      message: 'Indique el puesto al que será asignado',
    })
  }

  // El ingreso es la única acción que crea un vínculo, así que es la única que
  // exige nombramiento, unidad y puesto desde el formulario: sin ellos el
  // backend rechaza el registro más adelante.
  if (data.tipo_movimiento === 'ingreso') {
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
    if (!data.puesto_destino_id) {
      ctx.addIssue({
        path: ['puesto_destino_id'], code: 'custom',
        message: 'Seleccione el puesto',
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

export type MovimientoFormData = z.infer<typeof movimientoSchema>
