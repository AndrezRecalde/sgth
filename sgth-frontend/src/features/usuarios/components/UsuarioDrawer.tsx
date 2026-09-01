'use client'

import { useEffect, useState } from 'react'
import {
  Drawer, Stack, TextInput, MultiSelect, Button, Group,
  Divider, Text, Paper, Loader, ThemeIcon, Alert, ScrollArea,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconUser, IconCheck, IconArrowLeft, IconAlertTriangle, IconUserOff,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { useRoles } from '../hooks/useRoles'
import { usuarioService } from '../services/usuarioService'
import { BuscadorServidor, type ServidorItem } from './BuscadorServidor'
import {
  usuarioSchema,
  type UsuarioFormValues,
} from '../schemas/usuario.schema'
import type { Usuario } from '@/types/api'

type FormData = UsuarioFormValues

type Paso = 'buscar' | 'configurar'

interface Props {
  opened: boolean
  onClose: () => void
  usuario?: Usuario | null
}

export function UsuarioDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear, actualizar } = useUsuarioMutations()
  const { data: roles = [] } = useRoles()

  const modoEditar = !!usuario

  const [paso, setPaso] = useState<Paso>('buscar')
  const [servidorSel, setServidorSel] = useState<ServidorItem | null>(null)
  const [cargandoTi, setCargandoTi] = useState(false)

  const opcionesRol = roles.map(r => ({ value: r.valor, label: r.etiqueta }))

  const {
    register,
    control,
    handleSubmit,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(usuarioSchema),
    defaultValues: {
      servidor_id: undefined,
      email: '',
      usuario_ti: '',
      roles: [],
    },
  })

  // Los pasos se reinician en handleClose, por el que pasan todas las vías de
  // cierre del Drawer (botón, overlay y Escape).
  useEffect(() => {
    if (!opened) return

    reset({
      servidor_id: usuario?.servidor_id ?? undefined,
      email: usuario?.email ?? '',
      usuario_ti: usuario?.usuario_ti ?? '',
      roles: usuario?.roles ?? [],
    })
  }, [opened, usuario, reset])

  const handleClose = () => {
    setPaso('buscar')
    setServidorSel(null)
    onClose()
  }

  const handleSeleccionar = async (s: ServidorItem) => {
    setServidorSel(s)
    setValue('servidor_id', s.id)
    setPaso('configurar')

    setCargandoTi(true)
    try {
      const res = await usuarioService.sugerirUsuarioTi(s.id)
      setValue('usuario_ti', res?.usuario_ti_sugerido ?? '')
    } catch {
      // La sugerencia es una comodidad: si falla, el campo queda para escribir.
      setValue('usuario_ti', '')
    } finally {
      setCargandoTi(false)
    }
  }

  const onSubmit = async (values: FormData) => {
    try {
      if (modoEditar && usuario) {
        await actualizar.mutateAsync({
          id: Number(usuario.id),
          data: {
            email: values.email,
            usuario_ti: values.usuario_ti,
            roles: values.roles,
          },
        })
      } else {
        await crear.mutateAsync({
          email: values.email,
          usuario_ti: values.usuario_ti,
          roles: values.roles,
          servidor_id: values.servidor_id,
        })
      }
      handleClose()
    } catch {
      // El hook de mutación ya notifica el error.
    }
  }

  const camposDeAcceso = (
    <>
      <Divider label="Datos de acceso" labelPosition="left" />

      <TextInput
        label="Correo institucional"
        placeholder="usuario@gadpe.gob.ec"
        {...contained}
        {...register('email')}
        error={errors.email?.message}
      />

      <TextInput
        label="Usuario del sistema"
        placeholder="ej: jperez"
        description="Solo letras minúsculas y números"
        rightSection={cargandoTi ? <Loader size="xs" /> : undefined}
        {...contained}
        {...register('usuario_ti')}
        error={errors.usuario_ti?.message}
      />

      <Controller
        name="roles"
        control={control}
        render={({ field }) => (
          <MultiSelect
            label="Roles del sistema"
            placeholder="Seleccione uno o más roles"
            data={opcionesRol}
            searchable
            {...contained}
            value={field.value}
            onChange={field.onChange}
            error={errors.roles?.message}
          />
        )}
      />
    </>
  )

  return (
    <Drawer
      opened={opened}
      onClose={handleClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="md" radius="md">
            <IconUser size={16} />
          </ThemeIcon>
          <Text fw={700}>
            {modoEditar
              ? 'Editar usuario'
              : paso === 'buscar'
                ? 'Buscar servidor'
                : 'Configurar acceso'}
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 520}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          <Stack gap="md">
            {/* MODO EDITAR */}
            {modoEditar && usuario && (
              <>
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
                  <Alert
                    color="orange"
                    variant="light"
                    icon={<IconUserOff size={16} />}
                  >
                    <Text size="xs">
                      Este usuario no tiene ficha de servidor vinculada. Puede
                      editar sus datos de acceso, pero permanecerá inactivo y sin
                      expediente hasta que se le asigne una desde la tabla.
                    </Text>
                  </Alert>
                )}

                {camposDeAcceso}

                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleClose}>
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
              </>
            )}

            {/* MODO CREAR — PASO 1 */}
            {!modoEditar && paso === 'buscar' && (
              <Stack gap="md">
                <Text size="sm" c="dimmed">
                  Busca al servidor por cédula o nombre para crearle un acceso al
                  sistema.
                </Text>
                <BuscadorServidor onSeleccionar={handleSeleccionar} />
              </Stack>
            )}

            {/* MODO CREAR — PASO 2 */}
            {!modoEditar && paso === 'configurar' && servidorSel && (
              <Stack gap="md">
                <Paper
                  withBorder
                  radius="md"
                  p="md"
                  style={{ borderLeft: '4px solid var(--mantine-color-emerald-6)' }}
                >
                  <Group justify="space-between">
                    <Group gap="sm">
                      <ThemeIcon color="emerald" variant="light" size="lg" radius="xl">
                        <IconCheck size={18} />
                      </ThemeIcon>
                      <Stack gap={0}>
                        <Text size="sm" fw={600}>{servidorSel.nombre_completo}</Text>
                        <Text size="xs" c="dimmed">CI: {servidorSel.cedula}</Text>
                      </Stack>
                    </Group>
                    <Button
                      size="xs"
                      variant="subtle"
                      color="gray"
                      leftSection={<IconArrowLeft size={12} />}
                      onClick={() => {
                        setPaso('buscar')
                        setServidorSel(null)
                        reset({
                          servidor_id: undefined,
                          email: '',
                          usuario_ti: '',
                          roles: [],
                        })
                      }}
                    >
                      Cambiar
                    </Button>
                  </Group>
                </Paper>

                <Alert color="blue" variant="light" icon={<IconAlertTriangle size={16} />}>
                  <Text size="xs">
                    La contraseña inicial será la cédula del servidor
                    ({servidorSel.cedula}). El sistema pedirá cambiarla en el
                    primer inicio de sesión.
                  </Text>
                </Alert>

                {camposDeAcceso}

                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleClose}>
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    color="emerald"
                    loading={isSubmitting || crear.isPending}
                    leftSection={<IconCheck size={14} />}
                  >
                    Crear usuario
                  </Button>
                </Group>
              </Stack>
            )}
          </Stack>
        </form>
      </ScrollArea>
    </Drawer>
  )
}
