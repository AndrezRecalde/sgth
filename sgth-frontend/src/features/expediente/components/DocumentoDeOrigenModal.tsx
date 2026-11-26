'use client'

import { Alert, Stack, TextInput } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import type { MovimientoPersonal } from '@/types/api'

const schema = z.object({
  resolucion_numero: z
    .string()
    .trim()
    .min(1, 'Indique el documento con el que se archiva este acto')
    .max(100, 'El número no puede exceder los 100 caracteres'),
})

type FormData = z.infer<typeof schema>

interface Props {
  opened: boolean
  movimiento: MovimientoPersonal
  onClose: () => void
}

/**
 * El documento de origen de un acto de autoridad, al suscribirlo (fase 4.1b).
 *
 * Lo pide `MovimientoPersonalStateService::exigirDocumentoDeOrigen()` y hasta
 * ahora no había dónde escribirlo: la asunción definitiva nace fuera del
 * formulario —la prepara el cese del Prefecto— y su cajón dice, con razón, que
 * «este registro no se corrige con el formulario de acciones de personal». El
 * resultado era que se quedaba en borrador para siempre y la institución sin
 * Prefecto en funciones, que es quien firma las acciones de todos los demás
 * (hallazgo H15 de la prueba del 2026-11-26).
 *
 * Abre con el documento del cese ya puesto —lo hereda al prepararse, decisión
 * de TH del 2026-11-26— y se puede cambiar, por si el Consejo emitió una
 * resolución aparte. Es el mismo patrón que ya usan el completar del vínculo,
 * el dictamen presupuestario y la notificación: pedir antes lo que el backend
 * va a exigir, en vez de dejar que la transición falle.
 */
export function DocumentoDeOrigenModal({ opened, movimiento, onClose }: Props) {
  return opened ? <Formulario movimiento={movimiento} onClose={onClose} /> : null
}

function Formulario({ movimiento, onClose }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations()

  const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { resolucion_numero: movimiento.resolucion_numero ?? '' },
  })

  const enviar = async (v: FormData) => {
    try {
      await transicionar.mutateAsync({
        id: Number(movimiento.id),
        estado: 'suscrita',
        resolucion_numero: v.resolucion_numero.trim(),
      })
      onClose()
    } catch (error) {
      const mensaje = erroresDeCampo(error)?.resolucion_numero
      if (mensaje) setError('resolucion_numero', { type: 'server', message: mensaje })
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Suscribir el acto de autoridad"
      size="md"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Suscribir"
      submitting={transicionar.isPending}
    >
      <Stack gap="md">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light" py={8}>
          Al acto de una autoridad electa no lo emite una autoridad nominadora:
          se archiva con el documento que lo origina. Viene el del cese del
          Prefecto; cámbielo si el Consejo Provincial emitió una resolución
          propia.
        </Alert>

        <TextInput
          label="Documento de origen N.º"
          description="La resolución del Consejo, la renuncia aceptada, la partida de defunción o el resultado del CNE."
          placeholder="Ej. RES-CP-2026-045"
          {...contained}
          {...register('resolucion_numero')}
          error={errors.resolucion_numero?.message}
        />
      </Stack>
    </FormModal>
  )
}
