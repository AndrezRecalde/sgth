'use client'

import { Card, Group, Stack, Text, ThemeIcon } from '@mantine/core'
import { IconCheckbox, IconHash, IconList } from '@tabler/icons-react'
import { Controller, type Control } from 'react-hook-form'
import { SectionHeading, StatusBadge } from '@/components/ui'
import type { CriterioEvaluacion } from '../../services/criterioService'
import { claveCriterio, puntajeCriterio, type CalificacionForm } from './calificacion'
import { CriterioInput } from './CriterioInput'

const TIPO_ICONS: Record<string, React.ReactNode> = {
  radio:     <IconList size={14} />,
  checklist: <IconCheckbox size={14} />,
  numero:    <IconHash size={14} />,
}

interface Props {
  titulo:    string
  criterios: CriterioEvaluacion[]
  valores:   CalificacionForm
  control:   Control<CalificacionForm>
}

/**
 * Una sección de la calificación —méritos u oposición— con sus criterios.
 * Antes eran dos bloques de 70 líneas copiados uno del otro.
 */
export function SeccionCalificacion({ titulo, criterios, valores, control }: Props) {
  if (criterios.length === 0) return null
  const total = criterios.reduce((s, c) => s + puntajeCriterio(c, valores[claveCriterio(c.id)]), 0)

  return (
    <Stack gap="sm">
      <SectionHeading title={titulo} action={<StatusBadge>{total.toFixed(2)} pts</StatusBadge>} />
      {criterios.map((c, i) => (
        <Card key={c.id} withBorder radius="md" p="sm">
          <Stack gap="sm">
            <Group justify="space-between" wrap="nowrap">
              <Group gap="xs">
                <ThemeIcon size="xs" variant="light">{TIPO_ICONS[c.tipo_input]}</ThemeIcon>
                <Text size="sm" fw={500}>{i + 1}. {c.nombre}</Text>
              </Group>
              <StatusBadge size="xs">Máx: {c.puntaje_maximo} pts</StatusBadge>
            </Group>
            {c.descripcion && <Text size="xs" c="dimmed">{c.descripcion}</Text>}
            <Controller
              name={claveCriterio(c.id)}
              control={control}
              render={({ field, fieldState }) => (
                <CriterioInput criterio={c} value={field.value ?? {}} onChange={field.onChange}
                  error={fieldState.error?.message} />
              )}
            />
            <Text size="xs" c="dimmed" ta="right">
              Puntaje: {puntajeCriterio(c, valores[claveCriterio(c.id)]).toFixed(2)} pts
            </Text>
          </Stack>
        </Card>
      ))}
    </Stack>
  )
}
