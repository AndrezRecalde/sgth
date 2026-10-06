import type { MarcacionBiometrica } from '@/types/api'

/**
 * Los permisos de un día de marcaciones y qué cubren.
 *
 * El procedimiento del biométrico entrega los permisos del día como listas
 * unidas por coma —nombres, inicios y fines en columnas aparte— y con los
 * nombres en mayúsculas, como los guarda Sirha7. Aquí se arman de nuevo y se
 * decide qué parte del día explica cada uno: una celda de hora vacía con un
 * permiso que la cubre no es una marca que falta.
 */

export interface PermisoDelDia {
  nombre: string
  /** `HH:mm`, o null si el biométrico no lo trae. */
  desde: string | null
  hasta: string | null
}

export interface CoberturaDelDia {
  /** Un permiso que abarca la jornada entera (vacaciones, descanso médico…). */
  diaCompleto: PermisoDelDia | null
  entrada:     PermisoDelDia | null
  almuerzo:    PermisoDelDia | null
  salida:      PermisoDelDia | null
}

// Siglas que no se pasan a minúsculas al normalizar el nombre.
const SIGLAS = new Set(['IESS', 'GADPE', 'TH', 'UATH'])

// Sin horario no hay hora de entrada ni de salida contra la cual medir: un
// permiso de seis horas o más se toma como de jornada completa.
const MINUTOS_JORNADA_SIN_HORARIO = 6 * 60

/** «CITA MÉDICA IESS» → «Cita médica IESS». */
export function nombreLegible(nombre: string): string {
  const palabras = nombre.trim().toLowerCase().split(/\s+/)
  const texto = palabras
    .map((p) => (SIGLAS.has(p.toUpperCase()) ? p.toUpperCase() : p))
    .join(' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

function lista(valor?: string | null): string[] {
  return valor ? valor.split(', ').map((v) => v.trim()) : []
}

/** `YYYY-MM-DD HH:mm:ss` o `HH:mm:ss` → `HH:mm`. */
function horaMinuto(valor?: string | null): string | null {
  const coincidencia = valor?.match(/(\d{2}:\d{2})(:\d{2})?$/)
  return coincidencia ? coincidencia[1] : null
}

function minutos(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number)
  return h * 60 + m
}

export function permisosDelDia(
  fila: Pick<MarcacionBiometrica, 'TipoPermiso' | 'PermisoDesde' | 'PermisoHasta'>,
): PermisoDelDia[] {
  const desdes = lista(fila.PermisoDesde)
  const hastas = lista(fila.PermisoHasta)
  return lista(fila.TipoPermiso).map((nombre, i) => ({
    nombre: nombreLegible(nombre),
    desde: horaMinuto(desdes[i]),
    hasta: horaMinuto(hastas[i]),
  }))
}

type FilaCobertura = Pick<
  MarcacionBiometrica,
  | 'TipoPermiso' | 'PermisoDesde' | 'PermisoHasta'
  | 'HoraEntradaProgramada' | 'HoraSalidaProgramada'
  | 'HoraAlmuerzoSalidaProgramada' | 'HoraAlmuerzoRetornoProgramada'
>

export function coberturaDelDia(fila: FilaCobertura): CoberturaDelDia {
  const permisos = permisosDelDia(fila).filter(
    (p): p is PermisoDelDia & { desde: string; hasta: string } => !!p.desde && !!p.hasta,
  )
  const entrada = horaMinuto(fila.HoraEntradaProgramada)
  const salida = horaMinuto(fila.HoraSalidaProgramada)
  const almuerzoIni = horaMinuto(fila.HoraAlmuerzoSalidaProgramada)
  const almuerzoFin = horaMinuto(fila.HoraAlmuerzoRetornoProgramada)

  const diaCompleto = permisos.find((p) =>
    entrada && salida
      ? p.desde <= entrada && p.hasta >= salida
      : minutos(p.hasta) - minutos(p.desde) >= MINUTOS_JORNADA_SIN_HORARIO,
  ) ?? null

  if (diaCompleto) {
    return { diaCompleto, entrada: diaCompleto, almuerzo: diaCompleto, salida: diaCompleto }
  }

  return {
    diaCompleto: null,
    entrada: entrada ? permisos.find((p) => p.desde <= entrada && p.hasta > entrada) ?? null : null,
    almuerzo: almuerzoIni && almuerzoFin
      ? permisos.find((p) => p.desde <= almuerzoIni && p.hasta >= almuerzoFin) ?? null
      : null,
    salida: salida ? permisos.find((p) => p.desde < salida && p.hasta >= salida) ?? null : null,
  }
}

/**
 * El permiso que explica cada celda de hora de una fila. Un permiso cubre la
 * entrada solo si se llegó antes de que terminara, y la salida solo si se
 * salió después de que empezara: fuera de eso, el atraso o la salida
 * anticipada se siguen señalando.
 */
export function permisoPorCelda(fila: FilaCobertura & Pick<MarcacionBiometrica, 'Entrada' | 'Salida'>) {
  const c = coberturaDelDia(fila)
  const llegada = horaMinuto(fila.Entrada)
  const partida = horaMinuto(fila.Salida)
  return {
    dia: !!c.diaCompleto,
    entrada: c.entrada && (!llegada || llegada <= (c.entrada.hasta ?? '')) ? c.entrada : null,
    salida: c.salida && (!partida || partida >= (c.salida.desde ?? '')) ? c.salida : null,
    almuerzo: c.almuerzo,
  }
}

/**
 * Por qué una marca está en «Por revisar», a partir de lo que entrega el
 * procedimiento («17:07:02 O»). Son las mismas tres reglas de
 * `sp_SGTH_MarcacionesPorCedula`.
 */
export function motivoPorRevisar(marca: string): { hora: string; motivo: string } {
  const [horaCompleta, tecla = '?'] = marca.trim().split(/\s+/)
  const hora = horaMinuto(horaCompleta) ?? horaCompleta
  const motivo =
    tecla === 'O' ? '«O» fuera del almuerzo'
    : tecla === 'I' ? '«I» a la hora del almuerzo'
    : `tecla «${tecla}» desconocida`
  return { hora, motivo }
}
