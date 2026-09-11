'use client'

import { Button, Group, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconSearch } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDate, toDate } from './permiso.schema'

const TIPO_OPTIONS = [
  { value: 'personal',   label: 'Personal' },
  { value: 'oficial',    label: 'Oficial' },
  { value: 'enfermedad', label: 'Por Enfermedad' },
  { value: 'calamidad',  label: 'Calamidad Doméstica' },
]

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
}

/** Rango de fechas y tipo de permiso del consolidado. */
export function ConsolidadoFiltros({
  filtros, onCambiar, onConsultar, puedeConsultar, consultando,
}: Props) {
  const contained = useContainedInput('sm')

  return (
    <Toolbar
      actions={
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconSearch size={16} />}
          disabled={!puedeConsultar}
          loading={consultando}
          onClick={onConsultar}
        >
          Consultar
        </Button>
      }
    >
      {/* Desde y Hasta son un solo filtro, un rango: van juntos. */}
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Fecha inicio"
          placeholder="Desde"
          valueFormat="YYYY-MM-DD"
          {...contained}
          value={toDate(filtros.fechaInicio)}
          onChange={(d) => onCambiar({ fechaInicio: fromDate(d ?? null) })}
          style={{ minWidth: 150 }}
        />
        <DatePickerInput
          label="Fecha fin"
          placeholder="Hasta"
          valueFormat="YYYY-MM-DD"
          minDate={toDate(filtros.fechaInicio) ?? undefined}
          {...contained}
          value={toDate(filtros.fechaFin)}
          onChange={(d) => onCambiar({ fechaFin: fromDate(d ?? null) })}
          style={{ minWidth: 150 }}
        />
      </Group>

      <Select
        label="Tipo de permiso"
        data={TIPO_OPTIONS}
        {...contained}
        value={filtros.tipo}
        onChange={(v) => onCambiar({ tipo: v ?? 'personal' })}
        style={{ minWidth: 200 }}
      />
    </Toolbar>
  )
}
