'use client'

import { Chip, Group, Select } from '@mantine/core'
import { Toolbar } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ESTADO_LABELS, FILTROS_ESTADO, TONO_ESTADO } from './permisos.constants'

/** Años que se ofrecen en el filtro, contando el actual. */
const ANIOS_VISIBLES = 5

export interface FiltrosPantallaMisPermisos {
  estado: string
  anio:   string | null
}

export const FILTROS_INICIALES_MIS_PERMISOS: FiltrosPantallaMisPermisos = {
  estado: 'todos',
  anio:   String(new Date().getFullYear()),
}

interface Props {
  filtros:   FiltrosPantallaMisPermisos
  onCambiar: (cambio: Partial<FiltrosPantallaMisPermisos>) => void
}

export function MisPermisosFiltros({ filtros, onCambiar }: Props) {
  const contained = useContainedInput('sm')

  const anioActual = new Date().getFullYear()
  const anios = Array.from({ length: ANIOS_VISIBLES }, (_, i) => String(anioActual - i))

  return (
    <Toolbar>
      <Select
        label="Año"
        placeholder="Todos"
        data={anios}
        clearable
        {...contained}
        value={filtros.anio}
        onChange={(v) => onCambiar({ anio: v })}
        style={{ minWidth: 120 }}
      />

      <Group gap="xs">
        {FILTROS_ESTADO.map((valor) => (
          <Chip
            key={valor}
            // El mismo tono que la etiqueta de estado de la tabla, para que
            // filtro y resultado coincidan.
            color={SEMANTIC_COLOR[TONO_ESTADO[valor] ?? 'neutral']}
            checked={filtros.estado === valor}
            onChange={() => onCambiar({ estado: valor })}
          >
            {valor === 'todos' ? 'Todos' : ESTADO_LABELS[valor]}
          </Chip>
        ))}
      </Group>
    </Toolbar>
  )
}
