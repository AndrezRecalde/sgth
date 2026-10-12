'use client'

import { Alert, Group, Input, SegmentedControl, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { notificacionSchema, type NotificacionFormData } from '../schemas/notificacion.schema'
import type { MovimientoPersonal } from '@/types/api'

const MEDIOS = [
  { value: 'portal', label: 'Por el portal y el correo' },
  { value: 'personal', label: 'En persona' },
]

const RESULTADOS = [
  { value: 'recibida', label: 'La recibió' },
  { value: 'negada', label: 'Se negó a recibirla' },
]

/** Del campo que el backend nombra al de este formulario. */
const CAMPO_DEL_BACKEND: Record<string, keyof NotificacionFormData> = {
  notificacion_medio: 'medio',
  notificacion_resultado: 'resultado',
  notificacion_razon: 'razon',
  notificacion_testigo_nombre: 'testigo_nombre',
  notificacion_testigo_cedula: 'testigo_cedula',
}

interface Props {
  opened: boolean
  movimiento: MovimientoPersonal
  onClose: () => void
}

/**
 * Notificar la acción al servidor (Reglamento a la LOSEP, Art. 22; diseño,
 * 6.4; TH 26; fase 6.3a). Antes era un botón directo que solo guardaba quién y
 * cuándo: no le llegaba nada al servidor y la negativa no dejaba constancia.
 */
export function NotificarAccionModal({ opened, movimiento, onClose }: Props) {
  return opened ? <Formulario movimiento={movimiento} onClose={onClose} /> : null
}

function Formulario({ movimiento, onClose }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations()

  const { control, register, handleSubmit, setValue, setError, formState: { errors } } = useForm<NotificacionFormData>({
    resolver: zodResolver(notificacionSchema),
    defaultValues: { medio: 'portal', resultado: null, razon: '', testigo_nombre: '', testigo_cedula: '' },
  })
  const [medio, resultado] = useWatch({ control, name: ['medio', 'resultado'] })

  const enviar = async (v: NotificacionFormData) => {
    const enPersona = v.medio === 'personal'
    const negada = enPersona && v.resultado === 'negada'

    try {
      await transicionar.mutateAsync({
        id: Number(movimiento.id),
        estado: 'notificada',
        notificacion_medio: v.medio,
        notificacion_resultado: enPersona ? v.resultado ?? null : null,
        notificacion_razon: negada ? v.razon?.trim() || null : null,
        notificacion_testigo_nombre: negada ? v.testigo_nombre?.trim() || null : null,
        notificacion_testigo_cedula: negada ? v.testigo_cedula?.trim() || null : null,
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const [campo, mensaje] of Object.entries(campos ?? {})) {
        const propio = CAMPO_DEL_BACKEND[campo]
        if (propio) setError(propio, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={`Notificar ${movimiento.codigo_registro ?? 'la acción'}`}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Notificar"
      submitting={transicionar.isPending}
    >
      <Stack gap="sm">
        {/* Sin el patrón contained: un SegmentedControl no tiene casilla. */}
        <Input.Wrapper label="Cómo se notifica">
          <Controller
            name="medio"
            control={control}
            render={({ field }) => (
              <SegmentedControl
                data={MEDIOS}
                fullWidth
                value={field.value}
                onChange={(v) => {
                  field.onChange(v)
                  setValue('resultado', null)
                }}
              />
            )}
          />
        </Input.Wrapper>

        {medio === 'portal' ? (
          <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
            El documento aparece en «Mis documentos laborales» del servidor y le llega al correo
            institucional —o al personal, si no tiene cuenta— con el PDF adjunto. Él acusa recibo
            desde el portal.
          </Alert>
        ) : (
          <>
            <Input.Wrapper label="Qué pasó" error={errors.resultado?.message}>
              <Controller
                name="resultado"
                control={control}
                render={({ field }) => (
                  <SegmentedControl
                    data={RESULTADOS}
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(v) => field.onChange(v)}
                  />
                )}
              />
            </Input.Wrapper>

            {resultado === 'recibida' && (
              <Text size="sm" c="dimmed">
                Su «Recibí» en el documento firmado es el acuse: queda con la fecha de hoy.
              </Text>
            )}

            {resultado === 'negada' && (
              <>
                <Textarea
                  label="Razón de la negativa"
                  withAsterisk
                  autosize
                  minRows={2}
                  error={errors.razon?.message}
                  {...contained}
                  {...register('razon')}
                />
                <Group grow align="flex-start">
                  <TextInput
                    label="Testigo"
                    placeholder="Nombre completo"
                    withAsterisk
                    error={errors.testigo_nombre?.message}
                    {...contained}
                    {...register('testigo_nombre')}
                  />
                  <TextInput
                    label="Cédula del testigo"
                    withAsterisk
                    error={errors.testigo_cedula?.message}
                    {...contained}
                    {...register('testigo_cedula')}
                  />
                </Group>
              </>
            )}
          </>
        )}
      </Stack>
    </FormModal>
  )
}
