'use client'

import { Checkbox, Divider, Stack, Text, Title } from '@mantine/core'
import { Controller, type Control, type FieldErrors, type UseFormSetValue } from 'react-hook-form'
import type { SustanciaAssistInfo } from '../services/assistService'
import type { CuestionarioAssistFormData } from '../schemas/cuestionarioAssist.schema'

interface Props {
  /** El catálogo completo, en el orden en que lo manda el servidor. */
  sustancias: [string, SustanciaAssistInfo][]
  control: Control<CuestionarioAssistFormData>
  errors: FieldErrors<CuestionarioAssistFormData>
  setValue: UseFormSetValue<CuestionarioAssistFormData>
  /** Vigilados, porque las dos elecciones se excluyen entre sí. */
  seleccionadas: string[]
  sinConsumo: boolean
}

/**
 * P1 del ASSIST: qué sustancias ha consumido alguna vez.
 *
 * En su propio archivo porque es un paso del asistente con su propia regla —
 * marcar «no he consumido ninguna» vacía la lista y deshabilita las casillas,
 * y marcar una casilla desmarca aquello—, igual que el paso de cada sustancia
 * ya vivía aparte.
 */
export function AssistSeleccionSustancias({
  sustancias, control, errors, setValue, seleccionadas, sinConsumo,
}: Props) {
  const alternar = (clave: string, marcada: boolean) => {
    setValue(
      'seleccionadas',
      marcada ? [...seleccionadas, clave] : seleccionadas.filter((k) => k !== clave),
      { shouldValidate: false },
    )
    if (marcada) setValue('sinConsumo', false)
  }

  return (
    <Stack gap="sm">
      <Title order={4}>
        A lo largo de su vida, ¿cuáles de las siguientes sustancias ha consumido alguna vez?
      </Title>
      <Text size="sm" c="dimmed">
        Solo las que consumió sin receta médica. Seleccione todas las que apliquen.
      </Text>

      <Controller
        name="seleccionadas"
        control={control}
        render={({ field }) => (
          <Checkbox.Group
            value={field.value}
            onChange={field.onChange}
            error={errors.seleccionadas?.message}
          >
            <Stack gap="xs">
              {sustancias.map(([clave, info]) => (
                <Checkbox
                  key={clave}
                  value={clave}
                  label={`${info.etiqueta} (${info.ejemplos})`}
                  disabled={sinConsumo}
                  onChange={(e) => alternar(clave, e.currentTarget.checked)}
                />
              ))}
            </Stack>
          </Checkbox.Group>
        )}
      />

      <Divider my="xs" />

      <Controller
        name="sinConsumo"
        control={control}
        render={({ field }) => (
          <Checkbox
            checked={field.value}
            label={<Text fw={600}>No he consumido ninguna de estas sustancias</Text>}
            onChange={(e) => {
              field.onChange(e.currentTarget.checked)
              // Manual ASSIST, Fig. 1: si no ha consumido ninguna, se detiene
              // la entrevista. Lo que hubiera marcado antes deja de contar.
              if (e.currentTarget.checked) setValue('seleccionadas', [])
            }}
          />
        )}
      />
    </Stack>
  )
}
