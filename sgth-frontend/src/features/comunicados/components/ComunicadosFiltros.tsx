'use client'

import { Button, Select, TextInput } from '@mantine/core'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { OPCIONES_TIPO, OPCIONES_VIGENCIA } from '../constants/comunicados'
import type { FiltrosComunicados, TipoComunicado, VigenciaComunicado } from '../services/tipos'

interface Props {
  filtros: FiltrosComunicados
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosComunicados>) => void
  onLimpiar: () => void
  hayFiltros: boolean
}

const esVigencia = (v: string | null): v is VigenciaComunicado => OPCIONES_VIGENCIA.some((o) => o.value === v)
const esTipo = (v: string | null): v is TipoComunicado => OPCIONES_TIPO.some((o) => o.value === v)

export function ComunicadosFiltros({ filtros, busqueda, onBusqueda, onCambiar, onLimpiar, hayFiltros }: Props) {
  const compacto = useContainedInput('sm')

  return (
    <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={onLimpiar}>Quitar los filtros</Button> : undefined}>
      <TextInput
        label="Buscar"
        placeholder="Título o n.º de documento"
        {...compacto}
        value={busqueda}
        onChange={(e) => onBusqueda(e.currentTarget.value)}
      />
      <Select
        label="Estado"
        placeholder="Todos"
        data={OPCIONES_VIGENCIA}
        clearable
        {...compacto}
        value={filtros.vigencia ?? null}
        onChange={(v) => onCambiar({ vigencia: esVigencia(v) ? v : undefined })}
        style={{ minWidth: 180 }}
      />
      <Select
        label="Tipo"
        placeholder="Todos"
        data={OPCIONES_TIPO.map(({ value, label }) => ({ value, label }))}
        clearable
        {...compacto}
        value={filtros.tipo ?? null}
        onChange={(v) => onCambiar({ tipo: esTipo(v) ? v : undefined })}
        style={{ minWidth: 180 }}
      />
    </Toolbar>
  )
}
