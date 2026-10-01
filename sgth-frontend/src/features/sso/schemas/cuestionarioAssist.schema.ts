import { z } from 'zod/v4'
import type {
  RespuestaAssistPayload, RespuestaSustanciaAssist, SustanciaAssistInfo,
} from '../services/assistService'

/**
 * El cuestionario ASSIST que responde el personal, para React Hook Form.
 *
 * Estaba capturado con cinco `useState` y validado a mano con notificaciones:
 * «Debe responder todas las preguntas de esta sección» sin marcar cuál, sobre
 * cinco o siete desplegables de pregunta larga. Quien lo respondía tenía que
 * encontrar el hueco a ojo.
 *
 * Es una FÁBRICA y no un esquema fijo porque qué preguntas aplican lo decide
 * el servidor: P5 no aplica a todas las sustancias (`incluye_pregunta_5`), y
 * P3 a P5 solo se preguntan si P2 no es «nunca» —la lógica de preguntas filtro
 * del manual de la OMS, Cap. 13—. Esas dos condiciones no se pueden escribir
 * en un esquema estático sin duplicar el catálogo del backend.
 */

/**
 * Las respuestas de una sustancia. Todas opcionales en el tipo: cuáles son
 * obligatorias lo decide el `superRefine`, que es quien conoce las preguntas
 * filtro.
 */
const respuestaSustanciaSchema = z.object({
  p2: z.string().optional(),
  p3: z.string().optional(),
  p4: z.string().optional(),
  p5: z.string().optional(),
  p6: z.string().optional(),
  p7: z.string().optional(),
})

const baseSchema = z.object({
  /** «No he consumido ninguna de estas sustancias»: detiene la entrevista. */
  sinConsumo: z.boolean(),
  /** Los códigos de sustancia que la persona marcó en P1. */
  seleccionadas: z.array(z.string()),
  /** Las respuestas, indexadas por código de sustancia. */
  sustancias: z.record(z.string(), respuestaSustanciaSchema),
  /** P8, el consumo por vía inyectada. */
  uso_inyectable: z.string().nullable(),
})

export type CuestionarioAssistFormData = z.infer<typeof baseSchema>

export const VALORES_INICIALES_ASSIST: CuestionarioAssistFormData = {
  sinConsumo: false,
  seleccionadas: [],
  sustancias: {},
  uso_inyectable: null,
}

const FALTA = 'Elija una respuesta para continuar'

/**
 * El esquema del cuestionario, con las preguntas filtro del manual aplicadas.
 *
 * @param sustancias       el catálogo que manda el servidor, para saber a quién aplica P5
 * @param opcionesFrecuencia3m  claves válidas de las preguntas de los últimos 3 meses
 * @param opcionesFrecuenciaVida claves válidas de las preguntas de «alguna vez»
 */
export function esquemaCuestionarioAssist(
  sustancias: Record<string, SustanciaAssistInfo>,
  opcionesFrecuencia3m: string[],
  opcionesFrecuenciaVida: string[],
) {
  return baseSchema.superRefine((datos, ctx) => {
    // Manual ASSIST, Fig. 1: si no ha consumido ninguna sustancia, se detiene
    // la entrevista. No hay nada más que validar, ni P8.
    if (datos.sinConsumo) return

    if (datos.seleccionadas.length === 0) {
      ctx.addIssue({
        code: 'custom',
        path: ['seleccionadas'],
        message: 'Marque las sustancias que ha consumido, o marque que no ha consumido ninguna',
      })
      return
    }

    for (const codigo of datos.seleccionadas) {
      const respuestas = datos.sustancias[codigo] ?? {}

      const exigir = (campo: keyof typeof respuestas, validas: string[]) => {
        const valor = respuestas[campo]
        if (! valor || ! validas.includes(valor)) {
          ctx.addIssue({
            code: 'custom',
            path: ['sustancias', codigo, campo],
            message: FALTA,
          })
        }
      }

      exigir('p2', opcionesFrecuencia3m)

      // Preguntas filtro: si no la ha consumido en los últimos 3 meses, P3 a
      // P5 no se preguntan y por lo tanto no se exigen.
      if (respuestas.p2 && respuestas.p2 !== 'nunca') {
        exigir('p3', opcionesFrecuencia3m)
        exigir('p4', opcionesFrecuencia3m)
        if (sustancias[codigo]?.incluye_pregunta_5) {
          exigir('p5', opcionesFrecuencia3m)
        }
      }

      // P6 y P7 son de «alguna vez en la vida»: se preguntan siempre, aunque
      // no la haya consumido en los últimos 3 meses.
      exigir('p6', opcionesFrecuenciaVida)
      exigir('p7', opcionesFrecuenciaVida)
    }

    if (! datos.uso_inyectable || ! opcionesFrecuenciaVida.includes(datos.uso_inyectable)) {
      ctx.addIssue({ code: 'custom', path: ['uso_inyectable'], message: FALTA })
    }
  })
}

/**
 * Lo que se envía, a partir de lo que se respondió.
 *
 * Dos cosas que el formulario anterior no hacía:
 *
 * - Solo viajan las sustancias SELECCIONADAS. Antes se enviaba el mapa
 *   completo, así que deseleccionar una después de responderla dejaba sus
 *   respuestas en el envío.
 * - Si P2 es «nunca», P3 a P5 NO viajan. Esas tres preguntas no se le hicieron
 *   —el formulario las esconde—, pero si las había contestado antes de cambiar
 *   P2, el valor se quedaba en el estado y se guardaba. En un tamizaje anónimo
 *   eso es una respuesta retractada que después nadie puede desmentir.
 */
export function cargaUtilAssist(datos: CuestionarioAssistFormData): RespuestaAssistPayload {
  if (datos.sinConsumo) {
    return { sustancias: {} }
  }

  const sustancias: Record<string, RespuestaSustanciaAssist> = {}

  for (const codigo of datos.seleccionadas) {
    const r = datos.sustancias[codigo] ?? {}

    // Las tres obligatorias ya las garantizó el esquema: a este punto no se
    // llega sin pasar por `superRefine`. El `?? ''` evita la aserción de tipo
    // que la regla 09 prohíbe, y si alguna vez llegara vacía el backend la
    // rechaza con un 422 en vez de guardar una fila a medias.
    const consumioUltimos3Meses = !! r.p2 && r.p2 !== 'nunca'

    sustancias[codigo] = {
      p2: r.p2 ?? '',
      ...(consumioUltimos3Meses && r.p3 ? { p3: r.p3 } : {}),
      ...(consumioUltimos3Meses && r.p4 ? { p4: r.p4 } : {}),
      ...(consumioUltimos3Meses && r.p5 ? { p5: r.p5 } : {}),
      p6: r.p6 ?? '',
      p7: r.p7 ?? '',
    }
  }

  return { sustancias, uso_inyectable: datos.uso_inyectable ?? undefined }
}
