'use client'

import { Stack, Group, Select, Button, Textarea, Switch } from '@mantine/core'
import {
  useForm, Controller, useWatch, type DefaultValues,
} from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { SelectorProfesional } from './SelectorProfesional'
import { useCrearTurno } from '../hooks/useAgenda'
import { agendaSchema, type AgendaFormData } from '../schemas/agenda.schema'
import type { PacienteEncontrado } from '../services/pacienteService'
import type { AgendaMedica } from '../services/agendaService'
import { ResumenPaciente } from './ResumenPaciente'

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
    control, handleSubmit, setValue, resetField, setError,
    formState: { errors },
  } = useForm<AgendaFormData>({
    resolver: zodResolver(agendaSchema),
    defaultValues: VALORES_INICIALES,
  })

  const tipoAtencion = useWatch({ control, name: 'tipo_atencion' })

  // Odontología no requiere triaje, Medicina General sí. Al cambiar de
  // especialidad el profesional elegido deja de valer, así que el campo vuelve
  // a su estado inicial: `resetField` lo vacía sin inventarle un valor. Va en
  // el cambio del selector y no en un efecto: responde a lo que hace la
  // persona, no a que cambie un valor (regla 08).
  const cambiarTipo = (tipo: AgendaFormData['tipo_atencion']) => {
    setValue('tipo_atencion', tipo)
    setValue('requiere_triaje', tipo === 'medicina_general')
    resetField('medico_id')
  }

  const onSubmit = (values: AgendaFormData) => {
    crearTurno.mutateAsync(
      {
        medico_id:        values.medico_id,
        tipo_atencion:    values.tipo_atencion,
        motivo_solicitud: values.motivo_solicitud || null,
        requiere_triaje:  values.requiere_triaje,
        ...(paciente.tipo === 'servidor'
          ? { servidor_id: paciente.id }
          : { carga_familiar_id: paciente.id }),
      },
    ).then(onCreado).catch((error: unknown) => {
      // El profesional que no atiende la especialidad vuelve a su campo; el
      // turno duplicado no es de un campo y lo notifica la mutación.
      const campos = erroresDeCampo(error)
      if (campos?.medico_id) setError('medico_id', { message: campos.medico_id })
      if (campos?.motivo_solicitud) setError('motivo_solicitud', { message: campos.motivo_solicitud })
    })
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="md">
        <ResumenPaciente
          nombre={paciente.nombre_completo}
          esServidor={paciente.tipo === 'servidor'}
        />

        <Controller
          name="tipo_atencion"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de atención"
              data={TIPO_ATENCION_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => cambiarTipo(v === 'odontologia' ? 'odontologia' : 'medicina_general')}
              onBlur={field.onBlur}
            />
          )}
        />

        <SelectorProfesional control={control} errors={errors} tipoAtencion={tipoAtencion} />

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
              // Decía «se configura automáticamente» y se podía cambiar a mano.
              description="Se marca según el tipo de atención; puede cambiarlo."
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
