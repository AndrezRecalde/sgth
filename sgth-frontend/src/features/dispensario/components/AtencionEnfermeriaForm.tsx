'use client'

import {
  Stack, Group, Select, Button,
  Textarea, Text, Card, Avatar, } from '@mantine/core'
import {
  useForm, Controller, type DefaultValues,
} from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck, IconUser, IconUsers } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useCatalogoServicios,
  useRegistrarAtencionEnfermeria,
} from '../hooks/useAtencionEnfermeria'
import {
  atencionEnfermeriaSchema,
  type AtencionEnfermeriaFormData,
} from '../schemas/atencionEnfermeria.schema'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'
import { StatusBadge } from '@/components/ui'

interface Props {
  paciente:   PacienteEncontrado
  onCreado:   (atencion: AtencionEnfermeria) => void
  onCancelar: () => void
}

/**
 * El servicio se omite: no hay ninguno por defecto, lo elige quien atiende.
 * El esquema lo declara `number`, así que darle `undefined` pediría una
 * aserción de tipo (ver regla 09).
 */
const VALORES_INICIALES: DefaultValues<AtencionEnfermeriaFormData> = {
  descripcion: '',
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
  } = useForm<AtencionEnfermeriaFormData>({
    resolver: zodResolver(atencionEnfermeriaSchema),
    defaultValues: VALORES_INICIALES,
  })

  const catalogoOptions = catalogo.map(c => ({
    value: String(c.id),
    label: c.nombre,
  }))

  const onSubmit = (values: AtencionEnfermeriaFormData) => {
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
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="md">
        <Card
          withBorder radius="md" p="sm"
          style={{ backgroundColor: 'var(--sgth-accent-light)' }}
        >
          <Group gap="sm">
            <Avatar
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
              <StatusBadge size="xs">
                {paciente.tipo === 'servidor' ? 'Servidor' : 'Familiar'}
              </StatusBadge>
            </Stack>
          </Group>
        </Card>

        <Controller
          name="catalogo_servicio_id"
          control={control}
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
              value={field.value ?? ''}
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
