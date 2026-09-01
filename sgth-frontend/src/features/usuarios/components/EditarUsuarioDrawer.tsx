'use client'

import { useEffect } from 'react'
import {
  Drawer, Stack, Button, Group, Text, Paper,
  ThemeIcon, Alert, ScrollArea,
} from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconUser, IconCheck, IconUserOff } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { CamposAccesoUsuario } from './CamposAccesoUsuario'
import { usuarioSchema, type UsuarioFormValues } from '../schemas/usuario.schema'
import type { Usuario } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  usuario: Usuario
}

/**
 * Edición de las credenciales de un usuario ya existente.
 *
 * No ofrece cambiar la ficha de servidor —eso se hace desde las acciones de la
 * tabla— así que `servidor_id` solo viaja para no perderlo. Un usuario sin
 * ficha es un estado alcanzable: «Desvincular» lo deja así, y esta pantalla
 * tiene que poder editarlo igualmente.
 */
export function EditarUsuarioDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { actualizar } = useUsuarioMutations()

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<UsuarioFormValues>({
    resolver: zodResolver(usuarioSchema),
    defaultValues: {
      servidor_id: usuario.servidor_id ?? undefined,
      email: usuario.email ?? '',
      usuario_ti: usuario.usuario_ti ?? '',
      roles: usuario.roles ?? [],
    },
  })

  useEffect(() => {
    if (!opened) return

    reset({
      servidor_id: usuario.servidor_id ?? undefined,
      email: usuario.email ?? '',
      usuario_ti: usuario.usuario_ti ?? '',
      roles: usuario.roles ?? [],
    })
  }, [opened, usuario, reset])

  const onSubmit = async (values: UsuarioFormValues) => {
    try {
      await actualizar.mutateAsync({
        id: Number(usuario.id),
        data: {
          email: values.email,
          usuario_ti: values.usuario_ti,
          roles: values.roles,
        },
      })
      onClose()
    } catch {
      // El hook de mutación ya notifica el error.
    }
  }

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="md" radius="md">
            <IconUser size={16} />
          </ThemeIcon>
          <Text fw={700}>Editar usuario</Text>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 520}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Stack gap="md">
            <Paper
              withBorder
              radius="md"
              p="md"
              style={{ borderLeft: '4px solid var(--mantine-color-emerald-6)' }}
            >
              <Group gap="sm">
                <ThemeIcon color="emerald" variant="light" size="lg" radius="xl">
                  <IconUser size={18} />
                </ThemeIcon>
                <Stack gap={0}>
                  <Text size="sm" fw={600}>
                    {usuario.nombre_completo || usuario.email}
                  </Text>
                  <Text size="xs" c="dimmed">
                    CI: {usuario.servidor?.cedula ?? '—'}
                  </Text>
                </Stack>
              </Group>
            </Paper>

            {!usuario.servidor_id && (
              <Alert color="orange" variant="light" icon={<IconUserOff size={16} />}>
                <Text size="xs">
                  Este usuario no tiene ficha de servidor vinculada. Puede editar
                  sus datos de acceso, pero permanecerá inactivo y sin expediente
                  hasta que se le asigne una desde la tabla.
                </Text>
              </Alert>
            )}

            <CamposAccesoUsuario
              register={register}
              control={control}
              errors={errors}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={onClose}>
                Cancelar
              </Button>
              <Button
                type="submit"
                color="emerald"
                loading={isSubmitting || actualizar.isPending}
                leftSection={<IconCheck size={14} />}
              >
                Guardar cambios
              </Button>
            </Group>
          </Stack>
        </form>
      </ScrollArea>
    </Drawer>
  )
}
