'use client'

import { Select, Stack, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useRouter } from 'next/navigation'
import { FormModal } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCrearPlantilla } from '../hooks/usePlantilla'
import { CAMPOS_PLANTILLA, plantillaSchema, type PlantillaFormData } from '../schemas/plantilla.schema'
import { TIPO_CONTRATO_PLANTILLA_OPTIONS } from '../services/plantillaService'

const VACIO: PlantillaFormData = { nombre: '', descripcion: '', tipo_contrato: null }

/** Crear una plantilla y abrirla para agregarle criterios. */
export function NuevaPlantillaModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  const router = useRouter()
  const contained = useContainedInput()
  const crear = useCrearPlantilla()
  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<PlantillaFormData>({
    resolver: zodResolver(plantillaSchema),
    defaultValues: VACIO,
  })

  const cerrar = () => { reset(VACIO); onClose() }

  const enviar = (v: PlantillaFormData) =>
    crear.mutateAsync({ ...v, descripcion: v.descripcion || null })
      .then((p) => { cerrar(); router.push(ROUTES.SGTH.PLANTILLA(p.id)) })
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS_PLANTILLA, 'No se pudo crear la plantilla'))

  return (
    <FormModal opened={opened} onClose={cerrar} title="Nueva plantilla de evaluación" size="md"
      onSubmit={handleSubmit(enviar)} submitLabel="Crear plantilla" submitting={crear.isPending}>
      <Stack gap="sm">
        <TextInput label="Nombre de la plantilla" placeholder="Ej.: Concurso LOSEP estándar" required
          {...contained} {...register('nombre')} error={errors.nombre?.message} />
        <Textarea label="Descripción" placeholder="Describa cuándo usar esta plantilla" autosize minRows={2}
          {...contained} {...register('descripcion')} error={errors.descripcion?.message} />
        <Controller name="tipo_contrato" control={control} render={({ field }) => (
          <Select label="Tipo de contrato" description="Ayuda a encontrar la plantilla según el tipo de convocatoria"
            data={TIPO_CONTRATO_PLANTILLA_OPTIONS} clearable {...contained}
            value={field.value} onChange={field.onChange} error={errors.tipo_contrato?.message} />
        )} />
      </Stack>
    </FormModal>
  )
}
