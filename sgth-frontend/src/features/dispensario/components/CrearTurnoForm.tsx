'use client'

import { useEffect } from 'react'
import {
  Stack, Group, Select, Button,
  Textarea, Switch, Text, Card,
  Avatar, Badge,
} from '@mantine/core'
import { TimeInput } from '@mantine/dates'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck, IconUser, IconUsers } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalMedico, useCrearTurno } from '../hooks/useAgenda'
import { agendaSchema, type AgendaFormData } from '../schemas/agenda.schema'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  paciente:    PacienteEncontrado
  onCreado:    (agenda: AgendaMedica) => void
  onCancelar:  () => void
}

const ROL_OPTIONS = [
  { value: 'medico',     label: 'Médico'     },
  { value: 'odontologo', label: 'Odontólogo' },
  { value: 'enfermera',  label: 'Enfermera'  },
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
      fecha:            new Date().toISOString().slice(0, 10),
      hora_inicio:      new Date().toTimeString().slice(0, 5),
      hora_fin:         '',
      motivo_solicitud: '',
      requiere_triaje:  true,
      rol_filtro:       'medico',
    } as never,
  })

  const rolFiltro = useWatch({ control, name: 'rol_filtro' })

  const { data: personal = [] } = usePersonalMedico(
    rolFiltro as 'medico' | 'odontologo' | 'enfermera'
  )

  // Odontología no requiere triaje por defecto
  useEffect(() => {
    if (rolFiltro === 'odontologo') {
      setValue('requiere_triaje', false)
    } else {
      setValue('requiere_triaje', true)
    }
  }, [rolFiltro, setValue])

  const personalOptions = personal.map(p => ({
    value: String(p.id),
    label: p.nombre_completo,
  }))

  const onSubmit = (values: AgendaFormData) => {
    crearTurno.mutate(
      {
        medico_id:        values.medico_id,
        fecha:            values.fecha,
        hora_inicio:      values.hora_inicio,
        hora_fin:         values.hora_fin,
        motivo_solicitud: values.motivo_solicitud,
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
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="md">
        <Card withBorder radius="md" p="sm" bg="blue.0">
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
          name="rol_filtro"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de atención"
              data={ROL_OPTIONS}
              {...contained}
              value={field.value ?? 'medico'}
              onChange={(v) => field.onChange(v ?? 'medico')}
            />
          )}
        />

        <Controller
          name="medico_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Profesional"
              placeholder="Seleccione el profesional"
              data={personalOptions}
              searchable
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)}
              error={errors.medico_id?.message}
            />
          )}
        />

        <Group grow>
          <Controller
            name="hora_inicio"
            control={control}
            render={({ field }) => (
              <TimeInput
                label="Hora inicio"
                {...contained}
                {...field}
                error={errors.hora_inicio?.message}
              />
            )}
          />
          <Controller
            name="hora_fin"
            control={control}
            render={({ field }) => (
              <TimeInput
                label="Hora fin"
                {...contained}
                {...field}
                error={errors.hora_fin?.message}
              />
            )}
          />
        </Group>

        <Controller
          name="motivo_solicitud"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Motivo de la solicitud"
              placeholder="Describa brevemente el motivo de la visita"
              autosize
              minRows={2}
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
              error={errors.motivo_solicitud?.message}
            />
          )}
        />

        <Controller
          name="requiere_triaje"
          control={control}
          render={({ field }) => (
            <Switch
              label="Requiere triaje (signos vitales)"
              description="Desactive para citas odontológicas que no necesitan triaje"
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
