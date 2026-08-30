'use client'

import { useCallback, useMemo } from 'react'
import ReactECharts from 'echarts-for-react'
import { Skeleton, Text, Paper } from '@mantine/core'
import { useEChartsColors, type EChartsColors } from '@/hooks/useEChartsColors'
import { etiquetaNivel } from '../utils/jerarquia'
import {
  construirGrafo, MEDIDAS, type NodoOrganigrama,
} from '../utils/organigramaLayout'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  unidades: UnidadConRelaciones[]
  isLoading?: boolean
  error?: Error | null
  onNodeClick?: (unidad: UnidadConRelaciones) => void
  /** Los subprocesos alargan mucho el lienzo; se pueden plegar. */
  mostrarSubprocesos?: boolean
}

type DatoNodo = {
  id: string
  name: string
  x: number
  y: number
  symbol: string
  symbolSize: [number, number]
  itemStyle: object
  label: object
  emphasis?: object
  _unidad: UnidadConRelaciones
  _profundidad: number
}

/**
 * Colores derivados de los tokens para este organigrama.
 *
 * Dos hechos del sistema mandan sobre el diseño:
 *
 * - `acento` resuelve a un verde oscuro en los DOS esquemas, así que un
 *   relleno sólido de acento siempre pide una etiqueta clara encima.
 * - `acentoTenue` es opaco y se invierte solo (menta pálido en claro, verde
 *   profundo en oscuro), así que junto a `texto` da contraste alto en ambos.
 *
 * De ahí salen los pesos que sostienen la jerarquía: relleno sólido para la
 * raíz, tinte de acento para las unidades, y el lienzo hundido con borde para
 * los subprocesos, que son el nivel de menor peso.
 */
function coloresOrganigrama(c: EChartsColors) {
  const oscuro = c.esquema === 'dark'

  return {
    /** Etiqueta sobre el relleno de acento. */
    sobreAcento: oscuro ? c.texto : c.superficie,
    /**
     * Trazo de bordes y conexiones. En oscuro el acento resuelto se apaga
     * contra el lienzo y las líneas de 1.5px desaparecen; el emerald de la
     * paleta mantiene el dibujo legible.
     */
    trazo: oscuro ? c.serie[0] : c.acento,
  }
}

/** Configuración visual por profundidad, construida sobre los tokens. */
function nivelesOrganigrama(c: EChartsColors) {
  const { sobreAcento, trazo } = coloresOrganigrama(c)

  return [
    {
      // Institución (raíz). El peso máximo: relleno de acento sólido.
      bgColor: c.acento,
      borderColor: trazo,
      borderWidth: 1.5,
      textColor: sobreAcento,
      fontSize: 14,
      fontWeight: 'bold',
      size: MEDIDAS.tamanoRaiz,
      shadowColor: trazo,
      shadowBlur: 12,
      lineHeight: 14,
    },
    {
      // Unidades administrativas. Un escalón por debajo: tinte de acento con
      // borde de acento, y la etiqueta en el color de texto.
      bgColor: c.acentoTenue,
      borderColor: trazo,
      borderWidth: 2,
      textColor: c.texto,
      fontSize: 11,
      fontWeight: 'bold',
      size: MEDIDAS.tamanoUnidad,
      shadowColor: c.borde,
      shadowBlur: 8,
      lineHeight: 14,
    },
    {
      // Subprocesos. El menor peso: sin relleno de acento y con el borde
      // atenuado, para que la fila de unidades siga leyéndose por encima.
      bgColor: c.superficie,
      borderColor: c.borde,
      borderWidth: 1,
      textColor: c.textoTenue,
      fontSize: 9,
      fontWeight: 'normal',
      size: MEDIDAS.tamanoSubproceso,
      shadowColor: c.borde,
      shadowBlur: 0,
      lineHeight: 11,
    },
  ]
}

