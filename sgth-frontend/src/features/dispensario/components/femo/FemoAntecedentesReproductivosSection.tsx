'use client'

import { Stack, Grid, TextInput, Select, NumberInput, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { AntecedenteReproductivoForm } from '../../schemas/femo.schema'
import { METODO_PLANIFICACION_OPTIONS } from '../../services/femoOptions'

interface Props {
  data:     Partial<AntecedenteReproductivoForm>
  onChange: (data: Partial<AntecedenteReproductivoForm>) => void
}

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}

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

export function FemoAntecedentesReproductivosSection({ data, onChange }: Props) {
  const contained = useContainedInput()

  return (
    <Stack gap="xs">
      <Text size="xs" fw={600} c="dimmed" tt="uppercase" style={{ letterSpacing: '0.05em' }}>
        Antecedentes gineco obstétricos / reproductivos
      </Text>
      <Grid>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <DatePickerInput
            label="Fecha última menstruación"
            valueFormat="DD/MM/YYYY"
            clearable
            {...contained}
            value={toDate(data.fecha_ultima_menstruacion)}
            onChange={(d) => onChange({
              ...data, fecha_ultima_menstruacion: fromDate(d as Date | null),
            })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, md: 2 }}>
          <NumberInput
            label="Gestas"
            min={0}
            {...contained}
            value={data.gestas ?? undefined}
            onChange={(v) => onChange({ ...data, gestas: v !== '' ? Number(v) : null })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, md: 2 }}>
          <NumberInput
            label="Partos"
            min={0}
            {...contained}
            value={data.partos ?? undefined}
            onChange={(v) => onChange({ ...data, partos: v !== '' ? Number(v) : null })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, md: 2 }}>
          <NumberInput
            label="Cesáreas"
            min={0}
            {...contained}
            value={data.cesareas ?? undefined}
            onChange={(v) => onChange({ ...data, cesareas: v !== '' ? Number(v) : null })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, md: 2 }}>
          <NumberInput
            label="Abortos"
            min={0}
            {...contained}
            value={data.abortos ?? undefined}
            onChange={(v) => onChange({ ...data, abortos: v !== '' ? Number(v) : null })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Select
            label="Método de planificación familiar"
            data={METODO_PLANIFICACION_OPTIONS}
            {...contained}
            value={data.usa_metodo_planificacion ?? null}
            onChange={(v) => onChange({
              ...data, usa_metodo_planificacion: v as AntecedenteReproductivoForm['usa_metodo_planificacion'],
            })}
          />
        </Grid.Col>
        {data.usa_metodo_planificacion === 'si' && (
          <Grid.Col span={{ base: 12, md: 4 }}>
            <TextInput
              label="¿Cuál?"
              {...contained}
              value={data.metodo_planificacion_cual ?? ''}
              onChange={(e) => onChange({
                ...data, metodo_planificacion_cual: e.currentTarget.value,
              })}
            />
          </Grid.Col>
        )}
        <Grid.Col span={{ base: 12, md: 5 }}>
          <TextInput
            label="Exámenes realizados (¿cuál?)"
            placeholder="Aplica a antecedentes reproductivos"
            {...contained}
            value={data.examenes_realizados ?? ''}
            onChange={(e) => onChange({
              ...data, examenes_realizados: e.currentTarget.value,
            })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 3 }}>
          <NumberInput
            label="Tiempo (años)"
            min={0}
            {...contained}
            value={data.examenes_tiempo_anios ?? undefined}
            onChange={(v) => onChange({
              ...data, examenes_tiempo_anios: v !== '' ? Number(v) : null,
            })}
          />
        </Grid.Col>
      </Grid>
    </Stack>
  )
}
