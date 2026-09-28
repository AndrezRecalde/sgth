'use client'

import { Alert, Select, Stack } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { ModalFooter } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import {
  SUBTIPO_LABELS, TIPO_LABELS, requiereSubtipo,
  type AccionSubtipo, type AccionTipo,
} from '../utils/taxonomiaAccionPersonal'

interface Props {
  form: UseFormReturn<MovimientoFormData>
  /** Tipos ofrecibles para el nombramiento vigente del servidor. */
  tipos: AccionTipo[]
  /** Subtipos del tipo elegido, ya filtrados por nombramiento. */
  subtipos: AccionSubtipo[]
  tipo?: AccionTipo
  subtipo?: AccionSubtipo | null
  /** Con tipo fijo no se vuelve a preguntar: ya se eligió antes de abrir. */
  tipoFijo?: AccionTipo
  /** Elegir subtipo arrastra el default del dictamen médico. */
  elegirSubtipo: (valor: AccionSubtipo | null) => void
  onCancel: () => void
  onContinuar: () => void
}

/**
 * Primer paso: qué se va a registrar.
 *
 * Es el subtipo el que determina las reglas y el documento, así que este paso
 * sigue haciendo falta incluso con el tipo ya fijado desde el asistente de
 * categorías — saltárselo dejaba un formulario que el backend rechazaba por un
 * dato que nunca se pidió.
 */
export function MovimientoPasoTipo({
  form, tipos, subtipos, tipo, subtipo, tipoFijo, elegirSubtipo, onCancel, onContinuar,
}: Props) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  const puedeAvanzar = !!tipo && (!requiereSubtipo(tipo) || !!subtipo)

  return (
    <Stack gap="sm" mt="md">
      {/* Con tipo fijo no se vuelve a preguntar: ya se eligió en el grid, y
          ofrecerlo otra vez permitiría contradecirlo. */}
      {!tipoFijo && (
        <Controller
          name="tipo_movimiento"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de acción de personal"
              data={tipos.map((t) => ({ value: t, label: TIPO_LABELS[t] }))}
              value={field.value}
              onChange={(v) => {
                field.onChange(tipos.find((t) => t === v))
                elegirSubtipo(null)
              }}
              error={errors.tipo_movimiento?.message}
              {...contained}
            />
          )}
        />
      )}

      {tipo && requiereSubtipo(tipo) && (
        <Controller
          name="subtipo_movimiento"
          control={control}
          render={({ field }) => (
            <Select
              label="Subtipo"
              placeholder="Seleccione el subtipo"
              description="Es el subtipo el que determina las reglas y el documento que se imprime."
              data={subtipos.map((s) => ({ value: s, label: SUBTIPO_LABELS[s] }))}
              value={field.value ?? null}
              onChange={(v) => elegirSubtipo(subtipos.find((s) => s === v) ?? null)}
              error={errors.subtipo_movimiento?.message}
              {...contained}
            />
          )}
        />
      )}

      {tipo && requiereSubtipo(tipo) && subtipos.length === 0 && (
        <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
          Ningún subtipo de {TIPO_LABELS[tipo]} aplica al nombramiento vigente
          de este servidor.
        </Alert>
      )}

      <ModalFooter
        onCancel={onCancel}
        submitLabel="Continuar"
        submitDisabled={!puedeAvanzar}
        onSubmit={onContinuar}
      />
    </Stack>
  )
}
