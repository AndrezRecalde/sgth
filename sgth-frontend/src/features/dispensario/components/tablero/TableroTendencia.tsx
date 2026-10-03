'use client'

import { useMemo } from 'react'
import ReactECharts from 'echarts-for-react'
import { Text } from '@mantine/core'
import { SectionCard } from '@/components/ui'
import { useEChartsColors } from '@/hooks/useEChartsColors'
import type { PanoramaDispensario } from '../../services/panoramaService'

interface Props {
  tendencia?: PanoramaDispensario['tendencia']
}

/** «oct 25». */
function mesCorto(mes: string): string {
  const [anio, m] = mes.split('-').map(Number)
  const nombre = new Date(anio, m - 1, 1).toLocaleDateString('es-EC', { month: 'short' }).replace('.', '')
  return `${nombre} ${String(anio).slice(2)}`
}

/**
 * Atenciones por mes y especialidad, los doce meses que terminan en el mes
 * elegido. Sirve para ver si la demanda sube o baja.
 *
 * Los dos colores son los del reparto por especialidad (ocean y emerald, ya
 * validados para daltonismo). Su separación para tritanopía queda en el
 * mínimo legal, así que odontología lleva además línea discontinua y otro
 * marcador, y cada línea su etiqueta al final. Si las dos terminan en el
 * mismo punto las etiquetas se encimarían sin distinguir nada: entonces
 * basta la leyenda.
 */
export function TableroTendencia({ tendencia }: Props) {
  const c = useEChartsColors()
  const azul  = c.serie[1]
  const verde = c.serie[0]
  const total = (tendencia ?? []).reduce((n, t) => n + t.medicina_general + t.odontologia, 0)
  const ultimo = tendencia?.at(-1)
  const etiquetas = !!ultimo && ultimo.medicina_general !== ultimo.odontologia

  const option = useMemo(() => ({
    backgroundColor: 'transparent',
    textStyle: { color: c.texto, fontFamily: 'inherit' },
    aria: { enabled: true },
    legend: { top: 0, left: 0, icon: 'roundRect', itemWidth: 14, itemHeight: 4, textStyle: { color: c.textoTenue } },
    grid: { top: 36, left: 8, right: etiquetas ? 96 : 16, bottom: 8, containLabel: true },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'line', lineStyle: { color: c.borde } },
      backgroundColor: c.superficie,
      borderColor: c.borde,
      textStyle: { color: c.texto },
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: (tendencia ?? []).map(t => mesCorto(t.mes)),
      axisLine: { lineStyle: { color: c.borde } },
      axisTick: { show: false },
      axisLabel: { color: c.textoTenue },
    },
    yAxis: {
      type: 'value',
      minInterval: 1,
      splitLine: { lineStyle: { color: c.borde, opacity: 0.6 } },
      axisLabel: { color: c.textoTenue },
    },
    series: [
      {
        name: 'Medicina general',
        type: 'line',
        data: (tendencia ?? []).map(t => t.medicina_general),
        lineStyle: { width: 2, color: azul },
        itemStyle: { color: azul, borderColor: c.superficie, borderWidth: 2 },
        symbol: 'circle',
        symbolSize: 8,
        endLabel: { show: etiquetas, formatter: 'Medicina general', color: c.texto },
        labelLayout: { moveOverlap: 'shiftY' },
      },
      {
        name: 'Odontología',
        type: 'line',
        data: (tendencia ?? []).map(t => t.odontologia),
        lineStyle: { width: 2, color: verde, type: 'dashed' },
        itemStyle: { color: verde, borderColor: c.superficie, borderWidth: 2 },
        symbol: 'diamond',
        symbolSize: 9,
        endLabel: { show: etiquetas, formatter: 'Odontología', color: c.texto },
        labelLayout: { moveOverlap: 'shiftY' },
      },
    ],
  }), [tendencia, c, azul, verde, etiquetas])

  return (
    <SectionCard title="Tendencia de atenciones" description="Doce meses hasta el mes elegido, por especialidad.">
      {total === 0 ? (
        <Text size="sm" c="dimmed">Sin atenciones en los últimos doce meses.</Text>
      ) : (
        <ReactECharts option={option} style={{ height: 280 }} notMerge />
      )}
    </SectionCard>
  )
}
