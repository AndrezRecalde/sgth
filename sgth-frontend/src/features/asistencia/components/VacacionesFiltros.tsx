'use client'

import { ActionIcon, Button, Chip, Group, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconCubePlus, IconX } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import { fromDate, toDate } from '../utils/fechas'
import {
  ESTADO_LABELS,
  FILTROS_ESTADO,
  MOTIVO_LABELS,
  TONO_ESTADO,
} from './vacaciones.constants'
import type { EstadoVacacion, UnidadConRelaciones } from '@/types/api'

/*
| Los filtros del listado de vacaciones.
|
| La API acepta folio, estado, motivo, servidor, unidad y rango de fechas; la
| pantalla solo exponía folio y estado. Es el mismo hueco que tenía el listado
| de permisos antes de #37, y se cierra igual.
*/

export interface FiltrosVacacion {
  estado: string
  motivo: string | null
  unidadId: string | null
  fechaDesde: string | null
  fechaHasta: string | null
  folio: string
}

export const FILTROS_INICIALES: FiltrosVacacion = {
  estado: 'pendiente',
  motivo: null,
  unidadId: null,
  fechaDesde: null,
  fechaHasta: null,
  folio: '',
}

interface Props {
  filtros: FiltrosVacacion
  onCambiar: (filtros: Partial<FiltrosVacacion>) => void
  /** Sin él no hay botón: registrar exige `gestionar-vacaciones`. */
  onNueva?: () => void
}

export function VacacionesFiltros({ filtros, onCambiar, onNueva }: Props) {
  const contained = useContainedInput('sm')
  const { data: unidadesRaw } = useUnidades({ nivel: 2 })

  const unidadOptions = ((unidadesRaw ?? []) as UnidadConRelaciones[]).map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const motivoOptions = Object.entries(MOTIVO_LABELS).map(([value, label]) => ({
    value,
    label,
  }))

  return (
    <Toolbar
      actions={
        onNueva && (
          <Button
            color="emerald"
            variant="light"
            leftSection={<IconCubePlus size={16} />}
            onClick={onNueva}
          >
            Nueva solicitud
          </Button>
        )
      }
    >
      <TextInput
        label="Folio"
        placeholder="Ej: VAC-2026-00001"
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
        label="Motivo"
        placeholder="Todos"
        data={motivoOptions}
        searchable
        clearable
        {...contained}
        value={filtros.motivo}
        onChange={(v) => onCambiar({ motivo: v })}
        style={{ minWidth: 200 }}
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
            // Mismo tono semántico que la etiqueta de estado de la fila.
            color={SEMANTIC_COLOR[TONO_ESTADO[valor as EstadoVacacion] ?? 'neutral']}
            checked={filtros.estado === valor}
            onChange={() => onCambiar({ estado: valor })}
          >
            {valor === 'todos' ? 'Todos' : ESTADO_LABELS[valor as EstadoVacacion]}
          </Chip>
        ))}
      </Group>
    </Toolbar>
  )
}
