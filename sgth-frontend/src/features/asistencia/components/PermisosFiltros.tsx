'use client'

import { ActionIcon, Button, Chip, Group, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconCubePlus, IconX } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { fromDate, toDate } from './permiso.schema'
import { ESTADO_LABELS, FILTROS_ESTADO, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import type { UnidadConRelaciones } from '@/types/api'

/*
| Los filtros del listado de permisos.
|
| La API acepta folio, estado, tipo, servidor, unidad y rango de fechas desde el
| primer día; la pantalla solo exponía folio y estado. Buscar los permisos de
| una dirección en un mes concreto —que es lo que pide Talento Humano para
| cerrar la nómina— no se podía hacer.
|
| Va en su propio archivo porque el tab ya estaba en el límite de tamaño.
*/

export interface FiltrosPermiso {
  estado: string
  tipo: string | null
  unidadId: string | null
  fechaDesde: string | null
  fechaHasta: string | null
  folio: string
}

export const FILTROS_INICIALES: FiltrosPermiso = {
  estado: 'pendiente',
  tipo: null,
  unidadId: null,
  fechaDesde: null,
  fechaHasta: null,
  folio: '',
}

interface Props {
  filtros: FiltrosPermiso
  onCambiar: (filtros: Partial<FiltrosPermiso>) => void
  onNuevo: () => void
}

export function PermisosFiltros({ filtros, onCambiar, onNuevo }: Props) {
  const contained = useContainedInput('sm')
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })

  const unidadOptions = ((unidadesRaw ?? []) as UnidadConRelaciones[]).map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const tipoOptions = Object.entries(TIPO_LABELS).map(([value, label]) => ({
    value,
    label,
  }))

  return (
    <Toolbar
      actions={
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconCubePlus size={16} />}
          onClick={onNuevo}
        >
          Nuevo permiso
        </Button>
      }
    >
      <TextInput
        label="Folio"
        placeholder="Ej: PER-2026-00001"
        {...contained}
        value={filtros.folio}
        onChange={(e) => onCambiar({ folio: e.currentTarget.value })}
        // Sin `leftSection`: en un campo contained la lupa se monta encima de
        // la etiqueta (07-formularios). El marcador ya dice qué se busca.
        style={{ minWidth: 220 }}
        rightSection={
          filtros.folio ? (
            <ActionIcon
              size="sm"
              variant="subtle"
              color="gray"
              onClick={() => onCambiar({ folio: '' })}
            >
              <IconX size={12} />
            </ActionIcon>
          ) : null
        }
      />

      <Select
        label="Tipo"
        placeholder="Todos"
        data={tipoOptions}
        clearable
        {...contained}
        value={filtros.tipo}
        onChange={(v) => onCambiar({ tipo: v })}
        style={{ minWidth: 160 }}
      />

      <Select
        label="Unidad administrativa"
        placeholder="Todas"
        data={unidadOptions}
        searchable
        clearable
        {...contained}
        value={filtros.unidadId}
        onChange={(v) => onCambiar({ unidadId: v })}
        style={{ minWidth: 240 }}
      />

      {/*
        Desde y Hasta son un solo filtro —un rango— y van juntos. Sueltos, la
        barra partía la fila entre los dos: «Desde» quedaba arriba y «Hasta»
        abajo a la izquierda, sin nada que los relacionara.
      */}
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Desde"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDate(filtros.fechaDesde)}
          onChange={(d) => onCambiar({ fechaDesde: fromDate(d ?? null) })}
          style={{ minWidth: 150 }}
        />

        <DatePickerInput
          label="Hasta"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          minDate={toDate(filtros.fechaDesde) ?? undefined}
          {...contained}
          value={toDate(filtros.fechaHasta)}
          onChange={(d) => onCambiar({ fechaHasta: fromDate(d ?? null) })}
          style={{ minWidth: 150 }}
        />
      </Group>

      <Group gap="xs">
        {FILTROS_ESTADO.map((valor) => (
          <Chip
            key={valor}
            // El chip toma el color del mismo tono semántico que la etiqueta
            // de estado, para que filtro y resultado coincidan.
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
