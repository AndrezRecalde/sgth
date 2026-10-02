'use client'

import { Box, Divider, Group, Radio, Stack, Title } from '@mantine/core'
import { Controller, type Control, type FieldErrors, type UseFormTrigger } from 'react-hook-form'
import { OPCIONES_LIKERT_PSICOSOCIAL } from '../schemas/psicosocial.schema'
import type { CuestionarioPsicosocialFormData } from '../schemas/cuestionarioPsicosocial.schema'
import type { PreguntaPsicosocial } from '../services/psicosocialService'

interface Props {
  etiqueta: string
  /** Los números de ítem de esta dimensión, ya ordenados. */
  items: number[]
  preguntas: Record<number, PreguntaPsicosocial>
  control: Control<CuestionarioPsicosocialFormData>
  errors: FieldErrors<CuestionarioPsicosocialFormData>
  trigger: UseFormTrigger<CuestionarioPsicosocialFormData>
}

/**
 * Una dimensión del cuestionario: sus ítems con la escala Likert de 4 puntos.
 *
 * El error va en SU ítem. Antes la validación decía «Debe responder todos los
 * ítems de esta sección (faltan 3)» en una notificación, y había que recorrer
 * la lista a ojo para encontrarlos — en «Otros puntos importantes» son
 * veinticuatro preguntas.
 */
export function PsicosocialDimension({
  etiqueta, items, preguntas, control, errors, trigger,
}: Props) {
  return (
    <Stack gap="lg">
      <Title order={4}>{etiqueta}</Title>

      {items.map((numero) => (
        <Box key={numero}>
          <Controller
            name={`respuestas.${numero}`}
            control={control}
            render={({ field }) => (
              <Radio.Group
                label={preguntas[numero].texto}
                required
                value={field.value !== null && field.value !== undefined ? String(field.value) : ''}
                // Revalidar al contestar: el error lo pone `trigger()` desde el
                // botón Siguiente, y el modo por defecto de React Hook Form
                // solo revalida tras un ENVÍO, así que se quedaba en rojo con
                // la respuesta ya marcada.
                onChange={(valor) => {
                  field.onChange(Number(valor))
                  void trigger(`respuestas.${numero}`)
                }}
                error={errors.respuestas?.[numero]?.message}
              >
                <Group mt="xs" gap="md">
                  {OPCIONES_LIKERT_PSICOSOCIAL.map((opcion) => (
                    <Radio key={opcion.value} value={String(opcion.value)} label={opcion.label} />
                  ))}
                </Group>
              </Radio.Group>
            )}
          />
          <Divider mt="md" />
        </Box>
      ))}
    </Stack>
  )
}
