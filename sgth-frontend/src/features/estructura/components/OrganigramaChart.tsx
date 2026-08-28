'use client'

import { useCallback } from 'react'
import ReactECharts from 'echarts-for-react'
import { Skeleton, Text, Paper } from '@mantine/core'
import { useEChartsColors, type EChartsColors } from '@/hooks/useEChartsColors'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  unidades: UnidadConRelaciones[]
  isLoading?: boolean
  error?: Error | null
  onNodeClick?: (unidad: UnidadConRelaciones) => void
}

type EChartsGraphNode = {
  id: string
  name: string
  x: number
  y: number
  symbol?: string
  symbolSize?: number | number[]
  itemStyle?: object
  label?: object
  emphasis?: object
  _unidad?: UnidadConRelaciones
  _nivel?: number
}

type EChartsGraphLink = {
  source: string
  target: string
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
 * De ahí salen los tres pesos que sostienen la jerarquía: relleno sólido para
 * la raíz, tinte de acento para las gestiones, lienzo hundido detrás.
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

/** Configuración visual por nivel, construida sobre los tokens del tema. */
function nivelesOrganigrama(c: EChartsColors) {
  const { sobreAcento, trazo } = coloresOrganigrama(c)

  return [
    {
      // Nivel 0 — GADPE (raíz). El peso máximo: relleno de acento sólido.
      bgColor: c.acento,
      borderColor: trazo,
      textColor: sobreAcento,
      fontSize: 14,
      fontWeight: 'bold',
      width: 160,
      height: 52,
      shadowColor: trazo,
      shadowBlur: 12,
    },
    {
      // Nivel 1 — Gestiones principales. Un escalón por debajo: tinte de
      // acento con borde de acento, y la etiqueta en el color de texto.
      bgColor: c.acentoTenue,
      borderColor: trazo,
      textColor: c.texto,
      fontSize: 11,
      fontWeight: 'bold',
      width: 150,
      height: 48,
      shadowColor: c.borde,
      shadowBlur: 8,
    },
  ]
}

function wrapText(text: string, maxLen = 18): string {
  if (text.length <= maxLen) return text
  const words = text.split(' ')
  const lines: string[] = []
  let current = ''
  for (const word of words) {
    if ((current + ' ' + word).trim().length > maxLen) {
      if (current) lines.push(current)
      current = word
    } else {
      current = (current + ' ' + word).trim()
    }
  }
  if (current) lines.push(current)
  return lines.slice(0, 3).join('\n')
}

export function OrganigramaChart({
  unidades,
  isLoading,
  error,
  onNodeClick,
}: Props) {
  const c = useEChartsColors()

  const handleEvents = useCallback(
    () => ({
      click: (params: { data: EChartsGraphNode }) => {
        if (params.data._unidad && onNodeClick) {
          onNodeClick(params.data._unidad)
        }
      },
    }),
    [onNodeClick]
  )

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

  const { sobreAcento, trazo } = coloresOrganigrama(c)
  const NIVEL_CONFIG = nivelesOrganigrama(c)

  // 1. Encontrar el nodo raíz (GADPE)
  const root = unidades[0]
  const nodes: EChartsGraphNode[] = []
  const links: EChartsGraphLink[] = []

  if (root) {
    const rootConfig = NIVEL_CONFIG[0]
    const rootName = wrapText(root.nombre ?? 'GADPE', 20)

    // Nodo Raíz al centro
    nodes.push({
      id: 'root',
      name: rootName,
      x: 600,
      y: 50,
      symbol: 'roundRect',
      symbolSize: [rootConfig.width, rootConfig.height],
      _unidad: root,
      _nivel: 0,
      itemStyle: {
        color: rootConfig.bgColor,
        borderColor: rootConfig.borderColor,
        borderWidth: 1.5,
        borderRadius: 8,
        shadowColor: rootConfig.shadowColor,
        shadowBlur: rootConfig.shadowBlur,
      },
      label: {
        show: true,
        position: 'inside',
        color: rootConfig.textColor,
        fontSize: rootConfig.fontSize,
        fontWeight: rootConfig.fontWeight,
        fontFamily: "'Inter', sans-serif",
        lineHeight: 14,
        overflow: 'break',
        width: rootConfig.width - 24,
      },
    })

    // 2. Agrupar los hijos (Gestiones) por su tipo de unidad
    const hijos = root.hijos ?? []

    // Categorías en orden de arriba hacia abajo:
    // 1. GOBERNANTES ('G')
    // 2. HABILITANTES ASESORES ('HA')
    // 3. AGREGADORES DE VALOR ('AV')
    // 4. HABILITANTES DE APOYO ('HAP')
    const categoriesOrder = ['G', 'HA', 'AV', 'HAP']
    const groups: { [key: string]: UnidadConRelaciones[] } = {
      G: [],
      HA: [],
      AV: [],
      HAP: [],
    }

    hijos.forEach(h => {
      const acro = h.tipo_unidad?.acronimo ?? 'AV'
      if (groups[acro]) {
        groups[acro].push(h)
      } else {
        groups['AV'].push(h)
      }
    })

    // Coordenadas Y para cada categoría
    const yCoords = {
      G: 180,
      HA: 300,
      AV: 420,
      HAP: 540,
    }

    const config = NIVEL_CONFIG[1] // Configuración visual para nivel 1

    categoriesOrder.forEach(category => {
      const categoryNodes = groups[category]
      const N = categoryNodes.length
      if (N === 0) return

      const y = yCoords[category as keyof typeof yCoords]
      const spacing = 190 // Espaciado horizontal constante entre nodos

      categoryNodes.forEach((node, i) => {
        // Calcular X centrado en 600
        const x = 600 - ((N - 1) * spacing) / 2 + i * spacing
        const nodeId = String(node.id)
        const nodeName = wrapText(node.nombre ?? 'Sin nombre', 20)

        nodes.push({
          id: nodeId,
          name: nodeName,
          x: x,
          y: y,
          symbol: 'roundRect',
          symbolSize: [config.width, config.height],
          _unidad: node,
          _nivel: 1,
          itemStyle: {
            color: config.bgColor,
            borderColor: config.borderColor,
            borderWidth: 2,
            borderRadius: 8,
            shadowColor: config.shadowColor,
            shadowBlur: config.shadowBlur,
          },
          label: {
            show: true,
            position: 'inside',
            color: config.textColor,
            fontSize: config.fontSize,
            fontWeight: config.fontWeight,
            fontFamily: "'Inter', sans-serif",
            lineHeight: 14,
            overflow: 'break',
            width: config.width - 24,
          },
          emphasis: {
            // Al pasar el cursor la gestión "se rellena": sube del tinte al
            // acento sólido, el mismo peso que la raíz.
            itemStyle: {
              color: c.acento,
              borderColor: trazo,
              borderWidth: 2.5,
              shadowBlur: 18,
              shadowColor: trazo,
            },
            label: {
              color: sobreAcento,
            },
          },
        })

        // Conexión del nodo raíz a este nodo
        links.push({
          source: 'root',
          target: nodeId,
        })
      })
    })
  }

  const option = {
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
      formatter: (params: { data: EChartsGraphNode }) => {
        const u = params.data._unidad
        if (!u) return params.data.name
        const nivel = params.data._nivel ?? 0
        const nivelLabel = ['Institución', 'Gestión', 'Subproceso'][
          Math.min(nivel, 2)
        ]
        const hijosCount = u.hijos?.length ?? 0
        const extra =
          nivel === 1 && hijosCount > 0
            ? `<br/><span style="color:${trazo}">▶ Clic para ver detalles</span>`
            : ''
        return `
          <div style="font-weight:600;margin-bottom:4px;">
            ${(u.nombre ?? '').replace(/\n/g, ' ')}
          </div>
          <div style="color:${c.textoTenue};font-size:11px;">
            ${nivelLabel}
            ${hijosCount > 0 ? ` · ${hijosCount} subunidades` : ''}
          </div>
          ${extra}
        `
      },
    },
    series: [
      {
        type: 'graph',
        layout: 'none',
        data: nodes,
        links: links,
        roam: true,
        scaleLimit: { min: 0.4, max: 2 },
        lineStyle: {
          color: trazo,
          width: 1.5,
          opacity: 0.6,
          curveness: 0,
        },
        emphasis: {
          focus: 'adjacency',
        },
        animationDuration: 400,
        animationDurationUpdate: 500,
        animationEasing: 'cubicInOut',
        animationEasingUpdate: 'cubicInOut',
      },
    ],
  }

  return (
    <Paper
      radius="md"
      withBorder
      bg="var(--sgth-surface-sunken)"
      style={{
        height: 620,
        position: 'relative',
      }}
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
        Scroll para zoom · Arrastra para mover · Clic en gestión para detalles
      </Text>
      <div style={{ overflowX: 'auto', height: '100%', width: '100%' }}>
        <div style={{ minWidth: 1200, height: '100%' }}>
          <ReactECharts
            option={option}
            style={{ height: '100%', width: '100%' }}
            onEvents={handleEvents()}
            opts={{ renderer: 'canvas' }}
          />
        </div>
      </div>
    </Paper>
  )
}
