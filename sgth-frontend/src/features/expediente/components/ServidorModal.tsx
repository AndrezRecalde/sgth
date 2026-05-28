'use client'

import { Modal, Tabs, Button, Group } from '@mantine/core'
import { useForm, FormProvider } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconPhone, IconBriefcase } from '@tabler/icons-react'
import { useEffect } from 'react'
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

export function ServidorModal({ opened, onClose, servidor }: Props) {
  const { isMobile }      = useMobileBreakpoint()
  const { crear, editar } = useServidorMutations()
  const isEditing         = !!servidor

  const form = useForm<ServidorBasicoFormData>({
    resolver: zodResolver(servidorBasicoSchema),
    defaultValues: {
      nombre:           servidor?.nombre  ?? '',
      segundo_nombre:   servidor?.segundo_nombre  ?? '',
      apellido:         servidor?.apellido ?? '',
      segundo_apellido: servidor?.segundo_apellido ?? '',
      cedula:           servidor?.cedula ?? '',
      fecha_nacimiento: servidor?.fecha_nacimiento ?? '',
      genero:           (servidor?.genero as ServidorBasicoFormData['genero'])
        ?? 'masculino',
      estado_civil: (servidor?.estado_civil as ServidorBasicoFormData['estado_civil'])
        ?? 'soltero',
      tipo_sangre:             null,
      es_extranjero:           false,
      provincia_nacimiento_id: servidor?.provincia_nacimiento_id ?? null,
      canton_nacimiento_id:    servidor?.canton_nacimiento_id ?? null,
      nacionalidad:            '',
      pais_origen:             '',
      numero_papeleta_votacion: '',
      pasaporte_numero:        '',
      tiene_discapacidad:            false,
      tiene_enfermedad_catastrofica: false,
      telefono_celular:      servidor?.telefono_celular ?? '',
      telefono_convencional: servidor?.telefono_convencional ?? '',
      correo_personal:       servidor?.correo_personal ?? '',
      direccion_domicilio:   servidor?.direccion_domicilio ?? '',
    },
  })

  const laboralForm = useForm<ServidorLaboralFormData>({
    resolver: zodResolver(servidorLaboralSchema),
    defaultValues: {
      fecha_ingreso_institucion:    servidor?.fecha_ingreso_institucion
        ? servidor.fecha_ingreso_institucion.split('T')[0] : '',
      fecha_ingreso_sector_publico: servidor?.fecha_ingreso_sector_publico
        ? servidor.fecha_ingreso_sector_publico.split('T')[0] : null,
      fecha_nombramiento: servidor?.fecha_nombramiento
        ? servidor.fecha_nombramiento.split('T')[0] : null,
      numero_contrato:    servidor?.numero_contrato ?? null,
    },
  })

  useEffect(() => {
    if (servidor) {
      form.reset({
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
      })
      laboralForm.reset({
        fecha_ingreso_institucion: servidor.fecha_ingreso_institucion
          ? servidor.fecha_ingreso_institucion.split('T')[0] : '',
        fecha_ingreso_sector_publico: servidor.fecha_ingreso_sector_publico
          ? servidor.fecha_ingreso_sector_publico.split('T')[0] : null,
        fecha_nombramiento: servidor.fecha_nombramiento
          ? servidor.fecha_nombramiento.split('T')[0] : null,
        numero_contrato: servidor.numero_contrato ?? null,
      })
    } else {
      form.reset()
      laboralForm.reset()
    }
  }, [servidor, form, laboralForm])

  const handleClose = () => {
    form.reset()
    laboralForm.reset()
    onClose()
  }

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
      <form onSubmit={form.handleSubmit(onSubmit)}>
        <Tabs defaultValue="personal" color="emerald">
          <Tabs.List mb="md">
            <Tabs.Tab
              value="personal"
              leftSection={<IconUser size={14} />}
            >
              Personal
            </Tabs.Tab>
            <Tabs.Tab
              value="contacto"
              leftSection={<IconPhone size={14} />}
            >
              Contacto
            </Tabs.Tab>
            {isEditing && (
              <Tabs.Tab
                value="laboral"
                leftSection={<IconBriefcase size={14} />}
              >
                Laboral
              </Tabs.Tab>
            )}
          </Tabs.List>

          <Tabs.Panel value="personal" pt="md">
            <ServidorFormPersonal form={form} />
          </Tabs.Panel>

          <Tabs.Panel value="contacto" pt="md">
            <ServidorFormContacto form={form} />
          </Tabs.Panel>

          {isEditing && (
            <Tabs.Panel value="laboral" pt="md">
              <FormProvider {...laboralForm}>
                <ServidorFormLaboral />
              </FormProvider>
            </Tabs.Panel>
          )}
        </Tabs>

        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            loading={isPending}
            color="emerald"
            variant="light"
          >
            {isEditing ? 'Actualizar' : 'Registrar servidor'}
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
