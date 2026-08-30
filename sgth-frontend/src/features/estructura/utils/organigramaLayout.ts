import type { UnidadConRelaciones } from '@/types/api'

/**
 * Colocación de los nodos del organigrama sobre el lienzo.
 *
 * ECharts dibuja el grafo con `layout: 'none'`, así que las coordenadas las
 * calcula esto. Vive aparte del componente porque es aritmética pura y sin
 * ella el gráfico tenía dos responsabilidades: repartir el espacio y pintar.
 *
 * El reparto es por filas: una por categoría de proceso, y dentro de cada
 * fila las unidades administrativas en horizontal con sus subprocesos
 * apilados debajo. El alto de la fila lo fija la unidad con más subprocesos,
 * que es lo que impide que una fila invada la siguiente.
 */

export interface NodoOrganigrama {
  id: string
  name: string
  x: number
  y: number
  unidad: UnidadConRelaciones
  /** Profundidad en el dibujo: 0 institución, 1 unidad, 2 subproceso. */
  profundidad: number
}

export interface EnlaceOrganigrama {
  source: string
  target: string
}

export interface GrafoOrganigrama {
  nodos: NodoOrganigrama[]
  enlaces: EnlaceOrganigrama[]
  /** Alto necesario para que quepa todo sin recortar la última fila. */
  alto: number
}

/** Categorías de proceso, en el orden en que se leen los orgánicos públicos. */
export const CATEGORIAS = ['G', 'HA', 'AV', 'HAP'] as const

export const TITULOS_CATEGORIA: Record<string, string> = {
  G:   'Procesos gobernantes',
  HA:  'Procesos habilitantes de asesoría',
  AV:  'Procesos agregadores de valor',
  HAP: 'Procesos habilitantes de apoyo',
}

/** Medidas del lienzo, en las mismas unidades que usa ECharts. */
export const MEDIDAS = {
  centroX: 600,
  raizY: 50,
  /** Separación horizontal entre unidades de una misma fila. */
  separacionX: 190,
  /** Distancia de la primera fila de unidades a la raíz. */
  primeraFilaY: 180,
  /** Alto que ocupa una unidad antes de que empiecen sus subprocesos. */
  altoUnidad: 48,
  /**
   * Separación vertical entre subprocesos apilados. Da cabida a las dos
   * líneas del nodo más su aire: por debajo de eso los nombres largos se
   * montan sobre el subproceso siguiente.
   */
  separacionSubproceso: 40,
  /** Aire entre el último subproceso de una fila y la fila siguiente. */
  margenFila: 50,
  tamanoRaiz:       [160, 52] as [number, number],
  tamanoUnidad:     [150, 48] as [number, number],
  tamanoSubproceso: [150, 32] as [number, number],
}

/** Parte un nombre largo en líneas para que quepa en el nodo. */
export function partirTexto(texto: string, maximo = 18, maxLineas = 3): string {
  if (texto.length <= maximo) return texto

  const lineas: string[] = []
  let actual = ''

  for (const palabra of texto.split(' ')) {
    if (`${actual} ${palabra}`.trim().length > maximo) {
      if (actual) lineas.push(actual)
      actual = palabra
    } else {
      actual = `${actual} ${palabra}`.trim()
    }
  }
  if (actual) lineas.push(actual)

  // Lo que no cabe se recorta con puntos suspensivos en vez de desbordar el
  // nodo: el nombre completo sigue estando en el tooltip.
  if (lineas.length > maxLineas) {
    const visibles = lineas.slice(0, maxLineas)
    visibles[maxLineas - 1] = `${visibles[maxLineas - 1]}…`
    return visibles.join('\n')
  }

  return lineas.join('\n')
}

/**
 * Agrupa las unidades del segundo nivel por su tipo de proceso.
 *
 * Una unidad sin tipo cae en «agregadores de valor», que es donde el PDF la
 * coloca también: las dos vistas cuentan lo mismo aunque falte el dato.
 */
export function agruparPorCategoria(
  unidades: UnidadConRelaciones[]
): Record<string, UnidadConRelaciones[]> {
  const grupos: Record<string, UnidadConRelaciones[]> =
    Object.fromEntries(CATEGORIAS.map(c => [c, []]))

  for (const unidad of unidades) {
    const acronimo = unidad.tipo_unidad?.acronimo ?? 'AV'
    grupos[acronimo in grupos ? acronimo : 'AV'].push(unidad)
  }

  return grupos
}

export function construirGrafo(
  raiz: UnidadConRelaciones | undefined,
  { mostrarSubprocesos }: { mostrarSubprocesos: boolean }
): GrafoOrganigrama {
  const nodos: NodoOrganigrama[] = []
  const enlaces: EnlaceOrganigrama[] = []

  if (!raiz) return { nodos, enlaces, alto: 400 }

  nodos.push({
    id: 'root',
    name: partirTexto(raiz.nombre ?? 'Institución', 20),
    x: MEDIDAS.centroX,
    y: MEDIDAS.raizY,
    unidad: raiz,
    profundidad: 0,
  })

  const grupos = agruparPorCategoria(raiz.hijos ?? [])
  let filaY = MEDIDAS.primeraFilaY

  for (const categoria of CATEGORIAS) {
    const unidades = grupos[categoria]
    if (unidades.length === 0) continue

    let maxSubprocesos = 0

    unidades.forEach((unidad, i) => {
      const x =
        MEDIDAS.centroX
        - ((unidades.length - 1) * MEDIDAS.separacionX) / 2
        + i * MEDIDAS.separacionX
      const id = String(unidad.id)

      nodos.push({
        id,
        name: partirTexto(unidad.nombre ?? 'Sin nombre', 20),
        x,
        y: filaY,
        unidad,
        profundidad: 1,
      })
      enlaces.push({ source: 'root', target: id })

      const subprocesos = mostrarSubprocesos ? unidad.hijos ?? [] : []
      maxSubprocesos = Math.max(maxSubprocesos, subprocesos.length)

      subprocesos.forEach((subproceso, j) => {
        const idSub = String(subproceso.id)

        nodos.push({
          id: idSub,
          // Dos líneas como mucho: el nodo del subproceso es el más bajo y
          // una tercera línea se sale de la caja.
          name: partirTexto(subproceso.nombre ?? 'Sin nombre', 24, 2),
          x,
          y:
            filaY
            + MEDIDAS.altoUnidad
            + MEDIDAS.separacionSubproceso * (j + 1),
          unidad: subproceso,
          profundidad: 2,
        })
        enlaces.push({ source: id, target: idSub })
      })
    })

    filaY +=
      MEDIDAS.altoUnidad
      + MEDIDAS.separacionSubproceso * maxSubprocesos
      + MEDIDAS.margenFila
  }

  return { nodos, enlaces, alto: filaY + 60 }
}
