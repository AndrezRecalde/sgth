'use client'

import {
  TextInput, MultiSelect, Grid, Text,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { zodResolver } from 'mantine-form-zod-resolver'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRoles } from '../hooks/useRoles'
import {
  usuarioSchema,
  type UsuarioFormData,
} from '../schemas/usuario.schema'

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
  const { data: roles = [] } = useRoles()

  const rolOptions = (roles as string[]).map(r => ({
    value: r,
    label: ROL_LABELS[r] ?? r,
  }))

  const form = useForm<UsuarioFormData>({
    initialValues: {
      nombre:   initialValues?.nombre   ?? '',
      apellido: initialValues?.apellido ?? '',
      email:    initialValues?.email    ?? '',
      cedula:   initialValues?.cedula   ?? '',
      roles:    initialValues?.roles    ?? [],
    },
    validate: zodResolver(usuarioSchema),
  })

  return (
    <form id="usuario-form" onSubmit={form.onSubmit(onSubmit)}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Nombre"
            placeholder="Primer nombre"
            {...contained}
            {...form.getInputProps('nombre')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Apellido"
            placeholder="Primer apellido"
            {...contained}
            {...form.getInputProps('apellido')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Correo institucional"
            placeholder="usuario@gad-esmeraldas.gob.ec"
            {...contained}
            {...form.getInputProps('email')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Cédula de identidad"
            placeholder="0000000000"
            maxLength={10}
            disabled={isEditing}
            {...contained}
            {...form.getInputProps('cedula')}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <MultiSelect
            label="Roles del sistema"
            placeholder="Seleccione uno o más roles"
            data={rolOptions}
            searchable
            {...contained}
            value={form.values.roles}
            onChange={(v) => form.setFieldValue('roles', v)}
            error={form.errors.roles}
          />
        </Grid.Col>
        {!isEditing && (
          <Grid.Col span={12}>
            <Text size="xs" c="dimmed">
              La contraseña inicial será la cédula del usuario.
              Se solicitará cambio en el primer inicio de sesión.
            </Text>
          </Grid.Col>
        )}
      </Grid>
    </form>
  )
}
