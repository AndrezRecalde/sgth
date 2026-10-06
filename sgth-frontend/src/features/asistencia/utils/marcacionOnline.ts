import type { MarcacionBiometrica } from '@/types/api'

/**
 * La secuencia de una jornada en la marcación en línea.
 *
 * El biométrico solo distingue dos teclas: `I` (entrada o salida de la
 * institución) y `O` (almuerzo). Las cuatro acciones de la pantalla son esas
 * dos teclas en un orden: entrada, salida y retorno de almuerzo, salida. El
 * procedimiento del biométrico decide cuál es cuál por la hora, así que una
 * «Salida» pulsada a las 09:00 cuenta como otra entrada. Por eso la pantalla
 * resalta la siguiente acción esperada y pide confirmar la que va fuera de
 * orden o ya está registrada.
 */

export type ClaveAccion = 'Entrada' | 'AlmuerzoSalida' | 'AlmuerzoRetorno' | 'Salida'

export interface AccionMarcacion {
  /** Columna de `MarcacionBiometrica` donde queda la hora registrada. */
  clave: ClaveAccion
  tipo: 'I' | 'O'
  etiqueta: string
}

export const ACCIONES: AccionMarcacion[] = [
  { clave: 'Entrada',         tipo: 'I', etiqueta: 'Entrada' },
  { clave: 'AlmuerzoSalida',  tipo: 'O', etiqueta: 'Salida a almuerzo' },
  { clave: 'AlmuerzoRetorno', tipo: 'O', etiqueta: 'Retorno de almuerzo' },
  { clave: 'Salida',          tipo: 'I', etiqueta: 'Salida' },
]

type EstadoDia = Pick<MarcacionBiometrica, ClaveAccion | 'Horario' | 'HoraAlmuerzoSalidaProgramada'> | null | undefined

/**
 * Las acciones que corresponden hoy. Una jornada única no tiene almuerzo en
 * su horario: sus dos pasos se omiten, salvo que ya haya marcas de almuerzo
 * (entonces se muestran, para que se vean). Sin horario cargado no se sabe, y
 * van las cuatro.
 */
export function accionesDelDia(estado: EstadoDia): AccionMarcacion[] {
  const sinAlmuerzo = !!estado?.Horario
    && !estado.HoraAlmuerzoSalidaProgramada
    && !estado.AlmuerzoSalida
    && !estado.AlmuerzoRetorno

  return sinAlmuerzo ? ACCIONES.filter((a) => a.tipo === 'I') : ACCIONES
}

/** La hora registrada para una acción, `HH:mm`, o null. */
export function horaDe(estado: EstadoDia, clave: ClaveAccion): string | null {
  const hora = estado?.[clave]
  return hora ? hora.substring(0, 5) : null
}

/** La primera acción del día que aún no tiene hora; null si ya están todas. */
export function siguienteAccion(estado: EstadoDia, acciones: AccionMarcacion[]): AccionMarcacion | null {
  return acciones.find((a) => !horaDe(estado, a.clave)) ?? null
}

/**
 * Por qué conviene confirmar antes de registrar, o null si es la acción
 * esperada. Se confirma lo que ya está registrado y lo que va fuera de orden;
 * no se impide, porque una marca puede faltar (un corte de luz en el reloj) o
 * haber que repetirla.
 */
export function motivoParaConfirmar(
  accion: AccionMarcacion,
  estado: EstadoDia,
  siguiente: AccionMarcacion | null,
): string | null {
  const registrada = horaDe(estado, accion.clave)
  if (registrada) {
    return `Ya registró «${accion.etiqueta}» hoy a las ${registrada}. Si registra otra, el biométrico tomará la que corresponda por la hora.`
  }

  if (!siguiente) {
    return `Ya completó las marcaciones de hoy. ¿Registrar «${accion.etiqueta}» de todos modos?`
  }

  if (siguiente.clave !== accion.clave) {
    return `Aún no registra «${siguiente.etiqueta}». El biométrico ordena las marcas por la hora, así que «${accion.etiqueta}» podría contarse como otra cosa.`
  }

  return null
}
