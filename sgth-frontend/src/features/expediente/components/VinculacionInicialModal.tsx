'use client'

import { useState } from 'react'
import { Alert, Button, Group, Stepper } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import { FormProvider, useForm, Controller, type FieldErrors } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconBriefcase, IconInfoCircle, IconPhone, IconUser } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import { VinculacionInicialFormVinculo } from './VinculacionInicialFormVinculo'
import { useVinculacionInicial } from '../hooks/useVinculacionInicial'
import {
  vinculacionInicialSchema, type VinculacionInicialFormData,
} from '../schemas/vinculacionInicial.schema'
import { toDateValue, fromDateValueOrNull } from '@/lib/fecha'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import {
  CAMPOS_VINCULACION, PASO_PERSONAL, VINCULACION_EN_BLANCO as BLANCO, pasoDeVinculacion,
} from '../utils/vinculacionInicialForm'

interface Props {
  opened: boolean
  onClose: () => void
}

/**
 * Carga inicial de un servidor que ya estaba vinculado antes del sistema:
 * ficha y contrato vigente en un solo acto, sin Acción de Personal.
 *
 * Reutiliza los pasos del alta ordinaria (datos personales y contacto) y añade
 * el del vínculo, que es lo que distingue esta vía.
 */
export function VinculacionInicialModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const registrar = useVinculacionInicial()
  const [paso, setPaso] = useState(0)

  const form = useForm<VinculacionInicialFormData>({
    resolver: zodResolver(vinculacionInicialSchema),
    defaultValues: BLANCO,
  })

  const cerrar = () => {
    form.reset(BLANCO)
    setPaso(0)
    onClose()
  }

  const avanzar = async () => {
    if (paso === 0) {
      const ok = await form.trigger(PASO_PERSONAL)
      if (!ok) return
    }
    setPaso((p) => Math.min(p + 1, 2))
  }

  /** Un campo inválido puede estar en otro paso: se vuelve a él para que se vea. */
  const irAlError = (campos: string[]) => {
    const p = pasoDeVinculacion(campos)
    if (p !== null) setPaso(p)
  }

  const enviar = (valores: VinculacionInicialFormData) => {
    registrar.mutateAsync(valores).then(cerrar).catch((e) => {
      // Cédula repetida, puesto inexistente…: cada uno a su campo y a su paso.
      erroresAlFormulario(e, form.setError, CAMPOS_VINCULACION, 'No se pudo registrar la vinculación inicial')
      irAlError(Object.keys(erroresDeCampo(e) ?? {}))
    })
  }

  return (
    <SgthModal
      opened={opened}
      onClose={cerrar}
      title="Vinculación inicial — servidor ya vinculado"
      size="xl"
    >
      <Stepper active={paso} size="sm" mb="lg" allowNextStepsSelect={false}>
        <Stepper.Step label="Datos personales" icon={<IconUser size={16} />} />
        <Stepper.Step label="Contacto y antigüedad" icon={<IconPhone size={16} />} />
        <Stepper.Step label="Vínculo vigente" icon={<IconBriefcase size={16} />} />
      </Stepper>

      <FormProvider {...form}>
        <form
          onSubmit={form.handleSubmit(enviar, (e: FieldErrors) => irAlError(Object.keys(e)))}
          noValidate
        >
          {paso === 0 && <ServidorFormPersonal />}

          {paso === 1 && (
            <>
              <ServidorFormContacto />

              <Alert
                variant="light"
                color="ocean"
                icon={<IconInfoCircle size={16} />}
                mt="md"
                mb="sm"
              >
                De la fecha de ingreso sale la antigüedad, que habilita las
                comisiones de servicios y la jubilación. Si la persona tuvo
                vínculos anteriores, registre aquí el primero de todos.
              </Alert>

              <Group grow>
                <Controller
                  name="fecha_ingreso_institucion"
                  control={form.control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Ingreso a la institución"
                      description="Si se deja vacío, se toma la del contrato vigente."
                      valueFormat="DD/MM/YYYY"
                      maxDate={new Date()}
                      clearable
                      value={toDateValue(field.value)}
                      onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                      error={form.formState.errors.fecha_ingreso_institucion?.message}
                      {...contained}
                    />
                  )}
                />
                <Controller
                  name="fecha_ingreso_sector_publico"
                  control={form.control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Ingreso al sector público"
                      description="Opcional."
                      valueFormat="DD/MM/YYYY"
                      maxDate={new Date()}
                      clearable
                      value={toDateValue(field.value)}
                      onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                      {...contained}
                    />
                  )}
                />
              </Group>
            </>
          )}

          {paso === 2 && <VinculacionInicialFormVinculo />}

          <ModalFooter
            onCancel={cerrar}
            leftSection={paso > 0 && (
              <Button variant="default" onClick={() => setPaso((p) => p - 1)}>
                Atrás
              </Button>
            )}
            // Sin `onSubmit` el botón pasa a enviar el formulario: solo en el último paso.
            onSubmit={paso < 2 ? avanzar : undefined}
            submitLabel={paso < 2 ? 'Siguiente' : 'Registrar servidor y vínculo'}
            submitting={registrar.isPending}
          />
        </form>
      </FormProvider>
    </SgthModal>
  )
}
