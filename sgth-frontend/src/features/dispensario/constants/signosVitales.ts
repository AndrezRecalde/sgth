import type { SemanticTone } from '@/config/design.tokens'

/**
 * Rangos esperables de signos vitales en adultos.
 *
 * Espejo de `ValoracionSignosVitales` del backend, que es quien decide el nivel
 * que se guarda. Aquí solo sirven para avisar mientras se escribe: quien toma
 * el triaje debe verlo en el momento, no al recibir la respuesta.
 *
 * Si los umbrales cambian allí —están para que el personal médico los revise—
 * hay que cambiarlos aquí. Son dos listas cortas y avisar tarde es peor que la
 * duplicación.
 */
export const NIVEL_ALERTA = {
  normal:       { etiqueta: 'Normal',            tono: 'success' as SemanticTone },
  atencion:     { etiqueta: 'Requiere atención', tono: 'warning' as SemanticTone },
  critico:      { etiqueta: 'Crítico',           tono: 'danger'  as SemanticTone },
  no_evaluado:  { etiqueta: 'Sin valorar',       tono: 'neutral' as SemanticTone },
} as const

export type NivelAlerta = keyof typeof NIVEL_ALERTA

/** [crítico bajo, atención bajo, atención alto, crítico alto] */
const RANGOS: Record<string, [number, number, number, number]> = {
  presion_sistolica:       [90, 100, 139, 180],
  presion_diastolica:      [60,  65,  89, 110],
  frecuencia_cardiaca:     [50,  60,  99, 120],
  frecuencia_respiratoria: [10,  12,  20,  30],
  temperatura_c:           [35,  36, 37.4, 39],
  saturacion_oxigeno:      [90,  94, 100, 100],
  glucosa:                 [54,  70, 180, 300],
}

/** Nivel de una constante suelta, para pintar el campo mientras se escribe. */
export function nivelDeConstante(
  campo: string,
  valor: number | null | undefined,
): NivelAlerta | null {
  const rango = RANGOS[campo]
  if (!rango || valor === null || valor === undefined || Number.isNaN(valor)) {
    return null
  }

  const [criticoBajo, atencionBajo, atencionAlto, criticoAlto] = rango

  if (valor < criticoBajo || valor > criticoAlto) return 'critico'
  if (valor < atencionBajo || valor > atencionAlto) return 'atencion'
  return 'normal'
}

/** El peor nivel de todas las constantes capturadas. */
export function nivelGeneral(
  constantes: Record<string, number | null | undefined>,
): NivelAlerta {
  let hayAtencion = false

  for (const campo of Object.keys(RANGOS)) {
    const nivel = nivelDeConstante(campo, constantes[campo])
    if (nivel === 'critico') return 'critico'
    if (nivel === 'atencion') hayAtencion = true
  }

  return hayAtencion ? 'atencion' : 'normal'
}

/** Las constantes fuera de rango, con su etiqueta, para listarlas en el aviso. */
export function hallazgos(
  constantes: Record<string, number | null | undefined>,
): { campo: string; etiqueta: string; valor: number; nivel: NivelAlerta }[] {
  return Object.keys(RANGOS).flatMap((campo) => {
    const valor = constantes[campo]
    const nivel = nivelDeConstante(campo, valor)

    if (nivel === null || nivel === 'normal' || valor === null || valor === undefined) {
      return []
    }

    return [{ campo, etiqueta: ETIQUETAS[campo], valor, nivel }]
  })
}

const ETIQUETAS: Record<string, string> = {
  presion_sistolica:       'Presión sistólica',
  presion_diastolica:      'Presión diastólica',
  frecuencia_cardiaca:     'Frecuencia cardiaca',
  frecuencia_respiratoria: 'Frecuencia respiratoria',
  temperatura_c:           'Temperatura',
  saturacion_oxigeno:      'Saturación de oxígeno',
  glucosa:                 'Glucosa',
}
