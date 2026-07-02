'use client'

import { useState } from 'react'
import {
  Card, Stack, Group, Text, Badge,
  Textarea, Button, Avatar, Grid,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconCheck, IconUser, IconUsers,
  IconPill, IconCertificate,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRegistrarConsulta } from '../hooks/useConsultaMedica'
import { RecetaModal } from './RecetaModal'
import { useDisclosure } from '@mantine/hooks'
import { BuscarCie10Input } from './BuscarCie10Input'
import { PanelContextoPaciente } from './PanelContextoPaciente'
import {
  consultaMedicaSchema,
  type ConsultaMedicaFormData,
} from '../schemas/consultaMedica.schema'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../services/cie10Service'

interface Props {
  turno:             AgendaMedica
  historiaClinicaId: number
  onGuardada:        (consulta: ConsultaMedica) => void
  onCancelar:        () => void
}

function formatFechaLocal(d: Date): string {
  const year  = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day   = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function ConsultaMedicaForm({
  turno, historiaClinicaId, onGuardada, onCancelar,
}: Props) {
  const contained  = useContainedInput()
  const registrar  = useRegistrarConsulta()
  const [diagnosticoCie10, setDiagnosticoCie10] =
    useState<DiagnosticoCie10 | null>(null)
  const [consultaGuardada, setConsultaGuardada] =
    useState<ConsultaMedica | null>(null)
  const [recetaOpened,
    { open: abrirReceta, close: cerrarReceta }] = useDisclosure(false)

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

  const {
    control, handleSubmit,
    formState: { errors },
  } = useForm<ConsultaMedicaFormData>({
    resolver: zodResolver(consultaMedicaSchema),
    defaultValues: {
      motivo_consulta:       turno.motivo_solicitud ?? '',
      examen_fisico:         '',
      diagnostico_cie10:     null,
      diagnostico_detallado: '',
      plan_tratamiento:      '',
      notas_medico:          '',
    },
  })

  const onSubmit = (values: ConsultaMedicaFormData) => {
    const ahora = new Date()
    registrar.mutate(
      {
        historia_clinica_id:  historiaClinicaId,
        agenda_medica_id:     turno.id,
        fecha_consulta:       formatFechaLocal(ahora),
        hora_consulta:        ahora.toTimeString().slice(0, 5),
        motivo_consulta:      values.motivo_consulta,
        examen_fisico:        values.examen_fisico || null,
        diagnostico_cie10:    diagnosticoCie10?.id ?? null,
        diagnostico_detallado: values.diagnostico_detallado,
        plan_tratamiento:     values.plan_tratamiento || null,
        notas_medico:         values.notas_medico || null,
      },
      {
        onSuccess: (consulta) => {
          setConsultaGuardada(consulta)
          onGuardada(consulta)
        },
      }
    )
  }

  return (
    <Grid>
      <Grid.Col span={{ base: 12, md: 4 }}>
        <PanelContextoPaciente
          turno={turno}
          historiaClinicaId={historiaClinicaId}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 8 }}>
        <Card withBorder radius="lg" p="xl">
          <form onSubmit={handleSubmit(onSubmit)}>
            <Stack gap="md">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="sm">
                  <Avatar
                    color={esServidor ? 'emerald' : 'blue'}
                    radius="xl"
                  >
                    {esServidor
                      ? <IconUser size={16} />
                      : <IconUsers size={16} />}
                  </Avatar>
                  <Stack gap={0}>
                    <Text size="sm" fw={600}>
                      {nombrePaciente.trim() || '—'}
                    </Text>
                    <Text size="xs" c="dimmed" ff="monospace">
                      {turno.folio}
                    </Text>
                  </Stack>
                </Group>
                <Badge size="sm" variant="light" color="blue">
                  {turno.tipo_atencion === 'medicina_general'
                    ? 'Medicina General' : 'Odontología'}
                </Badge>
              </Group>

              <Controller
                name="motivo_consulta"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Motivo de consulta"
                    placeholder="Motivo por el cual acude el paciente"
                    autosize
                    minRows={2}
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.motivo_consulta?.message}
                  />
                )}
              />

              <Controller
                name="examen_fisico"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Examen físico (opcional)"
                    placeholder="Hallazgos relevantes del examen físico"
                    autosize
                    minRows={2}
                    {...contained}
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                  />
                )}
              />

              <BuscarCie10Input
                value={diagnosticoCie10}
                onChange={setDiagnosticoCie10}
              />

              <Controller
                name="diagnostico_detallado"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Diagnóstico detallado"
                    placeholder="Descripción clínica del diagnóstico"
                    autosize
                    minRows={2}
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.diagnostico_detallado?.message}
                  />
                )}
              />

              <Controller
                name="plan_tratamiento"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Plan de tratamiento (opcional)"
                    placeholder="Indicaciones y tratamiento a seguir"
                    autosize
                    minRows={2}
                    {...contained}
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                  />
                )}
              />

              <Controller
                name="notas_medico"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Notas adicionales (opcional)"
                    autosize
                    minRows={2}
                    {...contained}
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                  />
                )}
              />

              <Group justify="space-between" mt="sm">
                <Group gap="xs">
                  <Button
                    variant="light"
                    color="emerald"
                    size="sm"
                    leftSection={<IconPill size={14} />}
                    onClick={abrirReceta}
                    disabled={!consultaGuardada}
                  >
                    Recetar
                  </Button>
                  <Button
                    variant="default"
                    size="sm"
                    leftSection={<IconCertificate size={14} />}
                    disabled
                  >
                    Certificado
                  </Button>
                </Group>

                <Group gap="xs">
                  <Button variant="default" onClick={onCancelar}>
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    color="emerald"
                    leftSection={<IconCheck size={14} />}
                    loading={registrar.isPending}
                  >
                    Guardar consulta
                  </Button>
                </Group>
              </Group>
            </Stack>
          </form>
        </Card>
      </Grid.Col>
      <RecetaModal
        opened={recetaOpened}
        onClose={cerrarReceta}
        turno={turno}
        consulta={consultaGuardada}
        onEmitida={cerrarReceta}
      />
    </Grid>
  )
}
