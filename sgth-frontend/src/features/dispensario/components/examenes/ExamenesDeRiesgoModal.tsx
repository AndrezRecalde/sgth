'use client'

import { MultiSelect, Stack, Text } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogoExamenes } from '../../hooks/useExamenes'
import { useGuardarExamenesDeRiesgo } from '../../hooks/useExamenesPorRiesgo'
import { examenesDeRiesgoSchema, type ExamenesDeRiesgoFormData } from '../../schemas/examenAdmin.schema'

export interface FactorElegido {
  categoria:          string
  etiquetaCategoria:  string
  factor:             string
  examenes:           number[]
}

interface Props {
  /** El factor que se edita; sin él, el modal no se muestra. */
  factor:  FactorElegido | null
  onClose: () => void
}

/** Qué exámenes pide un factor de riesgo del puesto (D6). Nunca sensibles. */
export function ExamenesDeRiesgoModal({ factor, onClose }: Props) {
  return factor ? <Formulario key={`${factor.categoria}|${factor.factor}`} factor={factor} onClose={onClose} /> : null
}

function Formulario({ factor, onClose }: { factor: FactorElegido; onClose: () => void }) {
  const contained = useContainedInput()
  const catalogo = useCatalogoExamenes()
  const guardar = useGuardarExamenesDeRiesgo()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<ExamenesDeRiesgoFormData>({
    resolver: zodResolver(examenesDeRiesgoSchema),
    defaultValues: { examenes: factor.examenes },
  })

  // Por área, como la hoja, y sin los sensibles: lo que propone el riesgo del
  // puesto es ocupacional y un sensible no se exige para trabajar (D8).
  const areas = catalogo.data?.areas ?? []
  const datos = areas
    .map((a) => ({
      group: a.etiqueta,
      items: (catalogo.data?.examenes ?? [])
        .filter((e) => e.area === a.value && !e.sensible)
        .map((e) => ({ value: String(e.id), label: e.nombre })),
    }))
    .filter((g) => g.items.length > 0)

  const enviar = (v: ExamenesDeRiesgoFormData) =>
    guardar.mutateAsync({ categoria: factor.categoria, factor: factor.factor, examenes: v.examenes })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['examenes'], 'No se pudieron guardar los exámenes del factor'))

  return (
    <FormModal opened onClose={onClose} title="Exámenes del factor de riesgo" size="md"
      onSubmit={handleSubmit(enviar)} submitLabel="Guardar" submitting={guardar.isPending}>
      <Stack gap="sm">
        <Stack gap={0}>
          <Text size="xs" c="dimmed">{factor.etiquetaCategoria}</Text>
          <Text size="sm" fw={500}>{factor.factor}</Text>
        </Stack>
        <Controller name="examenes" control={control} render={({ field }) => (
          <MultiSelect label="Exámenes que pide" placeholder="Busque por nombre" searchable clearable
            description="La ficha ocupacional los propone al marcar este factor. Sin exámenes, no propone nada."
            nothingFoundMessage="Ningún examen coincide" maxDropdownHeight={280} {...contained}
            data={datos} value={field.value.map(String)} onChange={(v) => field.onChange(v.map(Number))}
            error={errors.examenes?.message} />
        )} />
      </Stack>
    </FormModal>
  )
}
