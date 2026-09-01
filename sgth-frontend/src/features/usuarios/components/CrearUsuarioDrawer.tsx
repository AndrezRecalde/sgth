'use client'

import { useEffect, useState } from 'react'
import {
  Drawer, Stack, Button, Group, Text, Paper,
  ThemeIcon, Alert, ScrollArea,
} from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconUser, IconCheck, IconArrowLeft, IconAlertTriangle,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { usuarioService } from '../services/usuarioService'
import { BuscadorServidor, type ServidorItem } from './BuscadorServidor'
import { CamposAccesoUsuario } from './CamposAccesoUsuario'
import { usuarioSchema, type UsuarioFormValues } from '../schemas/usuario.schema'

type Paso = 'buscar' | 'configurar'

const VALORES_VACIOS: UsuarioFormValues = {
  servidor_id: undefined,
  email: '',
  usuario_ti: '',
  roles: [],
}

interface Props {
  opened: boolean
  onClose: () => void
}

/**
 * Alta de acceso en dos pasos: primero se elige el servidor, después se
 * configuran sus credenciales. El orden no es decorativo — un acceso nace
 * siempre de una ficha existente, y por eso `servidor_id` no necesita validarse
 * en el esquema: no hay forma de llegar al paso 2 sin haberlo elegido.
 */
export function CrearUsuarioDrawer({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { crear } = useUsuarioMutations()

  const [paso, setPaso] = useState<Paso>('buscar')
  const [servidorSel, setServidorSel] = useState<ServidorItem | null>(null)
  const [cargandoTi, setCargandoTi] = useState(false)

  const {
    register,
    control,
    handleSubmit,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<UsuarioFormValues>({
    resolver: zodResolver(usuarioSchema),
    defaultValues: VALORES_VACIOS,
  })

  useEffect(() => {
    if (opened) reset(VALORES_VACIOS)
  }, [opened, reset])

  const handleClose = () => {
    setPaso('buscar')
    setServidorSel(null)
    onClose()
  }

  const volverABuscar = () => {
    setPaso('buscar')
    setServidorSel(null)
    reset(VALORES_VACIOS)
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

  const onSubmit = async (values: UsuarioFormValues) => {
    try {
      await crear.mutateAsync({
        email: values.email,
        usuario_ti: values.usuario_ti,
        roles: values.roles,
        servidor_id: values.servidor_id,
      })
      handleClose()
    } catch {
      // El hook de mutación ya notifica el error.
    }
  }

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
            {paso === 'buscar' ? 'Buscar servidor' : 'Configurar acceso'}
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
            {paso === 'buscar' && (
              <>
                <Text size="sm" c="dimmed">
                  Busca al servidor por cédula o nombre para crearle un acceso al
                  sistema.
                </Text>
                <BuscadorServidor onSeleccionar={handleSeleccionar} />
              </>
            )}

            {paso === 'configurar' && servidorSel && (
              <>
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
                      onClick={volverABuscar}
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

                <CamposAccesoUsuario
                  register={register}
                  control={control}
                  errors={errors}
                  cargandoTi={cargandoTi}
                />

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
              </>
            )}
          </Stack>
        </form>
      </ScrollArea>
    </Drawer>
  )
}
