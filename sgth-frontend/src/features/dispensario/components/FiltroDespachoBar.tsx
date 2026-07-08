'use client'

import {
  Paper, Group, Stack, Text, Popover,
  TextInput, Radio, Button, Chip,
  ActionIcon, Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import {
  IconSearch, IconX, IconUserCheck,
  IconCalendar, IconFilter,
} from '@tabler/icons-react'
import { useState, useCallback } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalMedico } from '../hooks/useAgenda'

export interface FiltroDespacho {
  medico_id?:   number | null
  fecha_desde?: string | null
  fecha_hasta?: string | null
  estado?:      string | null
}

interface Props {
  value:    FiltroDespacho
  onChange: (filtro: FiltroDespacho) => void
  onSearch: () => void
  onReset:  () => void
}

const ESTADO_OPTIONS = [
  { value: '',                    label: 'Todos'      },
  { value: 'pendiente',           label: 'Pendiente'  },
  { value: 'despachada_parcial',  label: 'Parcial'    },
  { value: 'despachada_completa', label: 'Completada' },
  { value: 'anulada',             label: 'Anulada'    },
]

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  if (typeof d === 'string') return d.slice(0, 10)
  if (!(d instanceof Date) || isNaN(d.getTime())) return null
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function formatFechaCorta(s: string | null | undefined): string {
  if (!s) return ''
  return new Date(s + 'T00:00').toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short',
  })
}

