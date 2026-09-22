'use client'

import { useEffect } from 'react'
import {
  Stack, Group, Select, Button,
  Textarea, Switch, Text, Card,
  Avatar, Alert,
} from '@mantine/core'
import {
  useForm, Controller, useWatch, type DefaultValues,
} from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconCheck, IconUser, IconUsers, IconInfoCircle,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalDisponible, useCrearTurno } from '../hooks/useAgenda'
import { agendaSchema, type AgendaFormData } from '../schemas/agenda.schema'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'
import { StatusBadge } from '@/components/ui'

interface Props {
  paciente:    PacienteEncontrado
  onCreado:    (agenda: AgendaMedica) => void
  onCancelar:  () => void
}

const TIPO_ATENCION_OPTIONS = [
  { value: 'medicina_general', label: 'Medicina General' },
  { value: 'odontologia',      label: 'Odontología'      },
]

/**
 * `medico_id` se omite en vez de escribirse `undefined`: el esquema lo declara
 * `number`, y darle `undefined` era lo que obligaba a una aserción de tipo
 * (ver regla 09). Quien abre el formulario elige el profesional.
 */
const VALORES_INICIALES: DefaultValues<AgendaFormData> = {
  tipo_atencion:    'medicina_general',
  motivo_solicitud: '',
  requiere_triaje:  true,
}

export function CrearTurnoForm({
  paciente, onCreado, onCancelar,
}: Props) {
  const contained = useContainedInput()
  const crearTurno = useCrearTurno()

  const {
    control, handleSubmit, setValue, resetField,
    formState: { errors },
  } = useForm<AgendaFormData>({
    resolver: zodResolver(agendaSchema),
    defaultValues: VALORES_INICIALES,
  })

  const tipoAtencion = useWatch({ control, name: 'tipo_atencion' })

  const { data: resultadoPersonal, isLoading: cargandoPersonal } =
    usePersonalDisponible(tipoAtencion)

  const personal       = resultadoPersonal?.personal ?? []
  const hayDisponibles = resultadoPersonal?.hayDisponibles ?? true

  // Odontología no requiere triaje, Medicina General sí. Al cambiar de
  // especialidad el profesional elegido deja de valer, así que el campo vuelve
  // a su estado inicial: `resetField` lo vacía sin inventarle un valor.
  useEffect(() => {
    setValue('requiere_triaje', tipoAtencion === 'medicina_general')
    resetField('medico_id')
  }, [tipoAtencion, setValue, resetField])

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
                    ? 'Sin profesionales de esta especialidad'
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

        {/* El aviso decía «no hay profesionales marcados como disponibles»
            cuando la lista salía vacía, pero la disponibilidad no se
            consultaba nunca: la lista vacía solo significaba que no había
            nadie con ese rol. Ahora cada caso dice lo suyo. */}
        {!cargandoPersonal && personalOptions.length === 0 && (
          <Alert
            icon={<IconInfoCircle size={14} />}
            color="amber"
            variant="light"
          >
            <Text size="xs">
              No hay ningún profesional registrado para este tipo de atención.
            </Text>
          </Alert>
        )}

        {!cargandoPersonal && personalOptions.length > 0 && !hayDisponibles && (
          <Alert
            icon={<IconInfoCircle size={14} />}
            color="amber"
            variant="light"
          >
            <Text size="xs">
              Nadie se ha marcado disponible para este tipo de atención. Se
              muestran todos los profesionales para no detener el turno.
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
            loading={crearTurno.isPending}
          >
            Crear turno
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
