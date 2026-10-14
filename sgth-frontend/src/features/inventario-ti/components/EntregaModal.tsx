'use client'

import { useState } from 'react'
import { Grid, Select, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { useCustodiaMutations } from '../hooks/useCustodia'
import { useUbicacionSugerida } from '../hooks/useUbicaciones'
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
 * Mientras no se elija otra, se propone como ubicación la habitual de la
 * unidad del custodio.
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
      memorando_numero: '', soporte_anterior: '', observaciones: '', ubicacion_id: bien.ubicacion?.id ?? null,
    },
  })
  const servidorId = useWatch({ control, name: 'servidor_id' })
  const { data: sugerida } = useUbicacionSugerida({ servidorId: Number.isNaN(servidorId) ? null : servidorId })
  // Hasta que se elige a mano, manda la sugerida (si la unidad tiene una).
  const [elegida, setElegida] = useState(false)
  const ubicacionDe = (actual: number | null) => (elegida ? actual : sugerida?.id ?? actual)

  const nulo = (v: string) => (v.trim() === '' ? null : v.trim())
  const enviar = (v: EntregaFormData) => {
    const data = {
      servidor_id: v.servidor_id, usuario_servidor_id: v.usuario_servidor_id, fecha_asignacion: v.fecha_asignacion,
      memorando_numero: nulo(v.memorando_numero), soporte_anterior: nulo(v.soporte_anterior), observaciones: nulo(v.observaciones),
      ubicacion_id: ubicacionDe(v.ubicacion_id),
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
        <Grid.Col span={12}>
          <Controller name="ubicacion_id" control={control} render={({ field }) => {
            const valor = ubicacionDe(field.value)
            return (
              <Select label="Dónde queda el equipo" placeholder="Sede y piso" searchable clearable {...contained}
                error={errors.ubicacion_id?.message}
                data={(catalogos?.ubicaciones ?? []).map((u) => ({ value: String(u.id), label: u.piso ? `${u.sede} · ${u.piso}` : u.sede }))}
                value={valor ? String(valor) : null}
                onChange={(v) => { setElegida(true); field.onChange(v ? Number(v) : null) }} />
            )
          }} />
          {!elegida && sugerida && (
            <Text size="xs" c="dimmed" mt={4}>Propuesta: la ubicación habitual de la unidad del custodio.</Text>
          )}
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
