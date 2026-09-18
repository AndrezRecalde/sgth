'use client'

import { ActionIcon, Select, TextInput } from '@mantine/core'
import { IconX } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ESTADO_LABELS, ZONA_OPTIONS } from '../constants/viatico.constants'

/*
| Filtros de «Mis viáticos», con la misma barra que la bandeja de Financiero.
|
| Antes eran un buscador sin etiqueta, con un botón «Buscar» aparte, y una fila
| de chips pintados con el color de cada estado —el tema es quien colorea un
| Chip— que dejaba fuera la mitad de los estados.
*/

export interface FiltrosViaticoForm {
  busqueda: string
  estado: string | null
  zona: string | null
}

export const FILTROS_VIATICO_INICIALES: FiltrosViaticoForm = {
  busqueda: '',
  estado: null,
  zona: null,
}

const ESTADO_OPCIONES = Object.entries(ESTADO_LABELS).map(([value, label]) => ({ value, label }))

interface Props {
  filtros: FiltrosViaticoForm
  onCambiar: (cambio: Partial<FiltrosViaticoForm>) => void
}

export function ViaticoFiltros({ filtros, onCambiar }: Props) {
  const contained = useContainedInput('sm')

  return (
    <Toolbar>
      <TextInput
        label="Buscar"
        placeholder="Código del viático"
        {...contained}
        value={filtros.busqueda}
        onChange={(e) => onCambiar({ busqueda: e.currentTarget.value })}
        style={{ minWidth: 220 }}
        rightSection={
          filtros.busqueda ? (
            <ActionIcon
              size="sm"
              variant="subtle"
              aria-label="Borrar la búsqueda"
              onClick={() => onCambiar({ busqueda: '' })}
            >
              <IconX size={12} />
            </ActionIcon>
          ) : null
        }
      />

      <Select
        label="Estado"
        placeholder="Todos"
        data={ESTADO_OPCIONES}
        clearable
        {...contained}
        value={filtros.estado}
        onChange={(v) => onCambiar({ estado: v })}
        style={{ minWidth: 200 }}
      />

      <Select
        label="Zona"
        placeholder="Todas"
        data={ZONA_OPTIONS}
        clearable
        {...contained}
        value={filtros.zona}
        onChange={(v) => onCambiar({ zona: v })}
        style={{ minWidth: 200 }}
      />
    </Toolbar>
  )
}
