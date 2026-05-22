'use client'

import { useCallback } from 'react'
import ReactECharts from 'echarts-for-react'
import { Skeleton, Text } from '@mantine/core'
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
  itemStyle?: { color: string; borderColor?: string }
  label?: { color?: string; fontSize?: number; fontWeight?: string }
  children?: EChartsTreeNode[]
  _unidad?: UnidadConRelaciones
}

function buildTreeNode(
  unidad: UnidadConRelaciones,
  nivel: number
): EChartsTreeNode {
  const hijos = unidad.hijos ?? []

  const COLORS = [
    '#0D1F2D', // nivel 1 — navy (GADPE)
    '#059669', // nivel 2 — emerald (gestiones)
    '#10B981', // nivel 3 — emerald claro (subprocesos)
  ]

  const color = COLORS[Math.min(nivel, COLORS.length - 1)]

  return {
    name: unidad.nombre ?? 'Sin nombre',
    value: unidad.id as unknown as number,
    _unidad: unidad,
    itemStyle: {
      color,
      borderColor: nivel === 1 ? '#059669' : undefined,
    },
    label: {
      color: '#ffffff',
      fontSize: nivel === 0 ? 13 : nivel === 1 ? 11 : 10,
      fontWeight: nivel <= 1 ? 'bold' : 'normal',
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
  const handleEvents = useCallback(() => ({
    click: (params: { data: EChartsTreeNode }) => {
      if (params.data._unidad && onNodeClick) {
        onNodeClick(params.data._unidad)
      }
    },
  }), [onNodeClick])

  if (isLoading) {
    return <Skeleton height={500} radius="md" />
  }

  if (error) {
    return <Text c="red" size="sm">Error al cargar el organigrama.</Text>
  }

  if (!unidades.length) {
    return <Text c="dimmed" size="sm">No hay unidades registradas.</Text>
  }

  const treeData = unidades.map(u => buildTreeNode(u, 0))

  const option = {
    tooltip: {
      trigger: 'item',
      triggerOn: 'mousemove',
      formatter: (params: { data: EChartsTreeNode }) =>
        params.data.name,
    },
    series: [
      {
        type: 'tree',
        data: treeData,
        top: '5%',
        left: '10%',
        bottom: '5%',
        right: '20%',
        symbolSize: 10,
        orient: 'LR',
        label: {
          position: 'left',
          verticalAlign: 'middle',
          align: 'right',
          fontSize: 11,
        },
        leaves: {
          label: {
            position: 'right',
            verticalAlign: 'middle',
            align: 'left',
          },
        },
        emphasis: {
          focus: 'descendant',
        },
        expandAndCollapse: true,
        animationDuration: 300,
        animationDurationUpdate: 450,
        initialTreeDepth: 2,
      },
    ],
  }

  return (
    <div style={{ height: 580 }}>
      <ReactECharts
        option={option}
        style={{ height: '100%' }}
        onEvents={handleEvents()}
      />
    </div>
  )
}
