'use client'

import { Modal, Tabs, Button, Group, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { IconUser, IconPhone } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useServidorMutations } from '../hooks/useServidorMutations'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
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
      nombres:                 servidor?.nombres ?? '',
      apellidos:               servidor?.apellidos ?? '',
      cedula:                  servidor?.cedula ?? '',
      fecha_nacimiento:        servidor?.fecha_nacimiento ?? '',
      genero:                  (servidor?.genero as ServidorFormData['genero']) ?? 'masculino',
      estado_civil:            (servidor?.estado_civil as ServidorFormData['estado_civil']) ?? 'soltero',
      telefono_personal:       servidor?.telefono_personal ?? '',
      telefono_institucional:  servidor?.telefono_institucional ?? '',
      correo_personal:         servidor?.correo_personal ?? '',
      correo_institucional:    servidor?.correo_institucional ?? '',
      direccion:               servidor?.direccion ?? '',
      provincia_nacimiento_id: servidor?.provincia_nacimiento_id ?? ('' as unknown as number),
      canton_nacimiento_id:    servidor?.canton_nacimiento_id ?? ('' as unknown as number),
    },
    validate: zodResolver(servidorSchema),
  })

  const handleSubmit = (values: ServidorFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number((servidor! as unknown as { id: number }).id), data: values })
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
            <Tabs.Tab value="personal" leftSection={<IconUser size={14} />}>
              Datos personales
            </Tabs.Tab>
            <Tabs.Tab value="contacto" leftSection={<IconPhone size={14} />}>
              Contacto
            </Tabs.Tab>
          </Tabs.List>
          <Tabs.Panel value="personal">
            <ServidorFormPersonal form={form} />
          </Tabs.Panel>
          <Tabs.Panel value="contacto">
            <ServidorFormContacto form={form} />
          </Tabs.Panel>
        </Tabs>
        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={onClose}>Cancelar</Button>
          <Button type="submit" loading={isPending} color="emerald">
            {isEditing ? 'Actualizar' : 'Registrar servidor'}
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
