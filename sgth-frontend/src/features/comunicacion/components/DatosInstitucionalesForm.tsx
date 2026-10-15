'use client'

import { Button, Grid, Group, TextInput } from '@mantine/core'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { formatFechaMesHora } from '@/lib/fecha'
import { useMutacionesIdentidad } from '../hooks/useIdentidad'
import { datosInstitucionalesSchema, type DatosInstitucionalesFormData } from '../schemas/datosInstitucionales.schema'
import type { EstadoIdentidad } from '../services/tiposIdentidad'

interface Props {
  datos: EstadoIdentidad['datos']
}

/**
 * Los datos de la institución (docs/diseno-comunicacion-social.md, 4.5): salen
 * en la cabecera y el pie de los documentos, en la receta y en el sidebar.
 */
export function DatosInstitucionalesForm({ datos }: Props) {
  const contained = useContainedInput()
  const { actualizarDatos } = useMutacionesIdentidad()

  const { register, handleSubmit, reset, setError, formState: { errors, isDirty } } = useForm<DatosInstitucionalesFormData>({
    resolver: zodResolver(datosInstitucionalesSchema),
    defaultValues: {
      nombre_oficial: datos.nombre_oficial, siglas: datos.siglas, nombre_corto: datos.nombre_corto,
      ruc: datos.ruc, direccion: datos.direccion, telefono: datos.telefono, sitio_web: datos.sitio_web ?? '',
    },
  })

  const guardar = (valores: DatosInstitucionalesFormData) =>
    actualizarDatos.mutateAsync({ ...valores, sitio_web: valores.sitio_web || null })
      // Lo guardado pasa a ser lo «sin cambios»: el botón vuelve a apagarse.
      .then(() => reset(valores))
      .catch((error) => erroresAlFormulario(error, setError, Object.keys(datosInstitucionalesSchema.shape), null))

  return (
    <SectionCard
      title="Datos de la institución"
      description={datos.actualizado_por
        ? `Última actualización: ${formatFechaMesHora(datos.actualizado_en)} · ${datos.actualizado_por.nombre}`
        : 'Los que estaban escritos en el sistema antes de este módulo. Las siglas y el nombre corto van en el sidebar.'}
    >
      <form noValidate onSubmit={handleSubmit(guardar)}>
        <Grid gap="md">
          <Grid.Col span={12}>
            <TextInput label="Nombre oficial" {...contained} {...register('nombre_oficial')} error={errors.nombre_oficial?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="Siglas" placeholder="Ej: GADPE" {...contained} {...register('siglas')} error={errors.siglas?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="Nombre corto" placeholder="Ej: Esmeraldas" {...contained}
              {...register('nombre_corto')} error={errors.nombre_corto?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="RUC" placeholder="13 dígitos" {...contained} {...register('ruc')} error={errors.ruc?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 8 }}>
            <TextInput label="Dirección" {...contained} {...register('direccion')} error={errors.direccion?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="Teléfono" placeholder="Ej: 06-2721433" {...contained} {...register('telefono')} error={errors.telefono?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput label="Sitio web" placeholder="Ej: www.gadpe.gob.ec" {...contained} {...register('sitio_web')} error={errors.sitio_web?.message} />
          </Grid.Col>
        </Grid>
        <Group justify="flex-end" mt="md">
          <Button type="submit" loading={actualizarDatos.isPending} disabled={!isDirty}>Guardar los datos</Button>
        </Group>
      </form>
    </SectionCard>
  )
}
