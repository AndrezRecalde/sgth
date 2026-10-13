'use client'

import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DatePickerInput } from '@mantine/dates'
import { FormModal, MotivoModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { ZonaArchivo } from '@/features/expediente/components/ZonaArchivo'
import { useBajaMutations } from '../hooks/useBajas'
import { entregaActivosSchema, type EntregaActivosFormData } from '../schemas/baja.schema'
import { memorandoSchema, type MemorandoFormData } from '../schemas/custodia.schema'
import type { BajaBien } from '../services/tiposBaja'
import { AprobarBajaModal } from './AprobarBajaModal'

/** Lo que se hace sobre una baja desde el listado o la ficha del equipo. */
export type AccionBaja = { tipo: 'aprobar' | 'rechazar' | 'memorando' | 'entrega'; baja: BajaBien } | null

interface Props {
  accion: AccionBaja
  onClose: () => void
}

/** Los modales de la baja: uno abierto a la vez, según la acción elegida. */
export function BajaModales({ accion, onClose }: Props) {
  const { rechazar } = useBajaMutations()

  if (!accion) return null
  const { tipo, baja } = accion

  if (tipo === 'aprobar') return <AprobarBajaModal baja={baja} onClose={onClose} />
  if (tipo === 'memorando') return <MemorandoBaja baja={baja} onClose={onClose} />
  if (tipo === 'entrega') return <EntregaActivos baja={baja} onClose={onClose} />

  return (
    <MotivoModal
      opened
      onClose={onClose}
      title={`Rechazar la baja ${baja.numero}`}
      descripcion={<>El equipo <b>{baja.bien?.codigo_institucional}</b> sigue en el inventario. El técnico verá el motivo.</>}
      confirmLabel="Rechazar"
      destructiva
      cargando={rechazar.isPending}
      onConfirm={(motivo) => rechazar.mutate({ id: baja.id, motivo }, { onSuccess: onClose })}
    />
  )
}

function MemorandoBaja({ baja, onClose }: { baja: BajaBien; onClose: () => void }) {
  const { memorando } = useBajaMutations()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<MemorandoFormData>({
    resolver: zodResolver(memorandoSchema),
  })

  const enviar = (v: MemorandoFormData) =>
    memorando.mutateAsync({ id: baja.id, archivo: v.archivo })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['archivo'], null))

  return (
    <FormModal opened onClose={onClose} title={baja.tiene_memorando ? 'Reemplazar el memorando' : 'Adjuntar el memorando'}
      onSubmit={handleSubmit(enviar)} submitLabel="Adjuntar" submitting={memorando.isPending}>
      <Controller name="archivo" control={control} render={({ field }) => (
        <ZonaArchivo value={field.value} onChange={field.onChange} error={errors.archivo?.message}
          onRechazo={(m) => notificar.error('El memorando no sirve', m)}
          accept={['application/pdf']} maxMb={10} formatos="PDF — máx. 10 MB" />
      )} />
    </FormModal>
  )
}

/** La entrega física del equipo a Activos Fijos y Bodega, después de la baja. */
function EntregaActivos({ baja, onClose }: { baja: BajaBien; onClose: () => void }) {
  const contained = useContainedInput()
  const { entrega } = useBajaMutations()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<EntregaActivosFormData>({
    resolver: zodResolver(entregaActivosSchema),
    defaultValues: { fecha: hoyIso() },
  })

  const enviar = (v: EntregaActivosFormData) =>
    entrega.mutateAsync({ id: baja.id, fecha: v.fecha })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['fecha'], null))

  return (
    <FormModal opened onClose={onClose} title="Entrega a Activos Fijos" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar la entrega" submitting={entrega.isPending}>
      <Controller name="fecha" control={control} render={({ field }) => (
        <DatePickerInput label="Fecha de la entrega" valueFormat="DD/MM/YYYY" withAsterisk
          minDate={baja.fecha_baja ?? undefined} maxDate={hoyIso()} error={errors.fecha?.message} {...contained}
          value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
      )} />
    </FormModal>
  )
}
