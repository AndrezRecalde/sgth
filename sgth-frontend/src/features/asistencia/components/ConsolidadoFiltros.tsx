'use client'

import { Button, Group, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconSearch } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { TIPO_OPCIONES_CONSOLIDADO } from './permisos.constants'

export interface FiltrosConsolidado {
  fechaInicio: string | null
  fechaFin:    string | null
  tipo:        string
}

export const FILTROS_INICIALES_CONSOLIDADO: FiltrosConsolidado = {
  fechaInicio: null,
  fechaFin:    null,
  tipo:        'personal',
}

interface Props {
  filtros:        FiltrosConsolidado
  onCambiar:      (cambio: Partial<FiltrosConsolidado>) => void
  onConsultar:    () => void
  puedeConsultar: boolean
  consultando:    boolean
  /** Exportar y demás acciones sobre el conjunto, a la derecha de Consultar. */
  acciones?:      React.ReactNode
}

/** Rango de fechas y tipo de permiso del consolidado. */
export function ConsolidadoFiltros({
  filtros, onCambiar, onConsultar, puedeConsultar, consultando, acciones,
}: Props) {
  const contained = useContainedInput('sm')

  return (
    <Toolbar
      actions={
        <>
          <Button
            variant="light"
            leftSection={<IconSearch size={16} />}
            disabled={!puedeConsultar}
            loading={consultando}
            onClick={onConsultar}
          >
            Consultar
          </Button>
          {/* Los botones de exportar colgaban de un `Group` suelto entre la
              barra y la tabla. El catálogo reserva el `actions` del `Toolbar`
              para esto: «acciones ligadas a la selección o al conjunto
              (exportar, limpiar…)». */}
          {acciones}
        </>
      }
    >
      {/* Desde y Hasta son un solo filtro, un rango: van juntos. */}
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Fecha inicio"
          placeholder="Desde"
          valueFormat="YYYY-MM-DD"
          {...contained}
          value={toDateValue(filtros.fechaInicio)}
          onChange={(d) => onCambiar({ fechaInicio: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
        <DatePickerInput
          label="Fecha fin"
          placeholder="Hasta"
          valueFormat="YYYY-MM-DD"
          minDate={toDateValue(filtros.fechaInicio) ?? undefined}
          {...contained}
          value={toDateValue(filtros.fechaFin)}
          onChange={(d) => onCambiar({ fechaFin: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
      </Group>

      <Select
        label="Tipo de permiso"
        data={TIPO_OPCIONES_CONSOLIDADO}
        {...contained}
        value={filtros.tipo}
        onChange={(v) => onCambiar({ tipo: v ?? 'personal' })}
        style={{ minWidth: 200 }}
      />
    </Toolbar>
  )
}
