'use client'

import {
  Stack, Textarea, Select, Group,
  Button, Text, Badge, Card,
  ActionIcon, Tooltip,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck, IconEdit, IconX } from '@tabler/icons-react'
import { useState, useEffect } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useRegistrarConsulta, useActualizarConsulta,
} from '../hooks/useConsultaMedica'
import { BuscarCie10Input } from './BuscarCie10Input'
import { RichTextInput } from './RichTextInput'
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

function CampoVista({
  label, valor, destacado = false,
}: {
  label:       string
  valor?:      string | null
  destacado?:  boolean
}) {
  if (!valor) return null
  const esHtml = valor.startsWith('<')
  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={destacado ? {
        borderLeft: '3px solid var(--mantine-color-blue-6)',
      } : undefined}
    >
      <Stack gap={4}>
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.04em' }}
        >
          {label}
        </Text>
        {esHtml ? (
          <div
            style={{
              fontSize: 'var(--mantine-font-size-sm)',
              lineHeight: 1.6,
            }}
            dangerouslySetInnerHTML={{ __html: valor }}
          />
        ) : (
          <Text size="sm" style={{ lineHeight: 1.6 }}>{valor}</Text>
        )}
      </Stack>
    </Card>
  )
}

export function TabConsulta({
  turno, historiaClinicaId, consultaPrevia, onGuardada,
}: Props) {
  const contained  = useContainedInput()
  const registrar  = useRegistrarConsulta()
  const actualizar = useActualizarConsulta()
  const [modoEdicion, setModoEdicion] = useState(!consultaPrevia)
  const [cie10Principal, setCie10Principal] =
    useState<DiagnosticoCie10 | null>(null)
  const [cie10Secundarios, setCie10Secundarios] =
    useState<DiagnosticoCie10[]>([])

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<ConsultaMedicaFormData>({
    resolver: zodResolver(consultaMedicaSchema),
    defaultValues: {
      tipo_atencion:         'primera_vez',
      tipo_diagnostico:      'presuntivo',
      motivo_consulta:       turno.motivo_solicitud ?? '',
      enfermedad_actual:     '',
      examen_fisico:         '',
      diagnostico_detallado: '',
      plan_tratamiento:      '',
      notas_medico:          '',
    },
  })

  useEffect(() => {
    if (consultaPrevia) {
      setModoEdicion(false)
      reset({
        tipo_atencion:         (consultaPrevia.tipo_atencion as ConsultaMedicaFormData['tipo_atencion']) ?? 'primera_vez',
        tipo_diagnostico:      (consultaPrevia.tipo_diagnostico as ConsultaMedicaFormData['tipo_diagnostico']) ?? 'presuntivo',
        motivo_consulta:       consultaPrevia.motivo_consulta ?? '',
        enfermedad_actual:     consultaPrevia.enfermedad_actual ?? '',
        examen_fisico:         consultaPrevia.examen_fisico ?? '',
        diagnostico_detallado: consultaPrevia.diagnostico_detallado ?? '',
        plan_tratamiento:      consultaPrevia.plan_tratamiento ?? '',
        notas_medico:          consultaPrevia.notas_medico ?? '',
      })
    }
  }, [consultaPrevia, reset])

  const onSubmit = (values: ConsultaMedicaFormData) => {
    if (consultaPrevia) {
      actualizar.mutate(
        {
          id: consultaPrevia.id,
          data: {
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
        },
        { onSuccess: () => setModoEdicion(false) }
      )
    } else {
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
  }

  if (!modoEdicion && consultaPrevia) {
    return (
      <Stack gap="sm" p="md">
        <Group justify="space-between" align="center">
          <Group gap="xs" wrap="wrap">
            <Badge size="sm" variant="light" color="emerald">
              Guardada
            </Badge>
            <Badge size="sm" variant="light" color="blue">
              {consultaPrevia.tipo_atencion?.replace(/_/g, ' ')}
            </Badge>
            <Badge
              size="sm"
              variant="light"
              color={consultaPrevia.tipo_diagnostico === 'definitivo'
                ? 'emerald' : 'orange'}
            >
              {consultaPrevia.tipo_diagnostico}
            </Badge>
          </Group>
          <Tooltip label="Editar consulta" withArrow>
            <ActionIcon
              variant="light"
              color="blue"
              onClick={() => setModoEdicion(true)}
            >
              <IconEdit size={16} />
            </ActionIcon>
          </Tooltip>
        </Group>

        <CampoVista
          label="Motivo de consulta"
          valor={consultaPrevia.motivo_consulta}
        />
        <CampoVista
          label="Enfermedad actual / Anamnesis"
          valor={consultaPrevia.enfermedad_actual}
        />
        <CampoVista
          label="Examen físico"
          valor={consultaPrevia.examen_fisico}
        />

        {consultaPrevia.diagnostico_cie10_principal && (
          <Card withBorder radius="md" p="sm"
            style={{
              borderLeft: '3px solid var(--mantine-color-blue-6)',
            }}
          >
            <Stack gap={6}>
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.04em' }}
              >
                Diagnóstico principal (CIE-10)
              </Text>
              <Group gap="xs" align="flex-start">
                <Badge
                  size="md"
                  variant="light"
                  color="blue"
                  ff="monospace"
                >
                  {consultaPrevia.diagnostico_cie10_principal.codigo}
                </Badge>
                <Text size="sm" style={{ flex: 1, lineHeight: 1.5 }}>
                  {consultaPrevia.diagnostico_cie10_principal.descripcion}
                </Text>
              </Group>
            </Stack>
          </Card>
        )}

        {(consultaPrevia.diagnosticos_secundarios?.length ?? 0) > 0 && (
          <Card withBorder radius="md" p="sm">
            <Stack gap={6}>
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.04em' }}
              >
                Diagnósticos secundarios
              </Text>
              <Stack gap={4}>
                {consultaPrevia.diagnosticos_secundarios?.map((ds) => (
                  <Group key={ds.id} gap="xs" align="flex-start">
                    <Badge
                      size="sm"
                      variant="outline"
                      color="blue"
                      ff="monospace"
                    >
                      {ds.diagnostico?.codigo}
                    </Badge>
                    <Text size="sm" c="dimmed" style={{ flex: 1 }}>
                      {ds.diagnostico?.descripcion}
                    </Text>
                  </Group>
                ))}
              </Stack>
            </Stack>
          </Card>
        )}

        <CampoVista
          label="Diagnóstico detallado"
          valor={consultaPrevia.diagnostico_detallado}
          destacado
        />
        <CampoVista
          label="Plan de tratamiento"
          valor={consultaPrevia.plan_tratamiento}
          destacado
        />
        <CampoVista
          label="Notas del médico"
          valor={consultaPrevia.notas_medico}
        />
      </Stack>
    )
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="sm" p="md">
        {consultaPrevia && (
          <Group justify="flex-end">
            <Button
              size="xs"
              variant="subtle"
              color="gray"
              leftSection={<IconX size={13} />}
              onClick={() => setModoEdicion(false)}
            >
              Cancelar edición
            </Button>
          </Group>
        )}

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
          placeholder="Inicio, duración, características..."
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
          <Text size="xs" c="dimmed">Diagnóstico principal (CIE-10)</Text>
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
              >×</Button>
            </Group>
          ))}
        </Stack>

        <Controller
          name="diagnostico_detallado"
          control={control}
          render={({ field }) => (
            <RichTextInput
              label="Diagnóstico detallado"
              required
              value={field.value ?? ''}
              onChange={field.onChange}
              error={errors.diagnostico_detallado?.message}
            />
          )}
        />

        <Controller
          name="plan_tratamiento"
          control={control}
          render={({ field }) => (
            <RichTextInput
              label="Plan de tratamiento"
              description="Opcional"
              value={field.value ?? ''}
              onChange={field.onChange}
            />
          )}
        />

        <Group justify="flex-end" pt="sm">
          <Button
            type="submit"
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending || actualizar.isPending}
          >
            {consultaPrevia ? 'Guardar cambios' : 'Guardar consulta'}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
