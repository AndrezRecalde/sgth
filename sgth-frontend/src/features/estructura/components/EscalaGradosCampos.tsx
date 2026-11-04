'use client'

import { NumberInput, Paper, Group, Stack, Text } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { dinero } from '@/lib/dinero'
import type { GrupoOcupacional } from '@/types/api'
import type { EscalaRemuneracionFormData } from '../schemas/escalaRemuneracion.schema'

interface Props {
  control: Control<EscalaRemuneracionFormData>
  errors:  FieldErrors<EscalaRemuneracionFormData>
  /** Los grados que suben con la escala: la LOSEP, sin autoridades electas ni obreros. */
  grados:  GrupoOcupacional[]
}

/** La RMU nueva de cada grado; el que se deja en blanco no cambia. */
export function EscalaGradosCampos({ control, errors, grados }: Props) {
  const contained = useContainedInput()

  return (
    <Paper withBorder p="sm" radius="md">
      <Stack gap={6}>
        <Text size="sm" fw={600}>RMU nueva de cada grado</Text>
        <Text size="xs" c="dimmed">Deje en blanco los grados que no cambian.</Text>
        {grados.map((g, i) => (
          <Group key={g.id} justify="space-between" wrap="nowrap" gap="sm">
            <Text size="sm" style={{ flex: 1, minWidth: 0 }}>
              <b>{g.grado_codigo}</b> · {g.denominacion_generica ?? g.grupo} · hoy {dinero(g.rmu)}
            </Text>
            <Controller
              name={`grados.${i}.rmu_nueva`}
              control={control}
              render={({ field }) => (
                <NumberInput
                  aria-label={`RMU nueva del grado ${g.grado_codigo}`}
                  placeholder="Igual"
                  min={0}
                  decimalScale={2}
                  hideControls
                  prefix="$ "
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
                  w={140}
                  {...contained}
                />
              )}
            />
          </Group>
        ))}
        {errors.grados?.root?.message && <Text size="xs" c="red">{errors.grados.root.message}</Text>}
        {errors.grados?.message && <Text size="xs" c="red">{errors.grados.message}</Text>}
      </Stack>
    </Paper>
  )
}
