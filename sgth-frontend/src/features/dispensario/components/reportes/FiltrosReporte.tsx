'use client'

import { Button, Group, Select, SegmentedControl, Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconFileSpreadsheet, IconSearch } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type {
  FiltroReporte, FiltrosReporteDispensario, OpcionReporte,
} from '../../services/reportesDispensarioService'

interface Props {
  filtros:      FiltrosReporteDispensario
  disponibles:  FiltroReporte[]
  opciones:     { profesionales: OpcionReporte[]; unidades: OpcionReporte[] }
  onCambiar:    (cambio: Partial<FiltrosReporteDispensario>) => void
  onConsultar:  () => void
  consultando:  boolean
  onDescargar:  () => void
  descargando:  boolean
  /** No se descarga lo que no se ha consultado: se sabría qué sale. */
  puedeDescargar: boolean
}

const ESPECIALIDADES = [
  { value: 'medicina_general', label: 'Medicina general' },
  { value: 'odontologia',      label: 'Odontología' },
]

const TIPOS_PACIENTE = [
  { value: 'servidor', label: 'Servidores' },
  { value: 'familiar', label: 'Familiares' },
]

const aOpciones = (lista: OpcionReporte[]) =>
  lista.map((o) => ({ value: String(o.id), label: o.nombre }))

/**
 * El período y los filtros que el reporte elegido admite. Cada reporte
 * declara los suyos en el backend: aquí solo se dibujan.
 */
export function FiltrosReporte({
  filtros, disponibles, opciones, onCambiar, onConsultar, consultando,
  onDescargar, descargando, puedeDescargar,
}: Props) {
  const contained = useContainedInput('sm')
  const admite = (f: FiltroReporte) => disponibles.includes(f)

  return (
    <Toolbar
      actions={
        <>
          <Button leftSection={<IconSearch size={16} />} loading={consultando} onClick={onConsultar}>
            Consultar
          </Button>
          <Button
            variant="light"
            leftSection={<IconFileSpreadsheet size={16} />}
            disabled={!puedeDescargar}
            loading={descargando}
            onClick={onDescargar}
          >
            Descargar Excel
          </Button>
        </>
      }
    >
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Desde"
          valueFormat="DD/MM/YYYY"
          {...contained}
          value={toDateValue(filtros.desde)}
          onChange={(d) => d && onCambiar({ desde: fromDateValue(d) })}
          w={140}
        />
        <DatePickerInput
          label="Hasta"
          valueFormat="DD/MM/YYYY"
          minDate={toDateValue(filtros.desde) ?? undefined}
          {...contained}
          value={toDateValue(filtros.hasta)}
          onChange={(d) => d && onCambiar({ hasta: fromDateValue(d) })}
          w={140}
        />
      </Group>

      {admite('profesional') && (
        <Select
          label="Profesional"
          placeholder="Todos"
          data={aOpciones(opciones.profesionales)}
          searchable
          clearable
          {...contained}
          value={filtros.profesional_id ? String(filtros.profesional_id) : null}
          onChange={(v) => onCambiar({ profesional_id: v ? Number(v) : null })}
          w={220}
        />
      )}

      {admite('especialidad') && (
        <Select
          label="Especialidad"
          placeholder="Todas"
          data={ESPECIALIDADES}
          clearable
          {...contained}
          value={filtros.especialidad ?? null}
          onChange={(v) => onCambiar({ especialidad: v })}
          w={180}
        />
      )}

      {admite('tipo_paciente') && (
        <Select
          label="Pacientes"
          placeholder="Todos"
          data={TIPOS_PACIENTE}
          clearable
          {...contained}
          value={filtros.tipo_paciente ?? null}
          onChange={(v) => onCambiar({ tipo_paciente: v })}
          w={160}
        />
      )}

      {admite('unidad') && opciones.unidades.length > 0 && (
        <Select
          label="Unidad administrativa"
          placeholder="Todas"
          data={aOpciones(opciones.unidades)}
          searchable
          clearable
          {...contained}
          value={filtros.unidad_administrativa_id ? String(filtros.unidad_administrativa_id) : null}
          onChange={(v) => onCambiar({ unidad_administrativa_id: v ? Number(v) : null })}
          w={240}
        />
      )}

      {admite('agrupacion') && (
        <Stack gap={4}>
          <Text size="xs" fw={500}>Agrupar por</Text>
          <SegmentedControl
            size="xs"
            data={[{ value: 'profesional', label: 'Profesional' }, { value: 'dia', label: 'Día' }]}
            value={filtros.agrupacion ?? 'profesional'}
            onChange={(v) => onCambiar({ agrupacion: v === 'dia' ? 'dia' : 'profesional' })}
          />
        </Stack>
      )}
    </Toolbar>
  )
}
