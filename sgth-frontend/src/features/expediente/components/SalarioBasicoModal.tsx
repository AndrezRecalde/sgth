'use client'

import { Grid, NumberInput, Stack, Text, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useGuardarSalarioBasico } from '../hooks/useContratoColectivo'
import { salarioBasicoSchema, type SalarioBasicoFormData } from '../schemas/contratoColectivo.schema'
import type { SalarioBasico } from '../services/contratoColectivoService'

interface Props {
  opened: boolean
  /** El que se corrige; sin él, el de un año nuevo. */
  salario: SalarioBasico | null
  onClose: () => void
}

/**
 * El salario básico unificado de un año (fase 4.6). Lo usan los topes del
 * Mandato 2 en la liquidación de los obreros; si el año ya está registrado, se
 * corrige.
 */
export function SalarioBasicoModal({ opened, salario, onClose }: Props) {
  return opened ? <Formulario key={salario?.id ?? 'nuevo'} salario={salario} onClose={onClose} /> : null
}

function Formulario({ salario, onClose }: { salario: SalarioBasico | null; onClose: () => void }) {
  const contained = useContainedInput()
  const guardar = useGuardarSalarioBasico()

  const {
    control, register, handleSubmit, setError, formState: { errors },
  } = useForm<SalarioBasicoFormData>({
    resolver: zodResolver(salarioBasicoSchema),
    defaultValues: {
      anio: salario?.anio ?? new Date().getFullYear(),
      monto: salario ? Number(salario.monto) : undefined,
      acuerdo: salario?.acuerdo ?? '',
    },
  })

  const enviar = async (v: SalarioBasicoFormData) => {
    try {
      await guardar.mutateAsync({ ...v, acuerdo: v.acuerdo?.trim() || null })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of ['anio', 'monto', 'acuerdo'] as const) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={salario ? `Corregir el salario básico de ${salario.anio}` : 'Registrar salario básico'}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Guardar"
      submitting={guardar.isPending}
      size="md"
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          El que fija el Ministerio del Trabajo para el año. Si el año ya está registrado, se corrige.
        </Text>
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="anio"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Año"
                  withAsterisk
                  min={2000}
                  max={2100}
                  hideControls
                  allowDecimal={false}
                  disabled={!!salario}
                  error={errors.anio?.message}
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="monto"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Salario básico"
                  withAsterisk
                  min={0}
                  decimalScale={2}
                  hideControls
                  error={errors.monto?.message}
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Grid.Col>
        </Grid>
        <TextInput
          label="Acuerdo ministerial"
          placeholder="Opcional"
          error={errors.acuerdo?.message}
          {...contained}
          {...register('acuerdo')}
        />
      </Stack>
    </FormModal>
  )
}
