'use client'

import { Button, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrUndefined, toDateValue } from '@/lib/fecha'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import type { FiltrosFotografias } from '../services/tipos'

interface Props {
  filtros: FiltrosFotografias
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosFotografias>) => void
  onLimpiar: () => void
  hayFiltros: boolean
}

const FOTO = [
  { value: 'sin', label: 'Sin foto' },
  { value: 'con', label: 'Con foto' },
]

/** «Activos» es lo que pide el backend si no se dice nada: la foto es para la credencial. */
const ESTADO = [
  { value: 'activos', label: 'Activos' },
  { value: 'inactivos', label: 'Inactivos' },
  { value: 'todos', label: 'Todos' },
]

export function FotografiasFiltros({ filtros, busqueda, onBusqueda, onCambiar, onLimpiar, hayFiltros }: Props) {
  const compacto = useContainedInput('sm')
  const { data: unidades = [] } = useTodasUnidades()

  return (
    <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={onLimpiar}>Quitar los filtros</Button> : undefined}>
      <TextInput
        label="Buscar"
        placeholder="Nombre o cédula"
        {...compacto}
        value={busqueda}
        onChange={(e) => onBusqueda(e.currentTarget.value)}
      />
      <Select
        label="Unidad"
        placeholder="Todas"
        data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
        searchable
        clearable
        {...compacto}
        value={filtros.unidad_administrativa_id ? String(filtros.unidad_administrativa_id) : null}
        onChange={(v) => onCambiar({ unidad_administrativa_id: v ? Number(v) : undefined })}
        style={{ minWidth: 240 }}
      />
      <Select
        label="Foto"
        placeholder="Con y sin foto"
        data={FOTO}
        clearable
        {...compacto}
        value={filtros.foto ?? null}
        onChange={(v) => onCambiar({ foto: v === 'con' || v === 'sin' ? v : undefined })}
      />
      <Select
        label="Servidores"
        data={ESTADO}
        allowDeselect={false}
        {...compacto}
        value={filtros.estado ?? 'activos'}
        onChange={(v) => onCambiar({ estado: v === 'inactivos' || v === 'todos' ? v : undefined })}
      />
      <DatePickerInput
        label="Foto subida antes del"
        placeholder="Cualquier fecha"
        valueFormat="DD/MM/YYYY"
        clearable
        {...compacto}
        value={toDateValue(filtros.subida_antes_de)}
        onChange={(d) => onCambiar({ subida_antes_de: fromDateValueOrUndefined(d ?? null) })}
        style={{ minWidth: 170 }}
      />
    </Toolbar>
  )
}
