'use client'

import { Modal, Stepper, Button, Group } from '@mantine/core'
import { useForm, FormProvider } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconPhone, IconBriefcase } from '@tabler/icons-react'
import { useEffect, useState } from 'react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
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
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  servidor?: ServidorConRelaciones | null
}

const BLANK_FORM_VALUES: ServidorBasicoFormData = {
  nombre:           '',
  segundo_nombre:   '',
  apellido:         '',
  segundo_apellido: '',
  cedula:           '',
  fecha_nacimiento: '',
  genero:           'masculino',
  estado_civil:     'soltero',
  tipo_sangre:             null,
  es_extranjero:           false,
  provincia_nacimiento_id: null,
  canton_nacimiento_id:    null,
  nacionalidad:            '',
  pais_origen:             '',
  numero_papeleta_votacion: '',
  pasaporte_numero:        '',
  tiene_discapacidad:            false,
  tiene_enfermedad_catastrofica: false,
  telefono_celular:      '',
  telefono_convencional: '',
  correo_personal:       '',
  direccion_domicilio:   '',
}

const BLANK_LABORAL_VALUES: ServidorLaboralFormData = {
  fecha_ingreso_institucion:    '',
  fecha_ingreso_sector_publico: null,
  fecha_nombramiento:           null,
  numero_contrato:              null,
}

function mapServidorToFormValues(servidor: ServidorConRelaciones): ServidorBasicoFormData {
  return {
    nombre:           servidor.nombre  ?? '',
    segundo_nombre:   servidor.segundo_nombre  ?? '',
    apellido:         servidor.apellido ?? '',
    segundo_apellido: servidor.segundo_apellido ?? '',
    cedula:           servidor.cedula ?? '',
    fecha_nacimiento: servidor.fecha_nacimiento
      ? servidor.fecha_nacimiento.split('T')[0] : '',
    genero:           (servidor.genero as ServidorBasicoFormData['genero'])
      ?? 'masculino',
    estado_civil: (servidor.estado_civil as ServidorBasicoFormData['estado_civil'])
      ?? 'soltero',
    tipo_sangre:             (servidor.tipo_sangre as ServidorBasicoFormData['tipo_sangre']) ?? null,
    es_extranjero:           servidor.es_extranjero ?? false,
    provincia_nacimiento_id: servidor.provincia_nacimiento_id ?? null,
    canton_nacimiento_id:    servidor.canton_nacimiento_id ?? null,
    nacionalidad:            servidor.nacionalidad ?? '',
    pais_origen:             servidor.pais_origen ?? '',
    numero_papeleta_votacion: servidor.numero_papeleta_votacion ?? '',
    pasaporte_numero:        servidor.pasaporte_numero ?? '',
    tiene_discapacidad:            servidor.tiene_discapacidad ?? false,
    tiene_enfermedad_catastrofica: servidor.tiene_enfermedad_catastrofica ?? false,
    telefono_celular:      servidor.telefono_celular ?? '',
    telefono_convencional: servidor.telefono_convencional ?? '',
    correo_personal:       servidor.correo_personal ?? '',
    direccion_domicilio:   servidor.direccion_domicilio ?? '',
  }
}

const PERSONAL_FIELDS = [
  'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'cedula',
  'fecha_nacimiento', 'genero', 'estado_civil', 'tipo_sangre', 'es_extranjero',
  'provincia_nacimiento_id', 'canton_nacimiento_id', 'nacionalidad', 'pais_origen',
  'tiene_discapacidad', 'tiene_enfermedad_catastrofica',
] as const satisfies readonly (keyof ServidorBasicoFormData)[]

function mapServidorToLaboralValues(servidor: ServidorConRelaciones): ServidorLaboralFormData {
  return {
    fecha_ingreso_institucion: servidor.fecha_ingreso_institucion
      ? servidor.fecha_ingreso_institucion.split('T')[0] : '',
    fecha_ingreso_sector_publico: servidor.fecha_ingreso_sector_publico
      ? servidor.fecha_ingreso_sector_publico.split('T')[0] : null,
    fecha_nombramiento: servidor.fecha_nombramiento
      ? servidor.fecha_nombramiento.split('T')[0] : null,
    numero_contrato: servidor.numero_contrato ?? null,
  }
}

export function ServidorModal({ opened, onClose, servidor }: Props) {
  const { isMobile }      = useMobileBreakpoint()
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
    defaultValues: servidor ? mapServidorToLaboralValues(servidor) : BLANK_LABORAL_VALUES,
  })

  useEffect(() => {
    if (servidor) {
      form.reset(mapServidorToFormValues(servidor))
      laboralForm.reset(mapServidorToLaboralValues(servidor))
    } else {
      form.reset(BLANK_FORM_VALUES)
      laboralForm.reset(BLANK_LABORAL_VALUES)
    }
  }, [servidor, form, laboralForm])

  const handleClose = () => {
    form.reset(BLANK_FORM_VALUES)
    laboralForm.reset(BLANK_LABORAL_VALUES)
    setStep(0)
    onClose()
  }

  const handleNext = async () => {
    const valid = await form.trigger(PERSONAL_FIELDS)
    if (valid) setStep((s) => Math.min(s + 1, totalSteps - 1))
  }

  const handleBack = () => setStep((s) => Math.max(s - 1, 0))

  const onSubmit = async (values: ServidorBasicoFormData) => {
    try {
      if (isEditing) {
        await editar.mutateAsync({
          id: Number(servidor!.id),
          data: values as never,
        })
        // Guardar datos laborales si hay fecha ingreso
        const laboralValues = laboralForm.getValues()
        if (laboralValues.fecha_ingreso_institucion) {
          await editar.mutateAsync({
            id: Number(servidor!.id),
            data: laboralValues as never,
          })
        }
      } else {
        await crear.mutateAsync(values as never)
      }
      handleClose()
    } catch {
      // error manejado por el hook
    }
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar datos del servidor' : 'Registrar servidor'}
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stepper active={step} size="sm" color="emerald" mb="lg" allowNextStepsSelect={false}>
        <Stepper.Step label="Datos personales" icon={<IconUser size={16} />} />
        <Stepper.Step label="Contacto" icon={<IconPhone size={16} />} />
        {isEditing && (
          <Stepper.Step label="Laboral" icon={<IconBriefcase size={16} />} />
        )}
      </Stepper>

      <form noValidate onSubmit={form.handleSubmit(onSubmit)}>
        {step === 0 && <ServidorFormPersonal form={form} />}
        {step === 1 && <ServidorFormContacto form={form} />}
        {step === 2 && isEditing && (
          <FormProvider {...laboralForm}>
            <ServidorFormLaboral
              tipoNombramiento={servidor?.contrato_vigente?.tipo_nombramiento}
            />
          </FormProvider>
        )}

        <Group justify="space-between" mt="xl">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Group>
            {step > 0 && (
              <Button variant="default" onClick={handleBack}>
                Atrás
              </Button>
            )}
            {step < totalSteps - 1 ? (
              <Button type="button" color="emerald" variant="light" onClick={handleNext}>
                Siguiente
              </Button>
            ) : (
              <Button
                type="submit"
                loading={isPending}
                color="emerald"
                variant="light"
              >
                {isEditing ? 'Actualizar' : 'Registrar servidor'}
              </Button>
            )}
          </Group>
        </Group>
      </form>
    </Modal>
  )
}
