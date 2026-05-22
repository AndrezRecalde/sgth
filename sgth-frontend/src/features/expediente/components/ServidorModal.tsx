'use client'

import { Modal, Tabs, Button, Group } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zod4Resolver } from 'mantine-form-zod-resolver'
import { IconUser, IconPhone, IconBriefcase } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useServidorMutations } from '../hooks/useServidorMutations'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import { ServidorFormLaboral } from './ServidorFormLaboral'
import { servidorSchema, type ServidorFormData } from '../schemas/servidor.schema'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor?: ServidorConRelaciones | null
}

export function ServidorModal({ opened, onClose, servidor }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { crear, editar } = useServidorMutations()
  const isEditing = !!servidor

  const form = useForm<ServidorFormData>({
    initialValues: {
      nombre:           servidor?.nombres?.split(' ')[0] ?? '',
      segundo_nombre:   servidor?.nombres?.split(' ')[1] ?? '',
      apellido:         servidor?.apellidos?.split(' ')[0] ?? '',
      segundo_apellido: servidor?.apellidos?.split(' ')[1] ?? '',
      cedula:           servidor?.cedula ?? '',
      regimen_laboral:  'losep',
      unidad_administrativa_id: '' as unknown as number,
      puesto_id:        '' as unknown as number,
      fecha_nacimiento: servidor?.fecha_nacimiento ?? '',
      genero:           (servidor?.genero as ServidorFormData['genero'])
        ?? 'masculino',
      estado_civil:     (servidor?.estado_civil as ServidorFormData['estado_civil'])
        ?? 'soltero',
      tipo_sangre:      null,
      es_extranjero:    false,
      provincia_nacimiento_id:  servidor?.provincia_nacimiento_id ?? null,
      canton_nacimiento_id:     servidor?.canton_nacimiento_id ?? null,
      nacionalidad:     '',
      pais_origen:      '',
      numero_papeleta_votacion: '',
      pasaporte_numero: '',
      telefono_celular:      servidor?.telefono_personal ?? '',
      telefono_convencional: servidor?.telefono_institucional ?? '',

      correo_personal:       servidor?.correo_personal ?? '',
      direccion_domicilio:   servidor?.direccion ?? '',
      tiene_discapacidad:           false,
      tiene_enfermedad_catastrofica: false,
      tipo_nombramiento:         'nombramiento_permanente',
      numero_contrato:           '',
      fecha_ingreso_institucion: '',
      fecha_ingreso_sector_publico: null,
      fecha_nombramiento:           null,
      fecha_inicio_ultimo_contrato: null,
      fecha_fin_ultimo_contrato:    null,
    },
    validate: zod4Resolver(servidorSchema),
  })

  const handleSubmit = (values: ServidorFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(servidor!.id), data: values })
      : crear.mutateAsync(values)
    mutation.then(() => { form.reset(); onClose() }).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar servidor' : 'Nuevo servidor'}
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Tabs defaultValue="personal" color="emerald">
          <Tabs.List mb="md">
            <Tabs.Tab value="personal"
              leftSection={<IconUser size={14} />}>
              Personal
            </Tabs.Tab>
            <Tabs.Tab value="contacto"
              leftSection={<IconPhone size={14} />}>
              Contacto
            </Tabs.Tab>
            <Tabs.Tab value="laboral"
              leftSection={<IconBriefcase size={14} />}>
              Laboral
            </Tabs.Tab>
          </Tabs.List>
          <Tabs.Panel value="personal" pt="md">
            <ServidorFormPersonal form={form} />
          </Tabs.Panel>
          <Tabs.Panel value="contacto" pt="md">
            <ServidorFormContacto form={form} />
          </Tabs.Panel>
          <Tabs.Panel value="laboral" pt="md">
            <ServidorFormLaboral form={form} />
          </Tabs.Panel>
        </Tabs>
        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={isPending} color="emerald">
            {isEditing ? 'Actualizar' : 'Registrar servidor'}
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
