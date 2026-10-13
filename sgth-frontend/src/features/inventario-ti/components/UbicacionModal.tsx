'use client'

import { Grid, TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogoMutations } from '../hooks/useCatalogosInventario'
import { ubicacionSchema, type UbicacionFormData } from '../schemas/catalogo.schema'
import { catalogosService } from '../services/catalogosService'
import type { Ubicacion } from '../services/tipos'

interface Props {
  opened: boolean
  onClose: () => void
  ubicacion: Ubicacion | null
}

/** Una sede y, si tiene, el piso. Juntos no se repiten. */
export function UbicacionModal({ opened, onClose, ubicacion }: Props) {
  return opened ? <Formulario onClose={onClose} ubicacion={ubicacion} /> : null
}

function Formulario({ onClose, ubicacion }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { guardar } = useCatalogoMutations('ubicacion', catalogosService.ubicacion)
  const { register, handleSubmit, setError, formState: { errors } } = useForm<UbicacionFormData>({
    resolver: zodResolver(ubicacionSchema),
    defaultValues: { sede: ubicacion?.sede ?? '', piso: ubicacion?.piso ?? '', descripcion: ubicacion?.descripcion ?? '' },
  })

  const enviar = (v: UbicacionFormData) =>
    guardar.mutateAsync({
      id: ubicacion?.id,
      data: { sede: v.sede, piso: v.piso || null, descripcion: v.descripcion || null },
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(ubicacionSchema.shape), 'No se pudo guardar la ubicación'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={ubicacion ? 'Editar ubicación' : 'Nueva ubicación'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={ubicacion ? 'Guardar cambios' : 'Registrar ubicación'}
      submitting={guardar.isPending}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput label="Sede" placeholder="Ej.: Edificio principal" withAsterisk
            error={errors.sede?.message} {...contained} {...register('sede')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TextInput label="Piso" placeholder="Ej.: 3 o PB"
            error={errors.piso?.message} {...contained} {...register('piso')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <TextInput label="Descripción" placeholder="Opcional. Ej.: Bodega de TIC"
            error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
