'use client'

import { Button, Select, TextInput } from '@mantine/core'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { OPCIONES_SITUACION } from '../constants/credenciales'
import type { FiltrosCredenciales } from '../services/tiposCredencial'

interface Props {
  filtros: FiltrosCredenciales
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosCredenciales>) => void
  onLimpiar: () => void
  hayFiltros: boolean
}

const esSituacion = (v: string | null): v is NonNullable<FiltrosCredenciales['situacion']> =>
  OPCIONES_SITUACION.some((o) => o.value === v)

export function CredencialesFiltros({ filtros, busqueda, onBusqueda, onCambiar, onLimpiar, hayFiltros }: Props) {
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
        label="Situación"
        placeholder="Todas"
        data={[...OPCIONES_SITUACION]}
        clearable
        {...compacto}
        value={filtros.situacion ?? null}
        onChange={(v) => onCambiar({ situacion: esSituacion(v) ? v : undefined })}
        style={{ minWidth: 250 }}
      />
    </Toolbar>
  )
}
