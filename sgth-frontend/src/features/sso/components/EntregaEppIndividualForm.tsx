'use client'

import {
  Group, NumberInput, Select, Stack, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { ModalFooter } from '@/components/ui'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useEppEntregaMutations } from '../hooks/useEppEntregas'
import { useEquiposProteccion } from '../hooks/useEquiposProteccion'
import {
  eppEntregaSchema, type EppEntregaFormData, MOTIVO_ENTREGA_OPTIONS,
} from '../schemas/eppEntrega.schema'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { toDateValue, fromDateValue } from '@/lib/fecha'

const VALORES_INICIALES: EppEntregaFormData = {
  servidor_id: 0,
  equipo_proteccion_id: 0,
  fecha_entrega: '',
  cantidad: 1,
  motivo: 'entrega',
  observaciones: '',
}

interface Props {
  onListo: () => void
  onCancelar: () => void
}

/** Un movimiento de EPP: una entrega, una devolución o una reposición. */
export function EntregaEppIndividualForm({ onListo, onCancelar }: Props) {
  const contained = useContainedInput()
  const { registrar } = useEppEntregaMutations()

  const { data: equiposData, error: errorEquipos } = useEquiposProteccion({ estado: true })
  const equipoOptions = (equiposData?.data ?? []).map(e => ({
    value: String(e.id), label: `${e.codigo} — ${e.nombre}`,
  }))

  const {
    register, control, handleSubmit, setError,
    formState: { errors },
  } = useForm<EppEntregaFormData>({
    resolver: zodResolver(eppEntregaSchema) as Resolver<EppEntregaFormData>,
    defaultValues: VALORES_INICIALES,
  })

  const guardar = (valores: EppEntregaFormData) => {
    registrar.mutateAsync(valores)
      .then(onListo)
      .catch((error) => {
        // El 422 del backend al campo que lo provoca; si no trae campos, el
        // hook ya lo notificó.
        const campos = erroresDeCampo(error)
        if (!campos) return
        for (const [campo, mensaje] of Object.entries(campos)) {
          setError(campo as keyof EppEntregaFormData, { message: mensaje })
        }
      })
  }

  return (
    <form onSubmit={handleSubmit(guardar)} noValidate>
      <Stack gap="sm">
        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Servidor"
              required
              value={field.value || null}
              onChange={(id) => field.onChange(id ?? 0)}
              error={errors.servidor_id?.message}
            />
          )}
        />
        <Controller
          name="equipo_proteccion_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Equipo de protección"
              placeholder="Seleccione un equipo"
              data={equipoOptions}
              searchable
              required
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : 0)}
              error={
                errors.equipo_proteccion_id?.message
                ?? (errorEquipos ? 'No se pudo cargar el catálogo de equipos de protección.' : undefined)
              }
            />
          )}
        />
        <Group grow>
          <Controller
            name="fecha_entrega"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                required
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                error={errors.fecha_entrega?.message}
              />
            )}
          />
          <Controller
            name="cantidad"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Cantidad"
                min={1}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                error={errors.cantidad?.message}
              />
            )}
          />
        </Group>
        <Controller
          name="motivo"
          control={control}
          render={({ field }) => (
            <Select
              label="Motivo"
              data={MOTIVO_ENTREGA_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v as EppEntregaFormData['motivo'])}
              error={errors.motivo?.message}
            />
          )}
        />
        <Textarea
          label="Observaciones"
          placeholder="Observaciones (opcional)"
          rows={2}
          {...contained}
          {...register('observaciones')}
          error={errors.observaciones?.message}
        />
        <ModalFooter
          onCancel={onCancelar}
          submitLabel="Registrar"
          submitting={registrar.isPending}
        />
      </Stack>
    </form>
  )
}
