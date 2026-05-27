'use client'

import { useState, useEffect } from 'react'
import {
  Drawer, Stack, TextInput, MultiSelect,
  Button, Group, Divider, Text, Badge,
  Paper, Avatar, Loader, Center,
  Accordion, Checkbox, ScrollArea,
  ThemeIcon, Tooltip, Alert,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import {
  IconUser, IconSearch, IconCheck,
  IconX, IconShieldCheck, IconInfoCircle,
  IconArrowLeft,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useServidoresSinUsuario } from '../hooks/useServidoresSinUsuario'
import { usePermisos } from '../hooks/usePermisos'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { usuarioService } from '../services/usuarioService'
import type { PermisoGrupo, PermisoItem } from '@/types/api'

// ── Schema ────────────────────────────────────────
const schema = z.object({
  servidor_id: z.number({ error: 'Seleccione un servidor' }),
  email:       z.string().email('Email inválido'),
  usuario_ti:  z.string()
    .min(3, 'Mínimo 3 caracteres')
    .regex(/^[a-z0-9]+$/, 'Solo letras minúsculas y números'),
  roles:       z.array(z.string()).min(1, 'Asigne al menos un rol'),
  permisos:    z.array(z.string()).optional(),
})

type FormData = z.infer<typeof schema>

// ── Constantes ────────────────────────────────────
const ROL_LABELS: Record<string, string> = {
  'admin-ti':          'Admin TI',
  'admin-uath':        'Admin UATH',
  'asistente-uath':    'Asistente UATH',
  'maxima-autoridad':  'Máxima Autoridad',
  'director':          'Director',
  'jefe-unidad':       'Jefe de Unidad',
  'servidor':          'Servidor',
  'recepcion':         'Recepción',
  'trabajo-social':    'Trabajo Social',
  'medico':            'Médico',
  'odontologo':        'Odontólogo',
  'enfermera':         'Enfermera',
  'admin-dispensario': 'Admin Dispensario',
  'tecnico-dtic':      'Técnico DTIC',
  'auditor':           'Auditor',
}

const ROLES_DISPONIBLES = Object.keys(ROL_LABELS).map(r => ({
  value: r,
  label: ROL_LABELS[r],
}))

// ── Interfaces ────────────────────────────────────
interface Props {
  opened:  boolean
  onClose: () => void
}

type ServidorItem = {
  id:              number
  cedula:          string
  nombre_completo: string
}

type Paso = 'buscar' | 'configurar'

// ── Componente ────────────────────────────────────
export function UsuarioDrawer({ opened, onClose }: Props) {
  const { isMobile }                = useMobileBreakpoint()
  const contained                   = useContainedInput()
  const { crear }                   = useUsuarioMutations()
  const { data: permisosGrupos = [] } = usePermisos()
  const { data: servidores = [] }   = useServidoresSinUsuario()

  const [paso, setPaso]                     = useState<Paso>('buscar')
  const [busqueda, setBusqueda]             = useState('')
  const [servidorSel, setServidorSel]       = useState<ServidorItem | null>(null)
  const [cargandoTi, setCargandoTi]         = useState(false)
  const [permisosActivos, setPermisosActivos] = useState<string[]>([])

  const {
    register,
    control,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      servidor_id: undefined,
      email:       '',
      usuario_ti:  '',
      roles:       [],
      permisos:    [],
    },
  })

  const rolesActuales = watch('roles')

  // Reset al abrir/cerrar
  useEffect(() => {
    if (!opened) {
      reset()
      setPaso('buscar')
      setBusqueda('')
      setServidorSel(null)
      setPermisosActivos([])
    }
  }, [opened, reset])

  // Servidores filtrados por búsqueda
  const servidoresFiltrados = (servidores as ServidorItem[]).filter(s =>
    s.cedula.includes(busqueda) ||
    s.nombre_completo.toLowerCase().includes(busqueda.toLowerCase())
  )

  // Al seleccionar servidor → pedir usuario_ti sugerido
  const handleSeleccionarServidor = async (s: ServidorItem) => {
    setServidorSel(s)
    setValue('servidor_id', s.id)
    setCargandoTi(true)
    try {
      const res = await usuarioService.sugerirUsuarioTi(s.id)
      const sugerido = (res as unknown as { usuario_ti_sugerido: string })
        .usuario_ti_sugerido
      setValue('usuario_ti', sugerido)
    } catch {
      setValue('usuario_ti', '')
    } finally {
      setCargandoTi(false)
    }
    setPaso('configurar')
  }

  // Toggle permiso directo
  const togglePermiso = (nombre: string) => {
    setPermisosActivos(prev =>
      prev.includes(nombre)
        ? prev.filter(p => p !== nombre)
        : [...prev, nombre]
    )
  }

  // Permisos cubiertos por roles actuales
  const permisosCubiertos = new Set(
    (permisosGrupos as PermisoGrupo[]).flatMap(g =>
      g.permisos.filter((p: PermisoItem) =>
        rolesActuales.some(r => p.roles.includes(r))
      ).map((p: PermisoItem) => p.nombre)
    )
  )

  const onSubmit = (values: FormData) => {
    crear.mutateAsync({
      ...values,
      permisos: permisosActivos,
    } as never).then(() => {
      onClose()
    }).catch(() => {})
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
          <Text fw={700}>
            {paso === 'buscar' ? 'Buscar servidor' : 'Configurar acceso'}
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 560}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="md">

            {/* ── PASO 1: Buscar servidor ── */}
            {paso === 'buscar' && (
              <Stack gap="md">
                <TextInput
                  label="Buscar servidor"
                  placeholder="Cédula o nombre del servidor"
                  leftSection={<IconSearch size={16} />}
                  {...contained}
                  value={busqueda}
                  onChange={(e) => setBusqueda(e.currentTarget.value)}
                />

                {servidoresFiltrados.length === 0 && busqueda.length > 0 && (
                  <Alert
                    icon={<IconInfoCircle size={16} />}
                    color="gray"
                    variant="light"
                  >
                    No se encontraron servidores sin usuario asignado
                    para esa búsqueda.
                  </Alert>
                )}

                <Stack gap="xs">
                  {servidoresFiltrados.slice(0, 8).map(s => (
                    <Paper
                      key={s.id}
                      withBorder
                      radius="md"
                      p="sm"
                      style={{ cursor: 'pointer' }}
                      onClick={() => handleSeleccionarServidor(s)}
                    >
                      <Group justify="space-between">
                        <Group gap="sm">
                          <Avatar color="emerald" size="md" radius="xl">
                            {s.nombre_completo.split(' ').slice(0, 2)
                              .map(w => w[0]).join('').toUpperCase()}
                          </Avatar>
                          <div>
                            <Text size="sm" fw={600}>
                              {s.nombre_completo}
                            </Text>
                            <Text size="xs" c="dimmed">
                              CI: {s.cedula}
                            </Text>
                          </div>
                        </Group>
                        <Badge
                          color="emerald"
                          variant="light"
                          size="sm"
                        >
                          Seleccionar →
                        </Badge>
                      </Group>
                    </Paper>
                  ))}
                </Stack>
              </Stack>
            )}

            {/* ── PASO 2: Configurar acceso ── */}
            {paso === 'configurar' && servidorSel && (
              <Stack gap="md">

                {/* Card del servidor seleccionado */}
                <Paper
                  withBorder
                  radius="md"
                  p="md"
                  style={{
                    borderLeft: '4px solid var(--mantine-color-emerald-6)',
                  }}
                >
                  <Group justify="space-between">
                    <Group gap="sm">
                      <ThemeIcon
                        color="emerald"
                        variant="light"
                        size="lg"
                        radius="xl"
                      >
                        <IconCheck size={18} />
                      </ThemeIcon>
                      <div>
                        <Text size="sm" fw={600}>
                          {servidorSel.nombre_completo}
                        </Text>
                        <Text size="xs" c="dimmed">
                          CI: {servidorSel.cedula}
                        </Text>
                      </div>
                    </Group>
                    <Button
                      size="xs"
                      variant="subtle"
                      color="gray"
                      leftSection={<IconArrowLeft size={12} />}
                      onClick={() => {
                        setPaso('buscar')
                        setServidorSel(null)
                        setValue('servidor_id', undefined as never)
                        setValue('usuario_ti', '')
                        setValue('email', '')
                      }}
                    >
                      Cambiar
                    </Button>
                  </Group>
                </Paper>

                <Divider label="Datos de acceso" labelPosition="left" />

                {/* Email */}
                <TextInput
                  label="Correo institucional"
                  placeholder="usuario@gadpe.gob.ec"
                  {...contained}
                  {...register('email')}
                  error={errors.email?.message}
                />

                {/* usuario_ti */}
                <TextInput
                  label="Usuario del sistema"
                  placeholder="ej: jperez"
                  description="Solo letras minúsculas y números, sin espacios"
                  rightSection={cargandoTi ? <Loader size="xs" /> : undefined}
                  {...contained}
                  {...register('usuario_ti')}
                  error={errors.usuario_ti?.message}
                />

                {/* Roles */}
                <Controller
                  name="roles"
                  control={control}
                  render={({ field }) => (
                    <MultiSelect
                      label="Roles del sistema"
                      placeholder="Seleccione uno o más roles"
                      data={ROLES_DISPONIBLES}
                      searchable
                      {...contained}
                      value={field.value}
                      onChange={field.onChange}
                      error={errors.roles?.message}
                    />
                  )}
                />

                {/* Permisos adicionales */}
                <Divider
                  label={
                    <Group gap="xs">
                      <IconShieldCheck size={14} />
                      <Text size="sm" fw={600}>
                        Permisos adicionales
                      </Text>
                    </Group>
                  }
                  labelPosition="left"
                />

                <Text size="xs" c="dimmed">
                  Los permisos marcados con{' '}
                  <Badge size="xs" color="emerald" variant="light">
                    cubierto
                  </Badge>{' '}
                  ya están incluidos en los roles seleccionados.
                </Text>

                <Accordion variant="separated" radius="md">
                  {(permisosGrupos as PermisoGrupo[]).map(grupo => (
                    <Accordion.Item
                      key={grupo.modulo}
                      value={grupo.modulo}
                    >
                      <Accordion.Control>
                        <Group gap="xs">
                          <Text size="sm" fw={600}>
                            {grupo.modulo}
                          </Text>
                          <Badge size="xs" variant="outline" color="gray">
                            {grupo.permisos.length}
                          </Badge>
                        </Group>
                      </Accordion.Control>
                      <Accordion.Panel>
                        <Stack gap="xs">
                          {grupo.permisos.map((p: PermisoItem) => {
                            const cubierto = permisosCubiertos.has(p.nombre)
                            const activo   = permisosActivos.includes(p.nombre)
                            return (
                              <Group
                                key={p.nombre}
                                justify="space-between"
                                wrap="nowrap"
                              >
                                <Group gap="xs" wrap="nowrap">
                                  <Checkbox
                                    size="sm"
                                    color="emerald"
                                    checked={activo || cubierto}
                                    disabled={cubierto}
                                    onChange={() => !cubierto && togglePermiso(p.nombre)}
                                  />
                                  <Text size="sm" c={cubierto ? 'dimmed' : undefined}>
                                    {p.nombre}
                                  </Text>
                                </Group>
                                {cubierto && (
                                  <Tooltip
                                    label={`Incluido por: ${p.roles
                                      .filter(r => rolesActuales.includes(r))
                                      .join(', ')}`}
                                    withArrow
                                  >
                                    <Badge
                                      size="xs"
                                      color="emerald"
                                      variant="light"
                                    >
                                      cubierto
                                    </Badge>
                                  </Tooltip>
                                )}
                              </Group>
                            )
                          })}
                        </Stack>
                      </Accordion.Panel>
                    </Accordion.Item>
                  ))}
                </Accordion>

                {/* Nota contraseña */}
                <Alert
                  icon={<IconInfoCircle size={16} />}
                  color="blue"
                  variant="light"
                  radius="md"
                >
                  <Text size="xs">
                    La contraseña inicial será la cédula del servidor
                    ({servidorSel.cedula}). Se solicitará cambio
                    en el primer inicio de sesión.
                  </Text>
                </Alert>

                {/* Botones */}
                <Group justify="flex-end" mt="md">
                  <Button
                    variant="default"
                    onClick={onClose}
                    leftSection={<IconX size={14} />}
                  >
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    color="emerald"
                    variant="light"
                    loading={crear.isPending}
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
