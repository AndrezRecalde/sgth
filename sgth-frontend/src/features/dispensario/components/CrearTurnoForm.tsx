'use client'

import { useEffect } from 'react'
import {
  Stack, Group, Select, Button,
  Textarea, Switch, Text, Card,
  Avatar, Badge, Alert,
} from '@mantine/core'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconCheck, IconUser, IconUsers, IconInfoCircle,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalDisponible, useCrearTurno } from '../hooks/useAgenda'
import { agendaSchema, type AgendaFormData } from '../schemas/agenda.schema'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  paciente:    PacienteEncontrado
  onCreado:    (agenda: AgendaMedica) => void
  onCancelar:  () => void
}

const TIPO_ATENCION_OPTIONS = [
  { value: 'medicina_general', label: 'Medicina General' },
  { value: 'odontologia',      label: 'Odontología'      },
]

export function CrearTurnoForm({
  paciente, onCreado, onCancelar,
}: Props) {
  const contained = useContainedInput()
  const crearTurno = useCrearTurno()

  const {
    control, handleSubmit, setValue,
    formState: { errors },
  } = useForm<AgendaFormData>({
    resolver: zodResolver(agendaSchema),
    defaultValues: {
      medico_id:        undefined,
      tipo_atencion:    'medicina_general',
      motivo_solicitud: '',
      requiere_triaje:  true,
    } as never,
  })

  const tipoAtencion = useWatch({ control, name: 'tipo_atencion' })

  const { data: personal = [], isLoading: cargandoPersonal } =
    usePersonalDisponible(tipoAtencion)

  // Odontología no requiere triaje, Medicina General sí
  useEffect(() => {
    setValue('requiere_triaje', tipoAtencion === 'medicina_general')
    setValue('medico_id', undefined as never)
  }, [tipoAtencion, setValue])

  const personalOptions = personal.map(p => ({
    value: String(p.id),
    label: p.nombre_completo,
  }))

  const onSubmit = (values: AgendaFormData) => {
    crearTurno.mutate(
      {
        medico_id:        values.medico_id,
        tipo_atencion:    values.tipo_atencion,
        motivo_solicitud: values.motivo_solicitud || null,
        requiere_triaje:  values.requiere_triaje,
        ...(paciente.tipo === 'servidor'
          ? { servidor_id: paciente.id }
          : { carga_familiar_id: paciente.id }),
      },
      {
        onSuccess: (agenda) => onCreado(agenda),
      }
    )
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
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
          name="tipo_atencion"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de atención"
              data={TIPO_ATENCION_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) =>
                field.onChange(v ?? 'medicina_general')
              }
            />
          )}
        />

        <Controller
          name="medico_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Profesional disponible"
              placeholder={
                cargandoPersonal
                  ? 'Cargando...'
                  : personalOptions.length === 0
                    ? 'Sin profesionales disponibles'
                    : 'Seleccione el profesional'
              }
              data={personalOptions}
              searchable
              disabled={cargandoPersonal}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)}
              error={errors.medico_id?.message}
            />
          )}
        />

        {!cargandoPersonal && personalOptions.length === 0 && (
          <Alert
            icon={<IconInfoCircle size={14} />}
            color="orange"
            variant="light"
          >
            <Text size="xs">
              No hay profesionales marcados como disponibles
              para este tipo de atención en este momento.
            </Text>
          </Alert>
        )}

        <Controller
          name="motivo_solicitud"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Motivo de la solicitud (opcional)"
              placeholder="Describa brevemente el motivo de la visita"
              autosize
              minRows={2}
              {...contained}
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />

        <Controller
          name="requiere_triaje"
          control={control}
          render={({ field }) => (
            <Switch
              label="Requiere triaje (signos vitales)"
              description="Se configura automáticamente según
                el tipo de atención"
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              color="emerald"
            />
          )}
        />

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onCancelar}>
            Cancelar
          </Button>
          <Button
            type="submit"
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={crearTurno.isPending}
          >
            Crear turno
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
