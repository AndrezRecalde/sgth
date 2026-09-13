'use client'

import { ActionIcon, Group, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconX } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

/*
| Filtros de la bandeja: unidad, rango de salida y búsqueda por código,
| nombre o cédula. Se aplican a los contadores y a la pestaña abierta.
|
| Las unidades salen del resumen de la propia bandeja —solo las que tienen
| viáticos— y no del catálogo de Estructura, que Financiero no puede leer.
*/

export interface FiltrosBandejaForm {
  unidadId: string | null
  desde: string | null
  hasta: string | null
  busqueda: string
}

export const FILTROS_BANDEJA_INICIALES: FiltrosBandejaForm = {
  unidadId: null,
  desde: null,
  hasta: null,
  busqueda: '',
}

interface Props {
  filtros: FiltrosBandejaForm
  unidades: { id: number; nombre: string }[]
  onCambiar: (cambio: Partial<FiltrosBandejaForm>) => void
}

export function BandejaFiltros({ filtros, unidades, onCambiar }: Props) {
  const contained = useContainedInput('sm')

  return (
    <Toolbar>
      <TextInput
        label="Buscar"
        placeholder="Código, nombre o cédula"
        {...contained}
        value={filtros.busqueda}
        onChange={(e) => onCambiar({ busqueda: e.currentTarget.value })}
        style={{ minWidth: 240 }}
        rightSection={
          filtros.busqueda ? (
            <ActionIcon size="sm" variant="subtle" color="gray" onClick={() => onCambiar({ busqueda: '' })}>
              <IconX size={12} />
            </ActionIcon>
          ) : null
        }
      />

      <Select
        label="Unidad administrativa"
        placeholder="Todas"
        data={unidades.map((u) => ({ value: String(u.id), label: u.nombre }))}
        searchable
        clearable
        {...contained}
        value={filtros.unidadId}
        onChange={(v) => onCambiar({ unidadId: v })}
        style={{ minWidth: 240 }}
      />

      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Salida desde"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDateValue(filtros.desde)}
          onChange={(d) => onCambiar({ desde: fromDateValueOrNull(d) })}
          style={{ minWidth: 150 }}
        />
        <DatePickerInput
          label="Hasta"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          minDate={toDateValue(filtros.desde) ?? undefined}
          {...contained}
          value={toDateValue(filtros.hasta)}
          onChange={(d) => onCambiar({ hasta: fromDateValueOrNull(d) })}
          style={{ minWidth: 150 }}
        />
      </Group>
    </Toolbar>
  )
}
