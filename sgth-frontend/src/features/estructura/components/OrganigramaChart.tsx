'use client'

import { useCallback } from 'react'
import ReactECharts from 'echarts-for-react'
import { Skeleton, Text, Paper } from '@mantine/core'
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

// Configuración visual por nivel
const NIVEL_CONFIG = [
  {
    // Nivel 0 — GADPE (raíz)
    bgColor: '#0D1F2D',
    borderColor: '#10B981',
    textColor: '#ffffff',
    fontSize: 14,
    fontWeight: 'bold',
    width: 160,
    height: 52,
    shadowColor: 'rgba(16,185,129,0.4)',
    shadowBlur: 12,
  },
  {
    // Nivel 1 — Gestiones principales
    bgColor: '#059669',
    borderColor: '#047857',
    textColor: '#ffffff',
    fontSize: 11,
    fontWeight: 'bold',
    width: 150,
    height: 48,
    shadowColor: 'rgba(5,150,105,0.35)',
    shadowBlur: 8,
  },
]

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
            itemStyle: {
              color: '#047857',
              borderColor: '#10B981',
              borderWidth: 2.5,
              shadowBlur: 18,
              shadowColor: 'rgba(16,185,129,0.5)',
            },
            label: {
              color: '#ffffff',
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
      backgroundColor: '#0D1F2D',
      borderColor: '#10B981',
      borderWidth: 1,
      padding: [10, 14],
      textStyle: {
        color: '#ffffff',
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
            ? `<br/><span style="color:#10B981">▶ Clic para ver detalles</span>`
            : ''
        return `
          <div style="font-weight:600;margin-bottom:4px;">
            ${(u.nombre ?? '').replace(/\n/g, ' ')}
          </div>
          <div style="color:#a3e6cb;font-size:11px;">
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
          color: '#059669',
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
      style={{
        height: 620,
        background:
          'linear-gradient(135deg, #f0fdf4 0%, #ffffff 50%, #f0fdf4 100%)',
        position: 'relative',
      }}
    >
      <div
        style={{
          position: 'absolute',
          bottom: 12,
          right: 12,
          fontSize: 11,
          color: '#6b7280',
          pointerEvents: 'none',
          zIndex: 10,
        }}
      >
        Scroll para zoom · Arrastra para mover · Clic en gestión para detalles
      </div>
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
