'use client'

import { Alert, Grid, Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { Controller, FormProvider, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ModalFooter, SectionCard, SgthModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { toDateValue, fromDateValueOrNull } from '@/lib/fecha'
import { useServidorMutations } from '../hooks/useServidorMutations'
import {
  camposQueFaltan,
  servidorEdicionSchema,
  type ServidorEdicionFormData,
} from '../schemas/servidorEdicion.schema'
import { valoresDeEdicion } from '../utils/servidorFormValues'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor: ServidorConRelaciones
}

const esCampo = (c: string): c is keyof ServidorEdicionFormData =>
  c in servidorEdicionSchema.shape

/**
 * Editar una ficha ya creada: un formulario con secciones, no un asistente.
 *
 * Solo viaja lo que se tocó, así que corregir un teléfono no obliga a
 * completar la fecha de nacimiento ni el cantón de una ficha vieja. Lo que
 * falte se avisa, no se bloquea.
 *
 * El padre lo monta con `key` por servidor.
 */
export function ServidorEditarModal({ opened, onClose, servidor }: Props) {
  const contained = useContainedInput()
  const { editar } = useServidorMutations()

  const form = useForm<ServidorEdicionFormData>({
    resolver: zodResolver(servidorEdicionSchema),
    defaultValues: valoresDeEdicion(servidor),
  })

  const { control, formState: { dirtyFields, errors } } = form

  // `useWatch` y no `form.watch()`: el compilador de React no puede memoizar
  // la función que devuelve el segundo.
  const faltan = camposQueFaltan(useWatch({ control }))

  /**
   * Se valida y se envía SOLO lo que se tocó.
   *
   * Validar el formulario entero traía los defectos de origen de vuelta: un
   * correo viejo con eñes bloqueaba corregir un teléfono, aunque nadie
   * hubiera tocado ese campo.
   */
  const guardar = async () => {
    const campos = Object.keys(dirtyFields).filter(esCampo)

    if (campos.length === 0) {
      onClose()
      return
    }

    if (!(await form.trigger(campos))) return

    const valores = form.getValues()
    const cambios = Object.fromEntries(campos.map((campo) => [campo, valores[campo]]))

    try {
      await editar.mutateAsync({ id: Number(servidor.id), data: cambios })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó
      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (esCampo(campo)) form.setError(campo, { message: mensaje })
        else sinCampo.push(mensaje)
      }
      if (sinCampo.length) {
        notificar.error('No se pudo guardar el expediente', sinCampo.join(' '))
      }
    }
  }

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Editar datos del servidor"
      size="xl"
    >
      <form onSubmit={(e) => { e.preventDefault(); guardar() }} noValidate>
        <FormProvider {...form}>
          <Stack gap="lg">
            {faltan.length > 0 && (
              <Alert
                variant="light"
                color="amber"
                icon={<IconAlertTriangle size={16} />}
                title="Ficha incompleta"
              >
                Falta {faltan.join(', ')}. Puede guardar igual lo que corrija
                ahora y completarlo después.
              </Alert>
            )}

            <SectionCard title="Datos personales">
              <ServidorFormPersonal />
            </SectionCard>

            <SectionCard title="Contacto">
              <ServidorFormContacto />
            </SectionCard>

            <SectionCard
              title="Antigüedad"
              description="La fecha de ingreso al GAD y el puesto salen del vínculo vigente; se cambian con una acción de personal."
            >
              <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />} mb="md">
                La fecha de nombramiento oficial solo aplica a Nombramiento
                Permanente.
              </Alert>

              <Grid>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="fecha_ingreso_sector_publico"
                    control={control}
                    render={({ field }) => (
                      <DatePickerInput
                        label="Fecha ingreso sector público"
                        placeholder="Seleccionar fecha (opcional)"
                        valueFormat="DD/MM/YYYY"
                        clearable
                        maxDate={new Date()}
                        {...contained}
                        value={toDateValue(field.value)}
                        onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                        error={errors.fecha_ingreso_sector_publico?.message}
                      />
                    )}
                  />
                </Grid.Col>

                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="fecha_nombramiento"
                    control={control}
                    render={({ field }) => (
                      <DatePickerInput
                        label="Fecha de nombramiento oficial"
                        placeholder={
                          servidor.contrato_vigente?.tipo_nombramiento === 'nombramiento_permanente'
                            ? 'Seleccionar fecha'
                            : 'Solo aplica a Nombramiento Permanente'
                        }
                        valueFormat="DD/MM/YYYY"
                        clearable
                        maxDate={new Date()}
                        disabled={servidor.contrato_vigente?.tipo_nombramiento !== 'nombramiento_permanente'}
                        {...contained}
                        value={toDateValue(field.value)}
                        onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                        error={errors.fecha_nombramiento?.message}
                      />
                    )}
                  />
                </Grid.Col>
              </Grid>
            </SectionCard>
          </Stack>
        </FormProvider>

        <ModalFooter
          onCancel={onClose}
          submitLabel="Guardar cambios"
          submitting={editar.isPending}
        />
      </form>
    </SgthModal>
  )
}
