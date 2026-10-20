'use client'

import { Alert, FileInput, Grid, NumberInput, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha, fromDateValue, toDateValue } from '@/lib/fecha'
import { useGuardarActaFiniquito } from '../hooks/useTerminacionesObreros'
import { actaFiniquitoSchema, type ActaFiniquitoFormData } from '../schemas/actaFiniquito.schema'
import { nombreDelObrero, type TerminacionObrero } from '../services/terminacionObreroService'

interface Props {
  terminacion: TerminacionObrero | null
  onClose: () => void
}

/**
 * El acta de finiquito de un obrero (fase 4.5). Se genera y se legaliza en el
 * sistema del Ministerio del Trabajo: aquí se registra su número, su fecha y lo
 * pagado, y el PDF si se tiene.
 */
export function ActaFiniquitoModal({ terminacion, onClose }: Props) {
  // Se monta al abrir: arranca con el acta de esa terminación sin resetear el
  // formulario desde un efecto.
  return terminacion ? <FormularioActa key={terminacion.movimiento_id} terminacion={terminacion} onClose={onClose} /> : null
}

function FormularioActa({ terminacion, onClose }: { terminacion: TerminacionObrero; onClose: () => void }) {
  const contained = useContainedInput()
  const guardar = useGuardarActaFiniquito()
  const acta = terminacion.acta

  const {
    control, register, handleSubmit, setError, formState: { errors },
  } = useForm<ActaFiniquitoFormData>({
    resolver: zodResolver(actaFiniquitoSchema),
    defaultValues: {
      numero: acta?.numero ?? '',
      fecha: acta?.fecha?.split('T')[0] ?? fromDateValue(new Date()),
      valor_pagado: acta ? Number(acta.valor_pagado) : undefined,
      observacion: acta?.observacion ?? '',
      archivo: null,
    },
  })

  const enviar = async (v: ActaFiniquitoFormData) => {
    try {
      await guardar.mutateAsync({
        movimientoId: terminacion.movimiento_id,
        data: { ...v, observacion: v.observacion?.trim() || null },
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of ['numero', 'fecha', 'valor_pagado', 'observacion', 'archivo'] as const) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={acta ? 'Corregir acta de finiquito' : 'Registrar acta de finiquito'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={acta ? 'Guardar cambios' : 'Registrar acta'}
      submitting={guardar.isPending}
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          {nombreDelObrero(terminacion.servidor)} —{terminacion.causal_etiqueta}, desde el{' '}
          {formatFecha(terminacion.fecha_efectiva)} ({terminacion.codigo_registro})
        </Text>

        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          El acta se genera y se legaliza en el sistema del Ministerio del Trabajo:
          registre aquí su número, su fecha y lo que se pagó.
        </Alert>

        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Acta N.º"
              withAsterisk
              error={errors.numero?.message}
              {...contained}
              {...register('numero')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="fecha"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha del acta"
                  withAsterisk
                  valueFormat="DD/MM/YYYY"
                  maxDate={new Date()}
                  error={errors.fecha?.message}
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(v) => field.onChange(fromDateValue(v))}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="valor_pagado"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Valor pagado"
                  description="El que dice el acta: la liquidación la calcula Financiero."
                  withAsterisk
                  min={0}
                  decimalScale={2}
                  hideControls
                  error={errors.valor_pagado?.message}
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="archivo"
              control={control}
              render={({ field }) => (
                <FileInput
                  label="PDF del acta"
                  placeholder={acta?.tiene_documento ? 'Reemplazar el adjunto' : 'Opcional'}
                  accept="application/pdf"
                  clearable
                  error={errors.archivo?.message}
                  {...contained}
                  value={field.value ?? null}
                  onChange={field.onChange}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        <Textarea
          label="Observación"
          placeholder="Opcional"
          autosize
          minRows={2}
          error={errors.observacion?.message}
          {...contained}
          {...register('observacion')}
        />
      </Stack>
    </FormModal>
  )
}
