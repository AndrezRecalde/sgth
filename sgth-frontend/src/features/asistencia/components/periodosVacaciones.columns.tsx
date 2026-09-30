'use client'

import { Group, Progress, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconRefreshAlert } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'
import type { DataTableColumn } from 'mantine-datatable'
import type { PeriodoVacacion } from '@/types/api'
import {
  ESTADO_PERIODO_LABELS,
  TONO_PERIODO,
  UMBRAL_ALERTA_TOPE,
} from './periodos.constants'

interface ColumnActions {
  /** Tope de acumulación del régimen del servidor, o `null` si no tiene. */
  tope: number | null
  /** `gestionar-vacaciones`: sin él, recalcular no se ofrece. */
  puedeRecalcular: boolean
  onRecalcular: (periodo: PeriodoVacacion) => void
}

function dias(valor: number | string | null | undefined, decimales = 1): string {
  if (valor === null || valor === undefined) return '—'
  return Number(valor).toFixed(decimales)
}

export function getPeriodosVacacionesColumns(
  { tope, puedeRecalcular, onRecalcular }: ColumnActions
): DataTableColumn<PeriodoVacacion>[] {
  return [
    {
      accessor: 'anio',
      title: 'Año',
      width: 70,
      render: ({ anio }) => (
        <Text size="sm" fw={700} ff="monospace">{anio}</Text>
      ),
    },
    {
      accessor: 'regimen',
      title: 'Régimen',
      width: 130,
      // Una categoría va neutra: LOSEP no es mejor ni peor que el Código del
      // Trabajo.
      render: ({ regimen }) => (
        <StatusBadge>{REGIMEN_LABELS[regimen] ?? regimen}</StatusBadge>
      ),
    },
    {
      accessor: 'anios_antiguedad',
      title: 'Antigüedad',
      width: 90,
      render: ({ anios_antiguedad }) => (
        <Text size="sm" ta="center">{anios_antiguedad} años</Text>
      ),
    },
    {
      accessor: 'dias_generados',
      title: 'Generados',
      width: 90,
      render: ({ dias_generados }) => (
        <Text size="sm" ta="center">{dias(dias_generados)}</Text>
      ),
    },
    {
      accessor: 'dias_vacaciones_aprobadas',
      title: 'Por vacaciones',
      width: 110,
      render: ({ dias_vacaciones_aprobadas }) => (
        <Text size="sm" ta="center">
          {dias(dias_vacaciones_aprobadas, 2)}
        </Text>
      ),
    },
    {
      accessor: 'dias_permisos_personales',
      title: 'Por permisos',
      width: 110,
      /*
      | Días que los permisos personales tomaron de ESTE período.
      |
      | Se muestran con dos decimales y sin traducir a horas: el descuento se
      | guarda en centésimas de día (`permiso_descuentos.dias` es
      | `decimal(8,2)`), así que un permiso de tres horas deja 0,38 y no 0,375.
      | La línea «3.04h descontadas» que se calculaba multiplicando por ocho
      | inventaba una precisión que el dato no tiene; las horas exactas están en
      | el propio permiso.
      */
      render: ({ dias_permisos_personales }) => {
        const valor = Number(dias_permisos_personales ?? 0)

        return valor === 0 ? (
          <Text size="xs" c="dimmed" ta="center">—</Text>
        ) : (
          <Text size="sm" ta="center">{valor.toFixed(2)}</Text>
        )
      },
    },
    {
      accessor: 'dias_saldo',
      title: 'Saldo',
      width: 150,
      render: ({ dias_saldo, dias_generados, dias_utilizados, dias_vencidos }) => {
        const saldo    = Number(dias_saldo)
        const generado = Number(dias_generados)
        // Lo gozado, sin lo vencido por el tope: eso no se usó, se perdió.
        const usado    = Number(dias_utilizados)
        const vencidos = Number(dias_vencidos ?? 0)

        const pct = generado > 0
          ? Math.min(100, Math.round((usado / generado) * 100))
          : 0
        const tono: SemanticTone = pct >= 80 ? 'danger' : pct >= 50 ? 'warning' : 'success'

        return (
          <Stack gap={4}>
            <Group gap={4} justify="space-between">
              <Text size="xs" fw={600}>{saldo.toFixed(1)} días</Text>
              <Text size="xs" c="dimmed">{pct}% usado</Text>
            </Group>
            {/* La barra era dos `div` con nueve propiedades en línea. */}
            <Progress value={pct} size="sm" radius="sm" color={SEMANTIC_COLOR[tono]} />
            {vencidos > 0 && (
              <Text size="xs" c="red">
                {vencidos.toFixed(1)} vencidos por el tope
              </Text>
            )}
          </Stack>
        )
      },
    },
    {
      accessor: 'saldo_acumulado',
      title: 'Acumulado',
      width: 100,
      render: ({ saldo_acumulado }) => {
        const acumulado = Number(saldo_acumulado)
        const enAlerta  = tope !== null && acumulado >= tope * UMBRAL_ALERTA_TOPE

        return (
          <Group gap={4} wrap="nowrap">
            <Text size="sm" fw={600} c={enAlerta ? 'amber' : 'inherit'}>
              {dias(saldo_acumulado)}
            </Text>
            {enAlerta && (
              <IconAlertTriangle size={12} color="var(--mantine-color-amber-6)" />
            )}
          </Group>
        )
      },
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 90,
      render: ({ estado }) => (
        <StatusBadge tone={TONO_PERIODO[estado]}>
          {ESTADO_PERIODO_LABELS[estado]}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (periodo) => (
        <TableActions
          actions={[
            {
              label: 'Recalcular este período',
              icon: <IconRefreshAlert size={14} />,
              // Solo en los cerrados: uno abierto se recalcula con la
              // generación normal, que no necesita advertencia ni bitácora.
              hidden: !puedeRecalcular || periodo.estado === 'abierto',
              onClick: () => onRecalcular(periodo),
            },
          ]}
        />
      ),
    },
  ]
}
