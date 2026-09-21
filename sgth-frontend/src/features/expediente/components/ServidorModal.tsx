'use client'

import { Stepper, Button } from '@mantine/core'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useForm, FormProvider, type FieldErrors } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconPhone } from '@tabler/icons-react'
import { useState } from 'react'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useServidorMutations } from '../hooks/useServidorMutations'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import {
  servidorBasicoSchema,
  type ServidorBasicoFormData,
} from '../schemas/servidorBasico.schema'
import {
  BLANK_FORM_VALUES,
  CAMPOS_POR_PASO,
  pasoConError,
} from '../utils/servidorFormValues'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  /**
   * Se dispara con la ficha recién creada. Registrar la ficha es solo la
   * primera mitad del alta: sin vínculo la persona existe pero no está
   * contratada, y quien la creó es quien mejor puede continuar en ese momento.
   */
  onCreado?: (servidor: ServidorConRelaciones) => void
}

const esCampo = (c: string): c is keyof ServidorBasicoFormData =>
  c in BLANK_FORM_VALUES

/**
 * Alta de una ficha, en dos pasos. Editar una ficha ya creada es otra cosa y
 * vive en `ServidorEditarModal`: ahí nada es obligatorio y solo viaja lo que
 * se tocó, porque las fichas cargadas antes del sistema vienen incompletas.
 */
export function ServidorModal({ opened, onClose, onCreado }: Props) {
  const { crear } = useServidorMutations()
  const [step, setStep] = useState(0)

  const form = useForm<ServidorBasicoFormData>({
    resolver: zodResolver(servidorBasicoSchema),
    defaultValues: BLANK_FORM_VALUES,
  })

  const handleClose = () => {
    form.reset(BLANK_FORM_VALUES)
    setStep(0)
    onClose()
  }

  const handleNext = async () => {
    const campos = CAMPOS_POR_PASO[step].filter(esCampo)
    if (await form.trigger(campos)) setStep(1)
  }

  /** Un campo inválido puede estar en otro paso: se vuelve a él para que se vea. */
  const irAlError = (errors: FieldErrors) => {
    const paso = pasoConError(Object.keys(errors))
    if (paso !== null) setStep(paso)
  }

  const onSubmit = async (values: ServidorBasicoFormData) => {
    try {
      const creado = await crear.mutateAsync(values)
      handleClose()
      // El vínculo se registra aparte: se ofrece continuar ahí mismo en vez
      // de dejar la ficha a medias esperando que alguien la encuentre.
      if (creado) onCreado?.(creado)
    } catch (error) {
      // Los errores de validación del backend (cédula duplicada…) a su campo.
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó
      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (esCampo(campo)) form.setError(campo, { message: mensaje })
        else sinCampo.push(mensaje)
      }
      if (sinCampo.length) notificar.error('No se pudo guardar el expediente', sinCampo.join(' '))
      const paso = pasoConError(Object.keys(campos))
      if (paso !== null) setStep(paso)
    }
  }

  const esUltimoPaso = step === 1

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="Registrar ficha del servidor"
      size="xl"
    >
      <Stepper active={step} size="sm" mb="lg" allowNextStepsSelect={false}>
        <Stepper.Step label="Datos personales" icon={<IconUser size={16} />} />
        <Stepper.Step label="Contacto" icon={<IconPhone size={16} />} />
      </Stepper>

      <form onSubmit={form.handleSubmit(onSubmit, irAlError)} noValidate>
        <FormProvider {...form}>
          {step === 0 && <ServidorFormPersonal />}
          {step === 1 && <ServidorFormContacto />}
        </FormProvider>

        <ModalFooter
          onCancel={handleClose}
          leftSection={step > 0 && (
            <Button variant="default" onClick={() => setStep(0)}>
              Atrás
            </Button>
          )}
          // Sin `onSubmit` el botón pasa a enviar el formulario: solo en el último paso.
          onSubmit={esUltimoPaso ? undefined : handleNext}
          submitLabel={esUltimoPaso ? 'Guardar ficha y continuar' : 'Siguiente'}
          submitting={crear.isPending}
        />
      </form>
    </SgthModal>
  )
}
