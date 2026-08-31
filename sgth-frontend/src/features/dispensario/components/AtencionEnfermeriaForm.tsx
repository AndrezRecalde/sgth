'use client'

import {
  Stack, Group, Select, Button,
  Textarea, Text, Card, Avatar, Badge,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { IconCheck, IconUser, IconUsers } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useCatalogoServicios,
  useRegistrarAtencionEnfermeria,
} from '../hooks/useAtencionEnfermeria'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'

interface Props {
  paciente:   PacienteEncontrado
  onCreado:   (atencion: AtencionEnfermeria) => void
  onCancelar: () => void
}

type FormData = {
  catalogo_servicio_id: number | undefined
  descripcion: string
}

export function AtencionEnfermeriaForm({
  paciente, onCreado, onCancelar,
}: Props) {
  const contained = useContainedInput()
  const { data: catalogo = [] } = useCatalogoServicios()
  const registrar = useRegistrarAtencionEnfermeria()

  const {
    control, handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: {
      catalogo_servicio_id: undefined,
      descripcion: '',
    },
  })

  const catalogoOptions = catalogo.map(c => ({
    value: String(c.id),
    label: c.nombre,
  }))

  const onSubmit = (values: FormData) => {
    if (!values.catalogo_servicio_id) return

    registrar.mutate(
      {
        catalogo_servicio_id: values.catalogo_servicio_id,
        descripcion: values.descripcion || null,
        ...(paciente.tipo === 'servidor'
          ? { servidor_id: paciente.id }
          : { carga_familiar_id: paciente.id }),
      },
      { onSuccess: (atencion) => onCreado(atencion) }
    )
  }

  return (
    <form noValidate onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="md">
        <Card
          withBorder radius="md" p="sm"
          style={{ backgroundColor: 'var(--mantine-color-blue-light)' }}
        >
          <Group gap="sm">
            <Avatar
              color={paciente.tipo === 'servidor' ? 'emerald' : 'blue'}
              radius="xl"
            >
              {paciente.tipo === 'servidor'
                ? <IconUser size={16} />
                : <IconUsers size={16} />}
            </Avatar>
            <Stack gap={0}>
              <Text size="sm" fw={600}>
                {paciente.nombre_completo}
              </Text>
              <Badge size="xs" variant="light">
                {paciente.tipo === 'servidor' ? 'Servidor' : 'Familiar'}
              </Badge>
            </Stack>
          </Group>
        </Card>

        <Controller
          name="catalogo_servicio_id"
          control={control}
          rules={{ required: 'Seleccione el servicio' }}
          render={({ field }) => (
            <Select
              label="Servicio realizado"
              placeholder="Seleccione el servicio"
              data={catalogoOptions}
              searchable
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)}
              error={errors.catalogo_servicio_id?.message}
            />
          )}
        />

        <Controller
          name="descripcion"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Descripción (opcional)"
              placeholder="Detalles adicionales del procedimiento"
              autosize
              minRows={2}
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onCancelar}>
            Cancelar
          </Button>
          <Button
            type="submit"
            color="violet"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending}
          >
            Registrar atención
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