export function OrganigramaChart({
  unidades,
  isLoading,
  error,
  onNodeClick,
  mostrarSubprocesos = true,
}: Props) {
  const c = useEChartsColors()

  const handleEvents = useCallback(
    () => ({
      click: (params: { data: DatoNodo }) => {
        if (params.data?._unidad && onNodeClick) {
          onNodeClick(params.data._unidad)
        }
      },
    }),
    [onNodeClick]
  )

  const grafo = useMemo(
    () => construirGrafo(unidades[0], { mostrarSubprocesos }),
    [unidades, mostrarSubprocesos]
  )

  const option = useMemo(() => {
    const { sobreAcento, trazo } = coloresOrganigrama(c)
    const config = nivelesOrganigrama(c)

    const aDatoNodo = (nodo: NodoOrganigrama): DatoNodo => {
      const cfg = config[nodo.profundidad]

      return {
        id: nodo.id,
        name: nodo.name,
        x: nodo.x,
        y: nodo.y,
        symbol: 'roundRect',
        symbolSize: cfg.size,
        _unidad: nodo.unidad,
        _profundidad: nodo.profundidad,
        itemStyle: {
          color: cfg.bgColor,
          borderColor: cfg.borderColor,
          borderWidth: cfg.borderWidth,
          borderRadius: 8,
          shadowColor: cfg.shadowColor,
          shadowBlur: cfg.shadowBlur,
        },
        label: {
          show: true,
          position: 'inside',
          color: cfg.textColor,
          fontSize: cfg.fontSize,
          fontWeight: cfg.fontWeight,
          fontFamily: "'Inter', sans-serif",
          lineHeight: cfg.lineHeight,
          overflow: 'break',
          width: cfg.size[0] - 20,
        },
        emphasis: nodo.profundidad === 0 ? undefined : {
          // Al pasar el cursor el nodo "se rellena": sube al acento sólido,
          // el mismo peso que la raíz.
          itemStyle: {
            color: c.acento,
            borderColor: trazo,
            borderWidth: 2.5,
            shadowBlur: 18,
            shadowColor: trazo,
          },
          label: { color: sobreAcento },
        },
      }
    }

    return {
      backgroundColor: 'transparent',
      tooltip: {
        trigger: 'item',
        triggerOn: 'mousemove',
        backgroundColor: c.superficie,
        borderColor: trazo,
        borderWidth: 1,
        padding: [10, 14],
        textStyle: {
          color: c.texto,
          fontSize: 12,
          fontFamily: "'Inter', sans-serif",
        },
        formatter: (params: { data: DatoNodo }) => {
          const u = params.data?._unidad
          if (!u) return params.data?.name ?? ''

          const hijos = u.hijos?.length ?? 0
          const extra = hijos > 0
            ? `<br/><span style="color:${trazo}">▶ Clic para ver detalles</span>`
            : ''

          return `
            <div style="font-weight:600;margin-bottom:4px;">
              ${(u.nombre ?? '').replace(/\n/g, ' ')}
            </div>
            <div style="color:${c.textoTenue};font-size:11px;">
              ${etiquetaNivel(u.nivel ?? params.data._profundidad + 1)}
              ${hijos > 0 ? ` · ${hijos} ${hijos === 1 ? 'subproceso' : 'subprocesos'}` : ''}
            </div>
            ${extra}
          `
        },
      },
      series: [
        {
          type: 'graph',
          layout: 'none',
          data: grafo.nodos.map(aDatoNodo),
          links: grafo.enlaces,
          roam: true,
          scaleLimit: { min: 0.3, max: 2 },
          lineStyle: {
            color: trazo,
            width: 1.5,
            opacity: 0.6,
            curveness: 0,
          },
          emphasis: { focus: 'adjacency' },
          animationDuration: 400,
          animationDurationUpdate: 500,
          animationEasing: 'cubicInOut',
          animationEasingUpdate: 'cubicInOut',
        },
      ],
    }
  }, [c, grafo])

  if (isLoading) {
    return <Skeleton height={600} radius="md" />
  }

  if (error) {
    return (
      <Text c="red" size="sm">
        Error al cargar el organigrama.
      </Text>
    )
  }

  if (!unidades.length) {
    return (
      <Text c="dimmed" size="sm">
        No hay unidades registradas.
      </Text>
    )
  }

  // El lienzo crece con la estructura: con los subprocesos desplegados el alto
  // fijo de 620px recortaba las últimas categorías sin avisar.
  const alto = Math.min(Math.max(grafo.alto, 620), 1400)

  return (
    <Paper
      radius="md"
      withBorder
      bg="var(--sgth-surface-sunken)"
      style={{ height: alto, position: 'relative' }}
    >
      <Text
        size="xs"
        c="dimmed"
        style={{
          position: 'absolute',
          bottom: 12,
          right: 12,
          pointerEvents: 'none',
          zIndex: 10,
        }}
      >
        Scroll para zoom · Arrastra para mover · Clic en una unidad para detalles
      </Text>
      <div style={{ overflowX: 'auto', height: '100%', width: '100%' }}>
        <div style={{ minWidth: 1200, height: '100%' }}>
          <ReactECharts
            option={option}
            style={{ height: '100%', width: '100%' }}
            onEvents={handleEvents()}
            opts={{ renderer: 'canvas' }}
            notMerge
          />
        </div>
      </div>
    </Paper>
  )
}
