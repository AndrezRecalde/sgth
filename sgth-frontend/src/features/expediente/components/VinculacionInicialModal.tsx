'use client'

import { useState } from 'react'
import { Alert, Button, Group, Modal, Stepper } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { FormProvider, useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconBriefcase, IconInfoCircle, IconPhone, IconUser } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import { VinculacionInicialFormVinculo } from './VinculacionInicialFormVinculo'
import { useVinculacionInicial } from '../hooks/useVinculacionInicial'
import {
  vinculacionInicialSchema, type VinculacionInicialFormData,
} from '../schemas/vinculacionInicial.schema'

interface Props {
  opened: boolean
  onClose: () => void
}

const BLANCO = {
  nombre: '', segundo_nombre: '', apellido: '', segundo_apellido: '', cedula: '',
  fecha_nacimiento: '',
  genero: 'masculino',
  estado_civil: 'soltero',
  tipo_sangre: null,
  es_extranjero: false,
  provincia_nacimiento_id: null,
  canton_nacimiento_id: null,
  nacionalidad: '', pais_origen: '',
  numero_papeleta_votacion: '', pasaporte_numero: '',
  tiene_discapacidad: false,
  tiene_enfermedad_catastrofica: false,
  telefono_celular: '', telefono_convencional: '',
  correo_personal: '', direccion_domicilio: '',
  fecha_ingreso_institucion: null,
  fecha_ingreso_sector_publico: null,
  vinculo: {
    tipo_nombramiento: 'nombramiento_permanente',
    unidad_administrativa_id: undefined,
    puesto_id: undefined,
    fecha_inicio: '',
    fecha_fin: null,
    remuneracion: undefined,
    numero_contrato: '',
    resolucion_numero: '',
    puede_marcar: true,
  },
} as unknown as VinculacionInicialFormData

/** Campos que deben validarse antes de dejar avanzar de paso. */
const PASO_PERSONAL = [
  'nombre', 'apellido', 'cedula', 'fecha_nacimiento', 'genero', 'estado_civil',
  'es_extranjero', 'provincia_nacimiento_id', 'canton_nacimiento_id',
  'nacionalidad', 'pais_origen',
  'tiene_discapacidad', 'tiene_enfermedad_catastrofica',
] as const

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('T')[0].split('-').map(Number)
  return new Date(y, m - 1, d)
}
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

/**
 * Carga inicial de un servidor que ya estaba vinculado antes del sistema:
 * ficha y contrato vigente en un solo acto, sin Acción de Personal.
 *
 * Reutiliza los pasos del alta ordinaria (datos personales y contacto) y añade
 * el del vínculo, que es lo que distingue esta vía.
 */
export function VinculacionInicialModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
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
      const ok = await form.trigger(PASO_PERSONAL as unknown as (keyof VinculacionInicialFormData)[])
      if (!ok) return
    }
    setPaso((p) => Math.min(p + 1, 2))
  }

  const enviar = (valores: VinculacionInicialFormData) => {
    registrar.mutateAsync(valores).then(cerrar).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={cerrar}
      title="Vinculación inicial — servidor ya vinculado"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stepper active={paso} size="sm" color="emerald" mb="lg" allowNextStepsSelect={false}>
        <Stepper.Step label="Datos personales" icon={<IconUser size={16} />} />
        <Stepper.Step label="Contacto y antigüedad" icon={<IconPhone size={16} />} />
        <Stepper.Step label="Vínculo vigente" icon={<IconBriefcase size={16} />} />
      </Stepper>

      <FormProvider {...form}>
        <form onSubmit={form.handleSubmit(enviar)}>
          {paso === 0 && <ServidorFormPersonal form={form as never} />}

          {paso === 1 && (
            <>
              <ServidorFormContacto form={form as never} />

              <Alert
                variant="light"
                color="blue"
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
                      value={toDate(field.value)}
                      onChange={(d) => field.onChange(fromDate(d as Date | null))}
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
                      value={toDate(field.value)}
                      onChange={(d) => field.onChange(fromDate(d as Date | null))}
                      {...contained}
                    />
                  )}
                />
              </Group>
            </>
          )}

          {paso === 2 && <VinculacionInicialFormVinculo />}

          <Group justify="space-between" mt="xl">
            <Button variant="default" onClick={cerrar}>Cancelar</Button>
            <Group>
              {paso > 0 && (
                <Button variant="default" onClick={() => setPaso((p) => p - 1)}>
                  Atrás
                </Button>
              )}
              {paso < 2 ? (
                <Button color="emerald" variant="light" onClick={avanzar}>
                  Siguiente
                </Button>
              ) : (
                <Button type="submit" color="emerald" loading={registrar.isPending}>
                  Registrar servidor y vínculo
                </Button>
              )}
            </Group>
          </Group>
        </form>
      </FormProvider>
    </Modal>
  )
}
