'use client'

import { useEffect } from 'react'
import { NumberInput, SegmentedControl, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { CAMPOS_CRITERIO, criterioSchema, type CriterioFormData } from '../../schemas/criterio.schema'
import type { SeccionCriterio, TipoInput } from '../../services/criterioService'
import { OpcionesCriterioCampos } from './OpcionesCriterioCampos'

const TIPOS: { value: TipoInput; label: string; ayuda: string }[] = [
  { value: 'radio',     label: 'Opción única',       ayuda: 'El evaluador elige UNA opción (ej.: nivel de instrucción).' },
  { value: 'checklist', label: 'Selección múltiple', ayuda: 'El evaluador puede marcar VARIAS opciones, que se suman hasta el máximo.' },
  { value: 'numero',    label: 'Valor numérico',     ayuda: 'El evaluador escribe el puntaje (ej.: nota de la prueba técnica).' },
]

interface Props {
  opened:         boolean
  onClose:        () => void
  seccionInicial: SeccionCriterio
  /** Para una convocatoria o para una plantilla: cambia a dónde se guarda. */
  onGuardar:      (datos: CriterioFormData) => Promise<unknown>
  enviando:       boolean
}

const vacio = (seccion: SeccionCriterio): CriterioFormData => ({
  seccion, nombre: '', descripcion: '', puntaje_maximo: 10, tipo_input: 'radio',
  opciones: [{ etiqueta: '', puntaje: 0 }],
})

/**
 * Agregar un criterio (2026-10-05). Antes había dos modales casi iguales, uno
 * por convocatoria y otro por plantilla, con la sección y las opciones en
 * `useState`; ahora es uno solo con React Hook Form y `useFieldArray`.
 */
export function CriterioFormModal({ opened, onClose, seccionInicial, onGuardar, enviando }: Props) {
  const contained = useContainedInput()
  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<CriterioFormData>({
    resolver: zodResolver(criterioSchema),
    defaultValues: vacio(seccionInicial),
  })
  const tipo = useWatch({ control, name: 'tipo_input' })

  // Cada vez que se abre, desde la sección del botón que lo abrió.
  useEffect(() => { if (opened) reset(vacio(seccionInicial)) }, [opened, seccionInicial, reset])

  const enviar = (d: CriterioFormData) =>
    onGuardar({ ...d, opciones: d.tipo_input === 'numero' ? [] : d.opciones })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS_CRITERIO, 'No se pudo agregar el criterio'))

  return (
    <FormModal opened={opened} onClose={onClose} title="Agregar criterio de evaluación" size="lg"
      onSubmit={handleSubmit(enviar)} submitLabel="Agregar criterio" submitting={enviando}>
      <Stack gap="md">
        <Controller name="seccion" control={control} render={({ field }) => (
          <Stack gap={4}>
            <Text size="sm" fw={500}>Sección</Text>
            <SegmentedControl fullWidth value={field.value} onChange={field.onChange} data={[
              { label: 'Méritos (hoja de vida)', value: 'meritos' },
              { label: 'Oposición (evaluación directa)', value: 'oposicion' },
            ]} />
          </Stack>
        )} />

        <TextInput label="Nombre del criterio" placeholder="Ej.: Instrucción formal, prueba técnica" required
          {...contained} {...register('nombre')} error={errors.nombre?.message} />

        <Textarea label="Descripción" placeholder="Qué se evalúa y cómo" autosize minRows={2}
          {...contained} {...register('descripcion')} error={errors.descripcion?.message} />

        <SectionHeading title="Configuración del criterio" />

        <Controller name="tipo_input" control={control} render={({ field }) => (
          <Stack gap={4}>
            <Text size="sm" fw={500}>Tipo de evaluación</Text>
            <SegmentedControl fullWidth value={field.value} onChange={field.onChange}
              data={TIPOS.map(({ value, label }) => ({ value, label }))} />
            <Text size="xs" c="dimmed">{TIPOS.find(t => t.value === field.value)?.ayuda}</Text>
          </Stack>
        )} />

        <Controller name="puntaje_maximo" control={control} render={({ field }) => (
          <NumberInput label="Puntaje máximo" description="Lo más que se puede obtener en este criterio. Entre todos, 100."
            min={0.5} max={100} decimalScale={2} required {...contained}
            value={field.value} onChange={(v) => field.onChange(v === '' ? 0 : Number(v))}
            error={errors.puntaje_maximo?.message} />
        )} />

        {tipo !== 'numero' && <OpcionesCriterioCampos control={control} register={register} errors={errors} />}
      </Stack>
    </FormModal>
  )
}
