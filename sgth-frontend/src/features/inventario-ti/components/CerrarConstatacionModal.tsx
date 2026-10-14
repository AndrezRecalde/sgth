'use client'

import { Stack, Text, Textarea } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { plural } from '@/lib/plural'
import { useConstatacionMutations } from '../hooks/useConstataciones'
import { cierreConstatacionSchema, type CierreConstatacionFormData } from '../schemas/constatacion.schema'
import type { DetalleConstatacion } from '../services/constatacionesService'

interface Props {
  constatacion: DetalleConstatacion | null
  onClose: () => void
}

/** Cerrar la constatación: lo pendiente queda como no encontrado y ya no se puede escanear. */
export function CerrarConstatacionModal({ constatacion, onClose }: Props) {
  return constatacion ? <Formulario constatacion={constatacion} onClose={onClose} /> : null
}

function Formulario({ constatacion: c, onClose }: { constatacion: DetalleConstatacion; onClose: () => void }) {
  const contained = useContainedInput()
  const { cerrar } = useConstatacionMutations(c.id)
  const { register, handleSubmit, setError, formState: { errors } } = useForm<CierreConstatacionFormData>({
    resolver: zodResolver(cierreConstatacionSchema),
    defaultValues: { observaciones: c.observaciones ?? '' },
  })
  const pendientes = c.resumen.pendientes

  const enviar = (v: CierreConstatacionFormData) =>
    cerrar.mutateAsync(v.observaciones || null).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['observaciones'], 'No se pudo cerrar la constatación'))

  return (
    <FormModal opened onClose={onClose} title={`Cerrar «${c.nombre}»`} onSubmit={handleSubmit(enviar)}
      submitLabel="Cerrar la constatación" submitting={cerrar.isPending} destructiva={pendientes > 0}>
      <Stack gap="md">
        <Text size="sm">
          {pendientes > 0
            ? <>Quedan <b>{plural(pendientes, 'equipo', 'equipos')}</b> sin escanear: constarán como no encontrados en el acta.</>
            : 'Se escanearon todos los equipos esperados.'}
          {' '}Después ya no se podrá escanear.
        </Text>
        <Textarea label="Observaciones" placeholder="Opcional. Ej.: La sala de reuniones estaba cerrada." autosize minRows={2}
          error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
      </Stack>
    </FormModal>
  )
}
