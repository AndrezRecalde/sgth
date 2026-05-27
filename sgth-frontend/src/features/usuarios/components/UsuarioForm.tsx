'use client'

import { useEffect } from 'react'
import { TextInput, MultiSelect, Select, Grid, Text } from '@mantine/core'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRoles } from '../hooks/useRoles'
import { useServidoresSinUsuario } from '../hooks/useServidoresSinUsuario'
import { usuarioSchema, type UsuarioFormData } from '../schemas/usuario.schema'

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

interface Props {
  initialValues?: Partial<UsuarioFormData>
  onSubmit: (values: UsuarioFormData) => void
  isEditing?: boolean
}

export function UsuarioForm({ initialValues, onSubmit, isEditing }: Props) {
  const contained = useContainedInput()
  const { data: roles = [] }      = useRoles()
  const { data: servidores = [] } = useServidoresSinUsuario()

  const rolOptions = (roles as string[]).map(r => ({
    value: r,
    label: ROL_LABELS[r] ?? r,
  }))

  type ServidorItem = {
    id: number
    cedula: string
    nombre_completo: string
  }
  const servidorOptions = (servidores as ServidorItem[]).map(s => ({
    value: String(s.id),
    label: `${s.cedula} — ${s.nombre_completo}`,
  }))

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<UsuarioFormData>({
    resolver: zodResolver(usuarioSchema) as Resolver<UsuarioFormData>,
    defaultValues: {
      email:       initialValues?.email       ?? '',
      roles:       initialValues?.roles       ?? [],
      servidor_id: initialValues?.servidor_id ?? null,
      cedula:      initialValues?.cedula      ?? '',
    },
  })

  const emailVal = initialValues?.email
  const rolesVal = initialValues?.roles?.join(',')
  const servidorIdVal = initialValues?.servidor_id
  const cedulaVal = initialValues?.cedula

  useEffect(() => {
    reset({
      email:       emailVal       ?? '',
      roles:       initialValues?.roles       ?? [],
      servidor_id: servidorIdVal ?? null,
      cedula:      cedulaVal      ?? '',
    })
  }, [emailVal, rolesVal, servidorIdVal, cedulaVal, reset, initialValues?.roles])

  return (
    <form id="usuario-form" onSubmit={handleSubmit(onSubmit)}>
      <Grid>
        {!isEditing && (
          <Grid.Col span={12}>
            <Controller
              name="servidor_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Vincular a servidor (expediente)"
                  placeholder="Buscar por cédula o nombre"
                  data={servidorOptions}
                  searchable
                  clearable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : null)}
                  error={errors.servidor_id?.message}
                />
              )}
            />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <TextInput
            label="Correo institucional"
            placeholder="usuario@gad-esmeraldas.gob.ec"
            {...contained}
            {...register('email')}
            error={errors.email?.message}
          />
        </Grid.Col>
        {!isEditing && (
          <Grid.Col span={12}>
            <TextInput
              label="Cédula de identidad"
              placeholder="0000000000 (contraseña inicial)"
              maxLength={10}
              {...contained}
              {...register('cedula')}
              error={errors.cedula?.message}
            />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <Controller
            name="roles"
            control={control}
            render={({ field }) => (
              <MultiSelect
                label="Roles del sistema"
                placeholder="Seleccione uno o más roles"
                data={rolOptions}
                searchable
                {...contained}
                value={field.value}
                onChange={field.onChange}
                error={errors.roles?.message}
              />
            )}
          />
        </Grid.Col>
        {!isEditing && (
          <Grid.Col span={12}>
            <Text size="xs" c="dimmed">
              La contraseña inicial será la cédula del usuario o la cédula
              del servidor vinculado. Se solicitará cambio en el primer inicio.
            </Text>
          </Grid.Col>
        )}
      </Grid>
    </form>
  )
}
