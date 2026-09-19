'use client'

import { Stepper, Button } from '@mantine/core'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useForm, FormProvider, type FieldErrors } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconPhone, IconBriefcase } from '@tabler/icons-react'
import { useState } from 'react'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useServidorMutations } from '../hooks/useServidorMutations'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import { ServidorFormLaboral } from './ServidorFormLaboral'
import {
  servidorBasicoSchema,
  type ServidorBasicoFormData,
} from '../schemas/servidorBasico.schema'
import {
  servidorLaboralSchema,
  type ServidorLaboralFormData,
} from '../schemas/servidorLaboral.schema'
import {
  BLANK_FORM_VALUES,
  CAMPOS_POR_PASO,
  mapServidorToFormValues,
  mapServidorToLaboralValues,
  pasoConError,
} from '../utils/servidorFormValues'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  servidor?: ServidorConRelaciones | null
  /**
   * Se dispara con la ficha recién creada. Registrar la ficha es solo la
   * primera mitad del alta: sin vínculo la persona existe pero no está
   * contratada, y quien la creó es quien mejor puede continuar en ese momento.
   */
  onCreado?: (servidor: ServidorConRelaciones) => void
}

const esCampoBasico = (c: string): c is keyof ServidorBasicoFormData =>
  c in BLANK_FORM_VALUES
const esCampoLaboral = (c: string): c is keyof ServidorLaboralFormData =>
  c in mapServidorToLaboralValues(null)

// El padre lo monta con `key` por servidor: los valores iniciales bastan y no
// hace falta reiniciar el formulario cuando cambia la prop.
export function ServidorModal({ opened, onClose, servidor, onCreado }: Props) {
  const { crear, editar } = useServidorMutations()
  const isEditing         = !!servidor
  const totalSteps        = isEditing ? 3 : 2

  const [step, setStep] = useState(0)

  const form = useForm<ServidorBasicoFormData>({
    resolver: zodResolver(servidorBasicoSchema),
    defaultValues: servidor ? mapServidorToFormValues(servidor) : BLANK_FORM_VALUES,
  })

  const laboralForm = useForm<ServidorLaboralFormData>({
    resolver: zodResolver(servidorLaboralSchema),
    defaultValues: mapServidorToLaboralValues(servidor),
  })

  const handleClose = () => {
    form.reset(BLANK_FORM_VALUES)
    laboralForm.reset(mapServidorToLaboralValues(null))
    setStep(0)
    onClose()
  }

  const handleNext = async () => {
    const campos = CAMPOS_POR_PASO[step].filter(esCampoBasico)
    if (await form.trigger(campos)) setStep((s) => Math.min(s + 1, totalSteps - 1))
  }

  const handleBack = () => setStep((s) => Math.max(s - 1, 0))

  /** Un campo inválido puede estar en otro paso: se vuelve a él para que se vea. */
  const irAlError = (errors: FieldErrors) => {
    const paso = pasoConError(Object.keys(errors))
    if (paso !== null) setStep(paso)
  }

  /** Los errores de validación del backend (cédula duplicada…) caen en su campo. */
  const mostrarErroresDelServidor = (error: unknown) => {
    const campos = erroresDeCampo(error)
    if (!campos) return // el hook ya lo notificó
    const sinCampo: string[] = []
    for (const [campo, mensaje] of Object.entries(campos)) {
      if (esCampoBasico(campo)) form.setError(campo, { message: mensaje })
      else if (esCampoLaboral(campo)) laboralForm.setError(campo, { message: mensaje })
      else sinCampo.push(mensaje)
    }
    if (sinCampo.length) notificar.error('No se pudo guardar el expediente', sinCampo.join(' '))
    const paso = pasoConError(Object.keys(campos))
    if (paso !== null) setStep(paso)
  }

  const onSubmit = async (values: ServidorBasicoFormData) => {
    try {
      if (isEditing) {
        if (!(await laboralForm.trigger())) return setStep(2)
        // Un solo envío con la ficha y lo laboral. Antes eran dos PUT: dos
        // notificaciones, un guardado a medias si fallaba el segundo, y lo
        // laboral solo se enviaba si había fecha de ingreso al GAD.
        await editar.mutateAsync({
          id: Number(servidor.id),
          data: { ...values, ...laboralForm.getValues() },
        })
        handleClose()
        return
      }
      const creado = await crear.mutateAsync(values)
      handleClose()
      // El vínculo se registra aparte: se ofrece continuar ahí mismo en vez
      // de dejar la ficha a medias esperando que alguien la encuentre.
      if (creado) onCreado?.(creado)
    } catch (error) {
      mostrarErroresDelServidor(error)
    }
  }

  const isPending = crear.isPending || editar.isPending
  const esUltimoPaso = step === totalSteps - 1

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar datos del servidor' : 'Registrar ficha del servidor'}
      size="xl"
    >
      <Stepper active={step} size="sm" mb="lg" allowNextStepsSelect={false}>
        <Stepper.Step label="Datos personales" icon={<IconUser size={16} />} />
        <Stepper.Step label="Contacto" icon={<IconPhone size={16} />} />
        {isEditing && (
          <Stepper.Step label="Laboral" icon={<IconBriefcase size={16} />} />
        )}
      </Stepper>

      <form onSubmit={form.handleSubmit(onSubmit, irAlError)} noValidate>
        {step === 0 && <ServidorFormPersonal form={form} />}
        {step === 1 && <ServidorFormContacto form={form} />}
        {step === 2 && isEditing && (
          <FormProvider {...laboralForm}>
            <ServidorFormLaboral
              tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
              fechaIngresoInstitucion={servidor.fecha_ingreso_institucion}
            />
          </FormProvider>
        )}

        <ModalFooter
          onCancel={handleClose}
          leftSection={step > 0 && (
            <Button variant="default" onClick={handleBack}>
              Atrás
            </Button>
          )}
          // Sin `onSubmit` el botón pasa a enviar el formulario: solo en el último paso.
          onSubmit={esUltimoPaso ? undefined : handleNext}
          submitLabel={esUltimoPaso
            ? (isEditing ? 'Actualizar' : 'Guardar ficha y continuar')
            : 'Siguiente'}
          submitting={isPending}
        />
      </form>
    </SgthModal>
  )
}
