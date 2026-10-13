'use client'

import { Button, Select, TextInput } from '@mantine/core'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import type { FiltrosBienes } from '../services/tipos'

interface Props {
  filtros: FiltrosBienes
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosBienes>) => void
  onLimpiar: () => void
  hayFiltros: boolean
}

/** «Sin custodio» y «De préstamo» son dos preguntas distintas sobre la custodia. */
const CUSTODIA = [
  { value: 'sin_custodio', label: 'Sin custodio' },
  { value: 'prestable', label: 'De préstamo' },
]

const aNumero = (v: string | null) => (v ? Number(v) : undefined)

export function BienesFiltros({ filtros, busqueda, onBusqueda, onCambiar, onLimpiar, hayFiltros }: Props) {
  const compacto = useContainedInput('sm')
  const { data: catalogos } = useCatalogosFormulario()
  const { data: unidades = [] } = useTodasUnidades()

  const custodia = filtros.sin_custodio ? 'sin_custodio' : filtros.prestable ? 'prestable' : null

  return (
    <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={onLimpiar}>Quitar los filtros</Button> : undefined}>
      <TextInput
        label="Buscar"
        placeholder="Código, serie o custodio"
        {...compacto}
        value={busqueda}
        onChange={(e) => onBusqueda(e.currentTarget.value)}
      />
      <Select
        label="Tipo"
        placeholder="Todos"
        data={(catalogos?.tipos ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))}
        clearable
        {...compacto}
        value={filtros.tipo_bien_id ? String(filtros.tipo_bien_id) : null}
        onChange={(v) => onCambiar({ tipo_bien_id: aNumero(v) })}
      />
      <Select
        label="Estado"
        placeholder="Todos"
        data={(catalogos?.estados ?? []).map((e) => ({ value: e.valor, label: e.etiqueta }))}
        clearable
        {...compacto}
        value={filtros.estado_operativo ?? null}
        onChange={(v) => onCambiar({ estado_operativo: catalogos?.estados.find((e) => e.valor === v)?.valor })}
      />
      <Select
        label="Unidad"
        placeholder="Todas"
        data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
        searchable
        clearable
        {...compacto}
        value={filtros.unidad_administrativa_id ? String(filtros.unidad_administrativa_id) : null}
        onChange={(v) => onCambiar({ unidad_administrativa_id: aNumero(v) })}
      />
      <Select
        label="Ubicación"
        placeholder="Todas"
        data={(catalogos?.ubicaciones ?? []).map((u) => ({ value: String(u.id), label: u.piso ? `${u.sede} · ${u.piso}` : u.sede }))}
        clearable
        {...compacto}
        value={filtros.ubicacion_id ? String(filtros.ubicacion_id) : null}
        onChange={(v) => onCambiar({ ubicacion_id: aNumero(v) })}
      />
      <Select
        label="Custodia"
        placeholder="Todos"
        data={CUSTODIA}
        clearable
        {...compacto}
        value={custodia}
        onChange={(v) => onCambiar({ sin_custodio: v === 'sin_custodio' || undefined, prestable: v === 'prestable' || undefined })}
      />
    </Toolbar>
  )
}