export function FiltroDespachoBar({
  value, onChange, onSearch, onReset,
}: Props) {
  const contained = useContainedInput()
  const { data: medicos = [] } = usePersonalMedico('medico')
  const [busquedaMedico, setBusquedaMedico] = useState('')

  const hayFiltros = !!(
    value.medico_id || value.fecha_desde ||
    value.fecha_hasta || value.estado
  )

  const medicoSeleccionado = medicos.find(
    m => m.id === value.medico_id
  )
  const labelMedico = medicoSeleccionado
    ? `Dr. ${medicoSeleccionado.nombre_completo}`
    : 'Todos los médicos'

  const labelFechas = value.fecha_desde
    ? value.fecha_hasta && value.fecha_desde !== value.fecha_hasta
      ? `${formatFechaCorta(value.fecha_desde)} — ${formatFechaCorta(value.fecha_hasta)}`
      : formatFechaCorta(value.fecha_desde)
    : 'Agregar rango'

  const labelEstado = ESTADO_OPTIONS.find(
    o => o.value === (value.estado ?? '')
  )?.label ?? 'Todos'

  const medicosFiltrados = medicos.filter(m => {
    if (!busquedaMedico) return true
    const nombre = m.nombre_completo.toLowerCase()
    return nombre.includes(busquedaMedico.toLowerCase())
  })

  const setHoy = () => {
    const hoy = new Date().toISOString().slice(0, 10)
    onChange({ ...value, fecha_desde: hoy, fecha_hasta: hoy })
  }

  const setSemana = () => {
    const hoy  = new Date()
    const lun  = new Date(hoy)
    lun.setDate(hoy.getDate() - hoy.getDay() + 1)
    const vier = new Date(lun)
    vier.setDate(lun.getDate() + 4)
    onChange({
      ...value,
      fecha_desde: lun.toISOString().slice(0, 10),
      fecha_hasta: vier.toISOString().slice(0, 10),
    })
  }

  const setMes = () => {
    const hoy   = new Date()
    const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
    const fin    = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0)
    onChange({
      ...value,
      fecha_desde: inicio.toISOString().slice(0, 10),
      fecha_hasta: fin.toISOString().slice(0, 10),
    })
  }

  return (
    <Stack gap="xs">
      <Paper
        withBorder
        radius={40}
        style={{ overflow: 'hidden' }}
      >
        <Group gap={0} wrap="nowrap">

          <Popover width={280} position="bottom-start" shadow="md">
            <Popover.Target>
              <Stack
                gap={0}
                px="md"
                py="sm"
                style={{
                  flex: 1,
                  cursor: 'pointer',
                  borderRight: '1px solid var(--mantine-color-gray-2)',
                  minWidth: 0,
                }}
              >
                <Text size="xs" fw={500} c="dimmed" tt="uppercase"
                  style={{ letterSpacing: '0.05em' }}
                >
                  Médico
                </Text>
                <Text size="sm" truncate>
                  {labelMedico}
                </Text>
              </Stack>
            </Popover.Target>
            <Popover.Dropdown>
              <Stack gap="xs">
                <TextInput
                  placeholder="Buscar médico..."
                  size="xs"
                  {...contained}
                  value={busquedaMedico}
                  onChange={(e) =>
                    setBusquedaMedico(e.currentTarget.value)}
                />
                <Radio.Group
                  value={String(value.medico_id ?? '')}
                  onChange={(v) => onChange({
                    ...value,
                    medico_id: v ? Number(v) : null,
                  })}
                >
                  <Stack gap={4}>
                    <Radio
                      value=""
                      label="Todos los médicos"
                      size="sm"
                    />
                    {medicosFiltrados.map(m => (
                      <Radio
                        key={m.id}
                        value={String(m.id)}
                        label={`Dr. ${m.nombre_completo}`}
                        size="sm"
                      />
                    ))}
                  </Stack>
                </Radio.Group>
              </Stack>
            </Popover.Dropdown>
          </Popover>

          <Popover width={320} position="bottom" shadow="md">
            <Popover.Target>
              <Stack
                gap={0}
                px="md"
                py="sm"
                style={{
                  flex: 1.3,
                  cursor: 'pointer',
                  borderRight: '1px solid var(--mantine-color-gray-2)',
                  minWidth: 0,
                }}
              >
                <Text size="xs" fw={500} c="dimmed" tt="uppercase"
                  style={{ letterSpacing: '0.05em' }}
                >
                  Fechas
                </Text>
                <Text size="sm" truncate c={value.fecha_desde ? undefined : 'dimmed'}>
                  {labelFechas}
                </Text>
              </Stack>
            </Popover.Target>
            <Popover.Dropdown>
              <Stack gap="sm">
                <DatePickerInput
                  type="range"
                  label="Rango de fechas"
                  valueFormat="DD/MM/YYYY"
                  clearable
                  {...contained}
                  value={[
                    toDate(value.fecha_desde),
                    toDate(value.fecha_hasta),
                  ]}
                  onChange={(v) => {
                    const [ini, fin] = v as [Date | null, Date | null]
                    onChange({
                      ...value,
                      fecha_desde: fromDate(ini),
                      fecha_hasta: fromDate(fin),
                    })
                  }}
                />
                <Group gap="xs">
                  <Button size="compact-xs" variant="light"
                    onClick={setHoy}>Hoy</Button>
                  <Button size="compact-xs" variant="light"
                    onClick={setSemana}>Esta semana</Button>
                  <Button size="compact-xs" variant="light"
                    onClick={setMes}>Este mes</Button>
                </Group>
              </Stack>
            </Popover.Dropdown>
          </Popover>

          <Popover width={260} position="bottom" shadow="md">
            <Popover.Target>
              <Stack
                gap={0}
                px="md"
                py="sm"
                style={{
                  flex: 1,
                  cursor: 'pointer',
                  minWidth: 0,
                }}
              >
                <Text size="xs" fw={500} c="dimmed" tt="uppercase"
                  style={{ letterSpacing: '0.05em' }}
                >
                  Estado
                </Text>
                <Text size="sm" truncate>
                  {labelEstado}
                </Text>
              </Stack>
            </Popover.Target>
            <Popover.Dropdown>
              <Stack gap="xs">
                <Text size="xs" fw={500} c="dimmed" tt="uppercase"
                  style={{ letterSpacing: '0.05em' }}
                >
                  Filtrar por estado
                </Text>
                <Chip.Group
                  value={value.estado ?? ''}
                  onChange={(v) => onChange({
                    ...value, estado: v as string || null,
                  })}
                >
                  <Group gap="xs">
                    {ESTADO_OPTIONS.map(o => (
                      <Chip key={o.value} value={o.value} size="sm">
                        {o.label}
                      </Chip>
                    ))}
                  </Group>
                </Chip.Group>
              </Stack>
            </Popover.Dropdown>
          </Popover>

          <Group gap="xs" px="sm">
            {hayFiltros && (
              <ActionIcon
                variant="subtle"
                color="gray"
                radius="xl"
                onClick={onReset}
                title="Limpiar filtros"
              >
                <IconX size={14} />
              </ActionIcon>
            )}
            <ActionIcon
              color="emerald"
              radius="xl"
              size="lg"
              onClick={onSearch}
            >
              <IconSearch size={16} />
            </ActionIcon>
          </Group>
        </Group>
      </Paper>
    </Stack>
  )
}
