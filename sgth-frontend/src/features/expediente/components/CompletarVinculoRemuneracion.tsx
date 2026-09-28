'use client'

import { Alert, NumberInput, Text } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { esLosep, remuneracionEsHeredada } from '../utils/nombramiento'
import type { CompletarVinculoFormData } from '../schemas/completarVinculo.schema'

/**
 * La remuneración con la que nace el contrato, y por qué se puede escribir o no.
 *
 * En LOSEP la fija el grupo ocupacional del puesto, así que el campo va en solo
 * lectura: escribir un monto distinto crearía una diferencia con la escala
 * vigente que nadie podría justificar después. En Código del Trabajo y Servicios
 * Profesionales se negocia en el contrato, y ahí tiene que estar abierto.
 */
export function CompletarVinculoRemuneracion({
  form,
  nombramiento,
  rmuDelPuesto,
}: {
  form: UseFormReturn<CompletarVinculoFormData>
  nombramiento: string | null
  /** R.M.U. de la escala del grupo ocupacional del puesto destino. */
  rmuDelPuesto: number | null
}) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  const derivaDelPuesto = esLosep(nombramiento)
  const rmuHeredada = remuneracionEsHeredada(nombramiento, rmuDelPuesto)

  return (
    <>
      <Controller
        name="remuneracion_propuesta"
        control={control}
        render={({ field }) => (
          <NumberInput
            label="Remuneración mensual unificada (R.M.U.)"
            description={rmuHeredada
              ? 'Fijada por el grupo ocupacional del puesto. No se edita en régimen LOSEP.'
              : derivaDelPuesto
                ? 'Este puesto no tiene grupo ocupacional asignado: ingrese el monto a mano.'
                : 'Se pacta en el contrato: este régimen no toma la remuneración del puesto.'}
            placeholder="0.00"
            min={0}
            decimalScale={2}
            readOnly={rmuHeredada}
            error={errors.remuneracion_propuesta?.message}
            value={field.value ?? ''}
            onChange={(v) => {
              const n = typeof v === 'number' ? v : parseFloat(String(v))
              field.onChange(Number.isFinite(n) ? n : undefined)
            }}
            {...contained}
          />
        )}
      />

      {!derivaDelPuesto && (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Bajo Código del Trabajo y Servicios Profesionales la remuneración es
            la negociada con el trabajador, no la del puesto.
          </Text>
        </Alert>
      )}
    </>
  )
}
