import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { ConsultaMedicaFormData } from '../schemas/consultaMedica.schema'
import type { DiagnosticoCie10 } from '../services/cie10Service'

/** El texto de un campo del editor, sin las etiquetas que deja vacío. */
export function limpiarHtml(valor?: string | null): string {
  return (valor ?? '').replace(/<[^>]*>/g, '').trim()
}

/**
 * Si hay algo que merezca guardarse, o el formulario sigue como nació.
 *
 * Sin esto se guardaba un borrador en cuanto se abría la consulta, y al volver
 * a entrar la pantalla anunciaba que «recuperó lo que estaba escrito» sin que
 * nadie hubiera escrito nada: un aviso que miente es peor que no avisar.
 *
 * El motivo de consulta no cuenta por sí solo: viene rellenado con lo que pidió
 * el paciente al agendar, no con lo que el médico teclea.
 */
export function hayAlgoEscrito(
  valores: Partial<ConsultaMedicaFormData>,
  motivoInicial: string,
  cie10Principal: DiagnosticoCie10 | null,
  cie10Secundarios: DiagnosticoCie10[],
): boolean {
  const conTexto = [
    valores.enfermedad_actual,
    valores.examen_fisico,
    valores.diagnostico_detallado,
    valores.plan_tratamiento,
    valores.notas_medico,
  ].some((v) => !!limpiarHtml(v))

  return (
    conTexto ||
    !!cie10Principal ||
    cie10Secundarios.length > 0 ||
    (valores.motivo_consulta ?? '') !== motivoInicial
  )
}

/** «hace un momento», «hace 12 minutos», o la hora si ya es de antes. */
export function formatCuando(iso: string): string {
  const minutos = Math.floor((Date.now() - new Date(iso).getTime()) / 60_000)

  if (minutos < 1) return 'hace un momento'
  if (minutos < 60) return `hace ${minutos} minuto${minutos === 1 ? '' : 's'}`

  return `el ${new Date(iso).toLocaleString('es-EC', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })}`
}

/**
 * El mismo plazo que aplica el servidor (`ConsultaMedicaController`). Aquí solo
 * decide si se ofrece el botón: quien manda es el backend, que rechaza igual
 * aunque alguien llame a la API a mano.
 */
const HORAS_PARA_CORREGIR = 24

/**
 * Por qué no se puede corregir, o null si sí se puede.
 *
 * La nota la firma quien atendió, y solo mientras es reciente. Pasado el plazo
 * corregir dejaría de ser corregir: lo que corresponde es una consulta nueva.
 */
export function motivoParaNoEditar(
  consulta: ConsultaMedica,
  usuarioId?: number,
): string | null {
  if (usuarioId !== undefined && consulta.medico_id !== usuarioId) {
    return 'Solo quien atendió la consulta puede corregirla.'
  }

  if (consulta.created_at) {
    const horas =
      (Date.now() - new Date(consulta.created_at).getTime()) / 3_600_000

    if (horas >= HORAS_PARA_CORREGIR) {
      return 'Ya pasaron más de 24 horas: para añadir algo, registre una consulta nueva.'
    }
  }

  return null
}
