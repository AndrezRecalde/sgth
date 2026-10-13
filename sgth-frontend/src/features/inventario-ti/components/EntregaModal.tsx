'use client'

import { Grid, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { useCustodiaMutations } from '../hooks/useCustodia'
import { entregaSchema, type EntregaFormData } from '../schemas/custodia.schema'
import type { Asignacion, BienInformatico } from '../services/tipos'

const CAMPOS = Object.keys(entregaSchema.shape)

interface Props {
  opened: boolean
  onClose: () => void
  bien: BienInformatico
  /** La custodia vigente que se traspasa; sin ella, es una entrega. */
  traspasarDe?: Asignacion | null
}

/**
 * Entregar el equipo en custodia, o traspasar la vigente a otra persona. El
 * memorando se hace en Quipux: aquí va su número y el PDF se adjunta después.
 */
export function EntregaModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, bien, traspasarDe }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { entregar, traspasar } = useCustodiaMutations()
  const { data: catalogos } = useCatalogosFormulario()
  const traspaso = !!traspasarDe

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<EntregaFormData>({
    resolver: zodResolver(entregaSchema),
    defaultValues: {
      servidor_id: Number.NaN, usuario_servidor_id: null, fecha_asignacion: hoyIso(),
      memorando_numero: '', soporte_anterior: '', observaciones: '',
    },
  })

  const nulo = (v: string) => (v.trim() === '' ? null : v.trim())
  const enviar = (v: EntregaFormData) => {
    const data = {
      servidor_id: v.servidor_id, usuario_servidor_id: v.usuario_servidor_id, fecha_asignacion: v.fecha_asignacion,
      memorando_numero: nulo(v.memorando_numero), soporte_anterior: nulo(v.soporte_anterior), observaciones: nulo(v.observaciones),
    }
    const peticion = traspasarDe
      ? traspasar.mutateAsync({ id: traspasarDe.id, data })
      : entregar.mutateAsync({ ...data, bien_informatico_id: bien.id })

    return peticion.then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={traspaso ? 'Traspasar la custodia' : 'Entregar en custodia'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={traspaso ? 'Registrar traspaso' : 'Registrar entrega'}
      submitting={entregar.isPending || traspasar.isPending}
      size="lg"
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <Controller name="servidor_id" control={control} render={({ field }) => (
            <BuscarServidorSelect label={traspaso ? 'Nuevo custodio' : 'Custodio'} required
              value={Number.isNaN(field.value) ? null : field.value}
              onChange={(id) => field.onChange(id ?? Number.NaN)} error={errors.servidor_id?.message} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller name="fecha_asignacion" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
              error={errors.fecha_asignacion?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="usuario_servidor_id" control={control} render={({ field }) => (
            <BuscarServidorSelect label="Usuario del equipo, si no es el custodio" value={field.value}
              onChange={field.onChange} error={errors.usuario_servidor_id?.message} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: catalogos?.acepta_soporte_anterior ? 8 : 12 }}>
          <TextInput label="Número de memorando" placeholder="Ej.: Memorando Nº 066 GADPE-HAP-GTIC-2026"
            error={errors.memorando_numero?.message} {...contained} {...register('memorando_numero')} />
        </Grid.Col>
        {catalogos?.acepta_soporte_anterior && (
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput label="Soporte (sistema anterior)" placeholder="Ej.: 48618"
              error={errors.soporte_anterior?.message} {...contained} {...register('soporte_anterior')} />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <Textarea label="Observaciones" placeholder="Opcional. Ej.: Se entrega con cargador y maletín."
            autosize minRows={2} error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
