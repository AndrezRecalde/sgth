'use client'

import {
  Button, Group, Select, SimpleGrid, Stack, Text, Textarea,
} from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarCie10Input } from './BuscarCie10Input'
import { RichTextInput } from './RichTextInput'
import {
  TIPO_ATENCION_OPTIONS,
  TIPO_DIAGNOSTICO_OPTIONS,
  type ConsultaMedicaFormData,
} from '../schemas/consultaMedica.schema'
import type { DiagnosticoCie10 } from '../services/cie10Service'

const MAX_SECUNDARIOS = 3

interface Props {
  form: UseFormReturn<ConsultaMedicaFormData>
  cie10Principal: DiagnosticoCie10 | null
  onCie10Principal: (d: DiagnosticoCie10 | null) => void
  cie10Secundarios: DiagnosticoCie10[]
  onCie10Secundarios: (
    actualizar: (previos: DiagnosticoCie10[]) => DiagnosticoCie10[],
  ) => void
}

/** Lo que el médico escribe de la consulta: clasificación, relato y diagnóstico. */
export function ConsultaCampos({
  form,
  cie10Principal,
  onCie10Principal,
  cie10Secundarios,
  onCie10Secundarios,
}: Props) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form

  return (
    <>
      {/* Una columna en el teléfono: con `Group grow` los dos selectores se
          repartían 375px y «Tipo de diagnóstico» no cabía en su control. */}
      <SimpleGrid cols={{ base: 1, sm: 2 }}>
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
      </SimpleGrid>

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
        <BuscarCie10Input value={cie10Principal} onChange={onCie10Principal} />
        <Text size="xs" c="dimmed">
          Diagnóstico principal (CIE-10)
        </Text>
      </Stack>

      <Stack gap="xs">
        <Text size="sm" fw={500}>
          Diagnósticos secundarios{' '}
          <Text span size="xs" c="dimmed">
            (opcional, máx. {MAX_SECUNDARIOS})
          </Text>
        </Text>

        {cie10Secundarios.length < MAX_SECUNDARIOS && (
          <BuscarCie10Input
            value={null}
            onChange={(d) => {
              if (d) {
                onCie10Secundarios((previos) =>
                  previos.find((s) => s.id === d.id) ? previos : [...previos, d],
                )
              }
            }}
          />
        )}

        {cie10Secundarios.map((d) => (
          <Group key={d.id} gap={6}>
            <Text size="xs" ff="monospace" c="dimmed">
              {d.codigo}
            </Text>
            <Text size="xs">{d.descripcion}</Text>
            <Button
              size="compact-xs"
              variant="subtle"
              color="red"
              aria-label={`Quitar ${d.codigo}`}
              onClick={() =>
                onCie10Secundarios((previos) =>
                  previos.filter((s) => s.id !== d.id),
                )
              }
            >
              ×
            </Button>
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
    </>
  )
}
