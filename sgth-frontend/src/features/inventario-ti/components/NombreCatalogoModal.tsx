'use client'

import { TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogoMutations } from '../hooks/useCatalogosInventario'
import { nombreSchema, type NombreFormData } from '../schemas/catalogo.schema'
import { catalogosService } from '../services/catalogosService'

type Registro = { id: number; nombre: string }

interface Props {
  opened: boolean
  onClose: () => void
  /** Una marca, o un subtipo del tipo `tipoBienId`. */
  catalogo: 'marca' | 'subtipo'
  registro: Registro | null
  tipoBienId?: number
}

const TEXTOS = {
  marca:   { nuevo: 'Nueva marca', editar: 'Editar marca', ejemplo: 'Ej.: Dell', guardar: 'Registrar marca' },
  subtipo: { nuevo: 'Nuevo subtipo', editar: 'Editar subtipo', ejemplo: 'Ej.: Todo en uno', guardar: 'Registrar subtipo' },
}

/** Los catálogos que solo tienen nombre: marcas y subtipos. */
export function NombreCatalogoModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, catalogo, registro, tipoBienId }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const textos = TEXTOS[catalogo]
  const marca = useCatalogoMutations('marca', catalogosService.marca)
  const subtipo = useCatalogoMutations('subtipo', catalogosService.subtipo)
  const pendiente = marca.guardar.isPending || subtipo.guardar.isPending

  const { register, handleSubmit, setError, formState: { errors } } = useForm<NombreFormData>({
    resolver: zodResolver(nombreSchema),
    defaultValues: { nombre: registro?.nombre ?? '' },
  })

  const enviar = (v: NombreFormData) =>
    (catalogo === 'marca'
      ? marca.guardar.mutateAsync({ id: registro?.id, data: v })
      : subtipo.guardar.mutateAsync({ id: registro?.id, data: { ...v, tipo_bien_id: tipoBienId ?? 0 } }))
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['nombre'], `No se pudo guardar ${catalogo === 'marca' ? 'la marca' : 'el subtipo'}`))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={registro ? textos.editar : textos.nuevo}
      onSubmit={handleSubmit(enviar)}
      submitLabel={registro ? 'Guardar cambios' : textos.guardar}
      submitting={pendiente}
    >
      <TextInput label="Nombre" placeholder={textos.ejemplo} withAsterisk
        error={errors.nombre?.message} {...contained} {...register('nombre')} />
    </FormModal>
  )
}
