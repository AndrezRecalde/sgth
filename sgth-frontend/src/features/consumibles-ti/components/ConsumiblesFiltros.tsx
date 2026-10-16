'use client'

import { Button, Select, Switch, TextInput } from '@mantine/core'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { TIPOS_CONSUMIBLE } from '../constants/consumibles'
import { useConsumibles } from '../hooks/useConsumibles'
import type { FiltrosConsumibles } from '../services/consumiblesService'

interface Props {
  filtros: FiltrosConsumibles
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosConsumibles>) => void
  onLimpiar: () => void
  hayFiltros: boolean
}

const GUARDA = [
  { value: 'tic', label: 'Lo guarda TIC' },
  { value: 'bodega', label: 'Lo despacha Bodega' },
]

const SEPARADOR = '|'

/**
 * Los filtros del catálogo: texto, tipo, quién lo guarda, el modelo de equipo
 * al que sirve y lo que bajó del mínimo.
 */
export function ConsumiblesFiltros({ filtros, busqueda, onBusqueda, onCambiar, onLimpiar, hayFiltros }: Props) {
  const compacto = useContainedInput('sm')
  // Los modelos que aparecen en el catálogo, sin repetir, para elegir uno.
  const { data: todos = [] } = useConsumibles({ incluir_inactivos: true })
  const modelos = [...new Map(todos.flatMap((c) => c.modelos).map((m) => [
    `${m.marca_id}${SEPARADOR}${m.modelo.replace(/\s+/g, '').toLowerCase()}`,
    { value: `${m.marca_id}${SEPARADOR}${m.modelo}`, label: [m.marca, m.modelo].filter(Boolean).join(' ') },
  ])).values()].sort((a, b) => a.label.localeCompare(b.label))

  const guarda = filtros.guarda_tic === undefined ? null : filtros.guarda_tic ? 'tic' : 'bodega'
  const modelo = filtros.marca_id && filtros.modelo ? `${filtros.marca_id}${SEPARADOR}${filtros.modelo}` : null

  return (
    <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={onLimpiar}>Quitar los filtros</Button> : undefined}>
      <TextInput label="Buscar" placeholder="Descripción o código de bodega" {...compacto}
        value={busqueda} onChange={(e) => onBusqueda(e.currentTarget.value)} />
      <Select label="Tipo" placeholder="Todos" data={TIPOS_CONSUMIBLE} clearable {...compacto}
        value={filtros.tipo ?? null}
        onChange={(v) => onCambiar({ tipo: TIPOS_CONSUMIBLE.find((t) => t.value === v)?.value })} />
      <Select label="Modelo de equipo" placeholder="Todos" data={modelos} searchable clearable {...compacto}
        value={modelo}
        onChange={(v) => {
          const [marca, ...resto] = (v ?? '').split(SEPARADOR)
          onCambiar(v ? { marca_id: Number(marca), modelo: resto.join(SEPARADOR) } : { marca_id: undefined, modelo: undefined })
        }} />
      <Select label="Quién lo guarda" placeholder="Todos" data={GUARDA} clearable {...compacto}
        value={guarda} onChange={(v) => onCambiar({ guarda_tic: v === null ? undefined : v === 'tic' })} />
      <Switch label="Bajo el mínimo" checked={!!filtros.bajo_minimo} mb={8}
        onChange={(e) => onCambiar({ bajo_minimo: e.currentTarget.checked || undefined })} />
      <Switch label="Ver los inactivos" checked={!!filtros.incluir_inactivos} mb={8}
        onChange={(e) => onCambiar({ incluir_inactivos: e.currentTarget.checked || undefined })} />
    </Toolbar>
  )
}
