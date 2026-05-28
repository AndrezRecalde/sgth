'use client'

import { Modal, Tabs, Button, Group } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconPhone } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useServidorMutations } from '../hooks/useServidorMutations'
import { ServidorFormPersonal } from './ServidorFormPersonal'
import { ServidorFormContacto } from './ServidorFormContacto'
import {
  servidorBasicoSchema,
  type ServidorBasicoFormData,
} from '../schemas/servidorBasico.schema'
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
      provincia_domicilio:   servidor?.provincia_domicilio ?? '',
      ciudad_domicilio:      servidor?.ciudad_domicilio ?? '',
    },
  })

  const handleClose = () => {
    form.reset()
    onClose()
  }

  const onSubmit = (values: ServidorBasicoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(servidor!.id), data: values as never })
      : crear.mutateAsync(values as never)
    mutation.then(handleClose).catch(() => {})
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
          </Tabs.List>

          <Tabs.Panel value="personal" pt="md">
            <ServidorFormPersonal form={form} />
          </Tabs.Panel>

          <Tabs.Panel value="contacto" pt="md">
            <ServidorFormContacto form={form} />
          </Tabs.Panel>
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
