'use client'

import {
  Group, Stack, Text, Popover,
  TextInput, Button, UnstyledButton,
  Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalMedico } from '../hooks/useAgenda'
import styles from './FiltroDespachoBar.module.css'

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

  const medicoSeleccionado = medicos.find(m => m.id === value.medico_id)
  const labelMedico = medicoSeleccionado
    ? (medicoSeleccionado.nombre_completo ?? 'Dr.')
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
    return (m.nombre_completo ?? '').toLowerCase()
      .includes(busquedaMedico.toLowerCase())
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

  const seccionClass = (sec: 'medico' | 'fechas' | 'estado') => {
    const base = [
      styles.seccion,
      sec === 'medico' ? styles.seccionMedico
        : sec === 'fechas' ? styles.seccionFechas
        : styles.seccionEstado,
    ]
    if (popoverAbierto === sec) base.push(styles.seccionActiva)
    return base.join(' ')
  }

  const opcionClass = (esActivo: boolean) =>
    [styles.opcionLista, esActivo ? styles.opcionListaActiva : ''].join(' ')

  return (
    <Stack gap="xs">
      <div className={styles.wrapper}>
        <div className={styles.barra}>

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
                className={seccionClass('medico')}
                onClick={() => setPopoverAbierto(
                  popoverAbierto === 'medico' ? null : 'medico'
                )}
              >
                <div className={styles.seccionLabel}>Médico</div>
                <div className={[
                  styles.seccionValor,
                  value.medico_id
                    ? styles.seccionValorActivo
                    : styles.seccionValorPlaceholder,
                ].join(' ')}>
                  {labelMedico}
                </div>
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
                {[
                  { id: null, nombre_completo: 'Todos los médicos' },
                  ...medicosFiltrados,
                ].map((m) => (
                  <button
                    key={m.id ?? 'todos'}
                    className={opcionClass(
                      value.medico_id === m.id ||
                      (!value.medico_id && m.id === null)
                    )}
                    onClick={() => {
                      onChange({ ...value, medico_id: m.id })
                      setPopoverAbierto(null)
                      setBusquedaMedico('')
                    }}
                  >
                    {m.nombre_completo}
                  </button>
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
                className={seccionClass('fechas')}
                onClick={() => setPopoverAbierto(
                  popoverAbierto === 'fechas' ? null : 'fechas'
                )}
              >
                <div className={styles.seccionLabel}>Fechas</div>
                <div className={[
                  styles.seccionValor,
                  value.fecha_desde
                    ? styles.seccionValorActivo
                    : styles.seccionValorPlaceholder,
                ].join(' ')}>
                  {labelFechas}
                </div>
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
                className={seccionClass('estado')}
                onClick={() => setPopoverAbierto(
                  popoverAbierto === 'estado' ? null : 'estado'
                )}
              >
                <div className={styles.seccionLabel}>Estado</div>
                <div className={[
                  styles.seccionValor,
                  styles.seccionValorActivo,
                ].join(' ')}>
                  {labelEstado}
                </div>
              </UnstyledButton>
            </Popover.Target>
            <Popover.Dropdown p="xs">
              <Stack gap={2}>
                {ESTADO_OPTIONS.map(o => (
                  <button
                    key={o.value}
                    className={opcionClass(
                      (value.estado ?? '') === o.value
                    )}
                    onClick={() => {
                      onChange({ ...value, estado: o.value || null })
                      setPopoverAbierto(null)
                    }}
                  >
                    {o.label}
                  </button>
                ))}
              </Stack>
            </Popover.Dropdown>
          </Popover>

          <div className={styles.buscadorWrapper}>
            <button
              className={styles.btnBuscar}
              onClick={onSearch}
            >
              <IconSearch size={15} />
              Buscar
            </button>
          </div>
        </div>
      </div>

      {hayFiltros && (
        <div className={styles.wrapper}>
          <div className={styles.limpiarWrapper}>
            <Group justify="flex-end">
              <button
                className={styles.btnLimpiar}
                onClick={onReset}
              >
                Limpiar filtros
              </button>
            </Group>
          </div>
        </div>
      )}
    </Stack>
  )
}
