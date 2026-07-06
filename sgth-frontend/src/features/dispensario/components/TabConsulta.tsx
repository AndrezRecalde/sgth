'use client'

import {
  Stack, Textarea, Select, Group,
  Button, Text,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRegistrarConsulta } from '../hooks/useConsultaMedica'
import { BuscarCie10Input } from './BuscarCie10Input'
import {
  consultaMedicaSchema,
  type ConsultaMedicaFormData,
  TIPO_ATENCION_OPTIONS,
  TIPO_DIAGNOSTICO_OPTIONS,
} from '../schemas/consultaMedica.schema'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../services/cie10Service'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
  consultaPrevia?:   ConsultaMedica | null
  onGuardada:        (consulta: ConsultaMedica) => void
}

function formatFechaLocal(d: Date): string {
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export function TabConsulta({
  turno, historiaClinicaId, consultaPrevia, onGuardada,
}: Props) {
  const contained = useContainedInput()
  const registrar = useRegistrarConsulta()
  const [cie10Principal, setCie10Principal] =
    useState<DiagnosticoCie10 | null>(null)
  const [cie10Secundarios, setCie10Secundarios] =
    useState<DiagnosticoCie10[]>([])

  const yaGuardada = !!consultaPrevia

  const {
    control, register, handleSubmit,
    formState: { errors },
  } = useForm<ConsultaMedicaFormData>({
    resolver: zodResolver(consultaMedicaSchema),
    defaultValues: {
      tipo_atencion:    'primera_vez',
      tipo_diagnostico: 'presuntivo',
      motivo_consulta:  turno.motivo_solicitud ?? '',
      enfermedad_actual: '',
      examen_fisico:    '',
      diagnostico_detallado: '',
      plan_tratamiento: '',
      notas_medico:     '',
    },
  })

  const onSubmit = (values: ConsultaMedicaFormData) => {
    const ahora = new Date()
    registrar.mutate(
      {
        historia_clinica_id:      historiaClinicaId,
        agenda_medica_id:         turno.id,
        fecha_consulta:           formatFechaLocal(ahora),
        hora_consulta:            ahora.toTimeString().slice(0, 5),
        tipo_atencion:            values.tipo_atencion,
        tipo_diagnostico:         values.tipo_diagnostico,
        motivo_consulta:          values.motivo_consulta,
        enfermedad_actual:        values.enfermedad_actual || null,
        examen_fisico:            values.examen_fisico || null,
        diagnostico_cie10_id:     cie10Principal?.id ?? null,
        diagnosticos_secundarios: cie10Secundarios.map(d => d.id),
        diagnostico_detallado:    values.diagnostico_detallado,
        plan_tratamiento:         values.plan_tratamiento || null,
        notas_medico:             values.notas_medico || null,
      },
      { onSuccess: (consulta) => onGuardada(consulta) }
    )
  }

  if (yaGuardada) {
    return (
      <Stack gap="sm" p="md">
        <Text size="sm" c="dimmed">
          Consulta registrada correctamente. Use los tabs
          "Receta", "Historial" o "Certificado" para continuar.
        </Text>
      </Stack>
    )
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="sm" p="md">
        <Group grow align="flex-start">
          <Controller
            name="tipo_atencion"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de atención"
                data={TIPO_ATENCION_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'primera_vez')}
                error={errors.tipo_atencion?.message}
              />
            )}
          />
          <Controller
            name="tipo_diagnostico"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de diagnóstico"
                data={TIPO_DIAGNOSTICO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'presuntivo')}
                error={errors.tipo_diagnostico?.message}
              />
            )}
          />
        </Group>

        <Textarea
          label="Motivo de consulta"
          placeholder="¿Por qué acude hoy el paciente?"
          autosize
          minRows={2}
          {...contained}
          {...register('motivo_consulta')}
          error={errors.motivo_consulta?.message}
        />

        <Textarea
          label="Enfermedad actual / Anamnesis (opcional)"
          placeholder="Inicio, duración, características, factores que agravan o alivian..."
          autosize
          minRows={4}
          {...contained}
          {...register('enfermedad_actual')}
        />

        <Textarea
          label="Examen físico (opcional)"
          placeholder="Hallazgos relevantes del examen físico"
          autosize
          minRows={4}
          {...contained}
          {...register('examen_fisico')}
        />

        <Stack gap="xs">
          <BuscarCie10Input
            value={cie10Principal}
            onChange={setCie10Principal}
          />
          <Text size="xs" c="dimmed">
            Diagnóstico principal (CIE-10)
          </Text>
        </Stack>

        <Stack gap="xs">
          <Text size="sm" fw={500}>
            Diagnósticos secundarios{' '}
            <Text span size="xs" c="dimmed">(opcional, máx. 3)</Text>
          </Text>
          {cie10Secundarios.length < 3 && (
            <BuscarCie10Input
              value={null}
              onChange={(d) => {
                if (d && !cie10Secundarios.find(s => s.id === d.id)) {
                  setCie10Secundarios(prev => [...prev, d])
                }
              }}
            />
          )}
          {cie10Secundarios.map((d) => (
            <Group key={d.id} gap={6}>
              <Text size="xs" ff="monospace" c="dimmed">{d.codigo}</Text>
              <Text size="xs">{d.descripcion}</Text>
              <Button
                size="compact-xs"
                variant="subtle"
                color="red"
                onClick={() => setCie10Secundarios(
                  prev => prev.filter(s => s.id !== d.id)
                )}
              >
                ×
              </Button>
            </Group>
          ))}
        </Stack>

        <Textarea
          label="Diagnóstico detallado"
          placeholder="Descripción clínica del diagnóstico"
          autosize
          minRows={4}
          {...contained}
          {...register('diagnostico_detallado')}
          error={errors.diagnostico_detallado?.message}
        />

        <Textarea
          label="Plan de tratamiento (opcional)"
          placeholder="Indicaciones y tratamiento a seguir"
          autosize
          minRows={4}
          {...contained}
          {...register('plan_tratamiento')}
        />

        <Group justify="flex-end" pt="sm">
          <Button
            type="submit"
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending}
          >
            Guardar consulta
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
