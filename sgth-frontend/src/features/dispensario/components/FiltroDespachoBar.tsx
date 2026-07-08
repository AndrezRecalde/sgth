'use client'

import {
  Group, Stack, Text, Popover,
  TextInput, Button, UnstyledButton,
  Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconSearch, IconX } from '@tabler/icons-react'
import { useState } from 'react'
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
  const [popoverAbierto, setPopoverAbierto] = useState<
    'medico' | 'fechas' | 'estado' | null
  >(null)

  const hayFiltros = !!(
    value.medico_id || value.fecha_desde ||
    value.fecha_hasta || value.estado
  )

  const medicoSeleccionado = medicos.find(
    m => m.id === value.medico_id
  )
  const labelMedico = medicoSeleccionado
    ? medicoSeleccionado.nombre_completo ?? 'Dr.'
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
    const nombre = (m.nombre_completo ?? '').toLowerCase()
    return nombre.includes(busquedaMedico.toLowerCase())
  })

  const setHoy = () => {
    const hoy = new Date().toISOString().slice(0, 10)
    onChange({ ...value, fecha_desde: hoy, fecha_hasta: hoy })
  }

  const setSemana = () => {
    const hoy = new Date()
    const lun = new Date(hoy)
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

  const seccionActiva = (sec: 'medico' | 'fechas' | 'estado') =>
    popoverAbierto === sec

  const seccionStyle = (sec: 'medico' | 'fechas' | 'estado') => ({
    padding: '12px 20px',
    borderRadius: 40,
    cursor: 'pointer' as const,
    background: seccionActiva(sec)
      ? 'var(--mantine-color-gray-1)'
      : 'transparent',
    transition: 'background 0.15s',
    flex: sec === 'fechas' ? 1.4 : 1,
    minWidth: 0,
  })

  return (
    <Stack gap="xs">
      <Group justify="center">
      <div style={{
        border: '1px solid var(--mantine-color-gray-3)',
        borderRadius: 40,
        background: 'var(--mantine-color-white)',
        boxShadow: '0 2px 12px rgba(0,0,0,0.08)',
        display: 'flex',
        alignItems: 'center',
        width: '100%',
        maxWidth: 680,
        overflow: 'visible',
      }}>
        <Popover
          opened={popoverAbierto === 'medico'}
          onClose={() => setPopoverAbierto(null)}
          position="bottom-start"
          shadow="md"
          width={280}
          withArrow={false}
        >
          <Popover.Target>
            <UnstyledButton
              style={seccionStyle('medico')}
              onClick={() => setPopoverAbierto(
                popoverAbierto === 'medico' ? null : 'medico'
              )}
            >
              <Text size="xs" fw={600} c="dark" style={{ lineHeight: 1.3 }}>
                Médico
              </Text>
              <Text size="sm" c={value.medico_id ? 'dark' : 'dimmed'}
                style={{ lineHeight: 1.3 }} truncate>
                {labelMedico}
              </Text>
            </UnstyledButton>
          </Popover.Target>
          <Popover.Dropdown p="xs">
            <Stack gap="xs">
              <TextInput
                placeholder="Buscar médico..."
                size="xs"
                {...contained}
                value={busquedaMedico}
                onChange={(e) =>
                  setBusquedaMedico(e.currentTarget.value)}
              />
              {[{ id: null, nombre_completo: 'Todos los médicos' }, ...medicosFiltrados].map((m, i) => (
                <UnstyledButton
                  key={m.id ?? 'todos'}
                  onClick={() => {
                    onChange({ ...value, medico_id: m.id })
                    setPopoverAbierto(null)
                    setBusquedaMedico('')
                  }}
                  style={{
                    padding: '8px 10px',
                    borderRadius: 8,
                    background: value.medico_id === m.id ||
                      (!value.medico_id && m.id === null)
                      ? 'var(--mantine-color-gray-1)'
                      : 'transparent',
                    fontWeight: value.medico_id === m.id ||
                      (!value.medico_id && m.id === null) ? 600 : 400,
                    fontSize: 13,
                    width: '100%',
                    textAlign: 'left',
                    color: 'var(--mantine-color-dark-7)',
                  }}
                >
                  {m.nombre_completo}
                </UnstyledButton>
              ))}
            </Stack>
          </Popover.Dropdown>
        </Popover>

        <Divider orientation="vertical" style={{ height: 32 }} />

        <Popover
          opened={popoverAbierto === 'fechas'}
          onClose={() => setPopoverAbierto(null)}
          position="bottom"
          shadow="md"
          width={320}
          withArrow={false}
        >
          <Popover.Target>
            <UnstyledButton
              style={seccionStyle('fechas')}
              onClick={() => setPopoverAbierto(
                popoverAbierto === 'fechas' ? null : 'fechas'
              )}
            >
              <Text size="xs" fw={600} c="dark" style={{ lineHeight: 1.3 }}>
                Fechas
              </Text>
              <Text size="sm" c={value.fecha_desde ? 'dark' : 'dimmed'}
                style={{ lineHeight: 1.3 }} truncate>
                {labelFechas}
              </Text>
            </UnstyledButton>
          </Popover.Target>
          <Popover.Dropdown p="sm">
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
                <Button size="compact-xs" variant="outline"
                  radius="xl" onClick={setHoy}>Hoy</Button>
                <Button size="compact-xs" variant="outline"
                  radius="xl" onClick={setSemana}>Esta semana</Button>
                <Button size="compact-xs" variant="outline"
                  radius="xl" onClick={setMes}>Este mes</Button>
              </Group>
            </Stack>
          </Popover.Dropdown>
        </Popover>

        <Divider orientation="vertical" style={{ height: 32 }} />

        <Popover
          opened={popoverAbierto === 'estado'}
          onClose={() => setPopoverAbierto(null)}
          position="bottom-end"
          shadow="md"
          width={220}
          withArrow={false}
        >
          <Popover.Target>
            <UnstyledButton
              style={seccionStyle('estado')}
              onClick={() => setPopoverAbierto(
                popoverAbierto === 'estado' ? null : 'estado'
              )}
            >
              <Text size="xs" fw={600} c="dark" style={{ lineHeight: 1.3 }}>
                Estado
              </Text>
              <Text size="sm" c="dark" style={{ lineHeight: 1.3 }}>
                {labelEstado}
              </Text>
            </UnstyledButton>
          </Popover.Target>
          <Popover.Dropdown p="xs">
            <Stack gap={2}>
              {ESTADO_OPTIONS.map(o => (
                <UnstyledButton
                  key={o.value}
                  onClick={() => {
                    onChange({ ...value, estado: o.value || null })
                    setPopoverAbierto(null)
                  }}
                  style={{
                    padding: '8px 12px',
                    borderRadius: 8,
                    background: (value.estado ?? '') === o.value
                      ? 'var(--mantine-color-gray-1)'
                      : 'transparent',
                    fontWeight: (value.estado ?? '') === o.value
                      ? 600 : 400,
                    fontSize: 13,
                    width: '100%',
                    textAlign: 'left',
                    color: 'var(--mantine-color-dark-7)',
                  }}
                >
                  {o.label}
                </UnstyledButton>
              ))}
            </Stack>
          </Popover.Dropdown>
        </Popover>

        <div style={{ padding: '6px 8px 6px 4px' }}>
          <UnstyledButton
            onClick={onSearch}
            style={{
              borderRadius: 24,
              background: 'var(--mantine-color-red-6)',
              color: 'white',
              display: 'flex',
              alignItems: 'center',
              gap: 6,
              padding: '10px 16px',
              fontSize: 14,
              fontWeight: 500,
            }}
          >
            <IconSearch size={15} />
            Buscar
          </UnstyledButton>
        </div>
      </div>
      </Group>
      {hayFiltros && (
        <Group justify="center">
          <div style={{ width: '100%', maxWidth: 680 }}>
            <Group justify="flex-end">
              <UnstyledButton
                onClick={onReset}
                style={{
                  fontSize: 13,
                  color: 'var(--mantine-color-dark-4)',
                  textDecoration: 'underline',
                  cursor: 'pointer',
                }}
              >
                Limpiar filtros
              </UnstyledButton>
            </Group>
          </div>
        </Group>
      )}
    </Stack>
  )
}
