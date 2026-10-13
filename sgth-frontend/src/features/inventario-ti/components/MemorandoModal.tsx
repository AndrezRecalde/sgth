'use client'

import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ZonaArchivo } from '@/features/expediente/components/ZonaArchivo'
import { useCustodiaMutations } from '../hooks/useCustodia'
import { memorandoSchema, type MemorandoFormData } from '../schemas/custodia.schema'
import type { Asignacion } from '../services/tipos'

interface Props {
  asignacion: Asignacion | null
  onClose: () => void
}

/** El PDF del memorando de Quipux. Reemplaza al anterior: hay uno por custodia. */
export function MemorandoModal({ asignacion, onClose }: Props) {
  return asignacion ? <Formulario asignacion={asignacion} onClose={onClose} /> : null
}

function Formulario({ asignacion, onClose }: { asignacion: Asignacion; onClose: () => void }) {
  const { memorando } = useCustodiaMutations()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<MemorandoFormData>({
    resolver: zodResolver(memorandoSchema),
  })

  const enviar = (v: MemorandoFormData) =>
    memorando.mutateAsync({ id: asignacion.id, archivo: v.archivo })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['archivo'], null))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={asignacion.tiene_memorando_pdf ? 'Reemplazar el memorando' : 'Adjuntar el memorando'}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Adjuntar"
      submitting={memorando.isPending}
    >
      <Controller name="archivo" control={control} render={({ field }) => (
        <ZonaArchivo
          value={field.value}
          onChange={field.onChange}
          error={errors.archivo?.message}
          onRechazo={(m) => notificar.error('El memorando no sirve', m)}
          accept={['application/pdf']}
          maxMb={10}
          formatos="PDF — máx. 10 MB"
        />
      )} />
    </FormModal>
  )
}
