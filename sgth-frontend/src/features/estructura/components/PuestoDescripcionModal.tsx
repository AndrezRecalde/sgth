'use client'

import { useEffect } from 'react'
import { Select, Stack, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { NIVELES_DEL_PUESTO } from '../constants/perfilPuesto'
import { useGuardarDescripcion } from '../hooks/useDescripcionPuesto'
import { descripcionPuestoSchema, type DescripcionPuestoFormData } from '../schemas/descripcionPuesto.schema'
import type { DescripcionPuesto, DescripcionPuestoDatos } from '../services/descripcionPuestoService'
import { DescripcionRequisitosCampos } from './DescripcionRequisitosCampos'

interface Props {
  opened:      boolean
  onClose:     () => void
  puestoId:    number
  descripcion: DescripcionPuesto
}

const CAMPOS_DE_TEXTO = [
  'mision', 'relaciones', 'subproceso', 'area_conocimiento', 'especificidad_experiencia', 'capacitacion',
] as const

const alFormulario = (d: DescripcionPuesto): DescripcionPuestoFormData => ({
  mision: d.mision ?? '',
  relaciones: d.relaciones ?? '',
  subproceso: d.subproceso ?? '',
  nivel: d.nivel ?? null,
  area_conocimiento: d.area_conocimiento ?? '',
  especificidad_experiencia: d.especificidad_experiencia ?? '',
  capacitacion: d.capacitacion ?? '',
  requisitos: d.requisitos.map((r) => ({
    nivel_instruccion: r.nivel_instruccion,
    detalle: r.detalle ?? '',
    anios: Math.floor(r.meses_experiencia / 12),
    meses: r.meses_experiencia % 12,
  })),
})

/** Lo vacío viaja como `null`, y la experiencia, en meses. */
const alBackend = (v: DescripcionPuestoFormData): DescripcionPuestoDatos => ({
  ...Object.fromEntries(CAMPOS_DE_TEXTO.map((c) => [c, v[c] || null])) as Pick<DescripcionPuestoDatos, typeof CAMPOS_DE_TEXTO[number]>,
  nivel: v.nivel,
  requisitos: v.requisitos.map((r) => ({
    nivel_instruccion: r.nivel_instruccion,
    detalle: r.nivel_instruccion === 'estudios_superiores_en_curso' ? r.detalle || null : null,
    meses_experiencia: r.anios * 12 + r.meses,
  })),
})

/** La descripción del puesto entera, como la ficha del Manual de Puestos (P1). */
export function PuestoDescripcionModal({ opened, onClose, puestoId, descripcion }: Props) {
  const contained = useContainedInput()
  const guardar = useGuardarDescripcion(puestoId)
  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<DescripcionPuestoFormData>({
    resolver: zodResolver(descripcionPuestoSchema),
    defaultValues: alFormulario(descripcion),
  })

  // Cada vez que se abre, con lo guardado.
  useEffect(() => {
    if (opened) reset(alFormulario(descripcion))
  }, [opened, descripcion, reset])

  const enviar = (valores: DescripcionPuestoFormData) =>
    guardar.mutateAsync(alBackend(valores))
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, [
        ...CAMPOS_DE_TEXTO, 'nivel',
        ...valores.requisitos.flatMap((_, i) => [`requisitos.${i}.nivel_instruccion`, `requisitos.${i}.detalle`]),
      ], null))

  const texto = (campo: typeof CAMPOS_DE_TEXTO[number], label: string, placeholder: string, filas = 3) => (
    <Textarea label={label} placeholder={placeholder} autosize minRows={filas} {...contained}
      {...register(campo)} error={errors[campo]?.message} />
  )

  return (
    <FormModal opened={opened} onClose={onClose} title="Descripción del puesto" size="lg"
      onSubmit={handleSubmit(enviar)} submitLabel="Guardar descripción" submitting={guardar.isPending}>
      <Stack gap="md">
        <SectionHeading title="Misión y relaciones" />
        {texto('mision', 'Misión', 'Para qué existe el puesto: qué hace y con qué fin')}
        {texto('relaciones', 'Relaciones internas y externas', 'Ej: Internas: todas las unidades. Externas: proveedores, ciudadanía', 2)}
        <TextInput label="Subproceso" placeholder="Ej: Soporte Técnico" description="El puesto sigue en su gestión; esto lo ubica dentro de ella."
          {...contained} {...register('subproceso')} error={errors.subproceso?.message} />

        <SectionHeading title="Requisitos" />
        <DescripcionRequisitosCampos control={control} register={register} errors={errors} />
        <Controller name="nivel" control={control} render={({ field }) => (
          <Select label="Nivel del puesto" placeholder="Según la instrucción" clearable data={NIVELES_DEL_PUESTO}
            description={descripcion.nivel_sugerido
              ? `Vacío, se toma de la instrucción: ${NIVELES_DEL_PUESTO.find((n) => n.value === descripcion.nivel_sugerido)?.label.toLowerCase()}.`
              : 'Vacío, se toma de la instrucción: profesional si alguna alternativa pide tercer o cuarto nivel.'}
            {...contained} value={field.value ?? null}
            onChange={(v) => field.onChange(NIVELES_DEL_PUESTO.find((n) => n.value === v)?.value ?? null)}
            error={errors.nivel?.message} />
        )} />
        {texto('area_conocimiento', 'Área de conocimiento', 'Ej: Sistemas, Informática, Telecomunicaciones', 2)}
        {texto('especificidad_experiencia', 'Especificidad de la experiencia', 'Ej: Soporte técnico y administración de redes', 2)}

        <SectionHeading title="Capacitación" />
        {texto('capacitacion', 'Temáticas de capacitación', 'Ej: Administración de redes, seguridad informática')}
      </Stack>
    </FormModal>
  )
}
