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

type EChartsTreeNode = {
  name: string
  value?: number
  itemStyle?: object
  label?: object
  emphasis?: object
  children?: EChartsTreeNode[]
  _unidad?: UnidadConRelaciones
  _nivel?: number
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
  {
    // Nivel 2 — Subprocesos
    bgColor: '#ffffff',
    borderColor: '#059669',
    textColor: '#0D1F2D',
    fontSize: 10,
    fontWeight: 'normal',
    width: 138,
    height: 40,
    shadowColor: 'rgba(0,0,0,0.08)',
    shadowBlur: 4,
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

function buildTreeNode(
  unidad: UnidadConRelaciones,
  nivel: number
): EChartsTreeNode {
  const hijos  = unidad.hijos ?? []
  const config = NIVEL_CONFIG[Math.min(nivel, NIVEL_CONFIG.length - 1)]
  const esNivel2 = nivel === 1
  const nombre = wrapText(unidad.nombre ?? 'Sin nombre', 20)

  return {
    name: nombre,
    value: unidad.id as unknown as number,
    _unidad: unidad,
    _nivel: nivel,
    itemStyle: {
      color: config.bgColor,
      borderColor: config.borderColor,
      borderWidth: esNivel2 ? 2 : 1.5,
      borderRadius: 8,
      shadowColor: config.shadowColor,
      shadowBlur: config.shadowBlur,
    },
    label: {
      color: config.textColor,
      fontSize: config.fontSize,
      fontWeight: config.fontWeight,
      fontFamily: "'Inter', sans-serif",
      lineHeight: 16,
      padding: [8, 10],
      overflow: 'break',
      width: config.width,
    },
    emphasis: {
      itemStyle: {
        color: esNivel2 ? '#047857' : config.bgColor,
        borderColor: '#10B981',
        borderWidth: 2.5,
        shadowBlur: 18,
        shadowColor: 'rgba(16,185,129,0.5)',
      },
      label: {
        color: esNivel2 ? '#ffffff' : config.textColor,
      },
    },
    children: hijos.map(h => buildTreeNode(h, nivel + 1)),
  }
}

export function OrganigramaChart({
  unidades,
  isLoading,
  error,
  onNodeClick,
}: Props) {
  const handleEvents = useCallback(
    () => ({
      click: (params: { data: EChartsTreeNode }) => {
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

  const treeData = unidades.map(u => buildTreeNode(u, 0))

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
      formatter: (params: { data: EChartsTreeNode }) => {
        const u = params.data._unidad
        if (!u) return params.data.name
        const nivel = params.data._nivel ?? 0
        const nivelLabel = ['Institución', 'Gestión', 'Subproceso'][
          Math.min(nivel, 2)
        ]
        const hijos = u.hijos?.length ?? 0
        const extra =
          nivel === 1 && hijos > 0
            ? `<br/><span style="color:#10B981">▶ Clic para ver detalles</span>`
            : ''
        return `
          <div style="font-weight:600;margin-bottom:4px;">
            ${(u.nombre ?? '').replace(/\n/g, ' ')}
          </div>
          <div style="color:#a3e6cb;font-size:11px;">
            ${nivelLabel}
            ${hijos > 0 ? ` · ${hijos} subunidades` : ''}
          </div>
          ${extra}
        `
      },
    },
    series: [
      {
        type: 'tree',
        data: treeData,
        top: '4%',
        left: '2%',
        bottom: '4%',
        right: '2%',
        orient: 'TB',
        symbol: 'roundRect',
        symbolSize: [150, 48],
        edgeShape: 'curve',
        edgeForkPosition: '50%',
        roam: true,
        scaleLimit: { min: 0.4, max: 2 },
        initialTreeDepth: 2,
        layout: 'orthogonal',
        lineStyle: {
          color: '#059669',
          width: 1.5,
          opacity: 0.6,
          curveness: 0.4,
        },
        label: {
          show: true,
          position: 'inside',
          verticalAlign: 'middle',
          align: 'center',
        },
        leaves: {
          label: {
            show: true,
            position: 'inside',
            verticalAlign: 'middle',
            align: 'center',
          },
        },
        emphasis: {
          focus: 'relative',
        },
        expandAndCollapse: true,
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
        overflow: 'hidden',
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
        }}
      >
        Scroll para zoom · Arrastra para mover · Clic en gestión para detalles
      </div>
      <ReactECharts
        option={option}
        style={{ height: '100%', width: '100%' }}
        onEvents={handleEvents()}
        opts={{ renderer: 'canvas' }}
      />
    </Paper>
  )
}
