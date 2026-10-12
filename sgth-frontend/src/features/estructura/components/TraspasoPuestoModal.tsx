'use client'

import { Alert, List, Select, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useTodasUnidades } from '../hooks/useUnidades'
import { usePrepararTraspaso, useVistaPreviaTraspaso } from '../hooks/useTraspasoPuesto'
import { traspasoPuestoSchema, type TraspasoPuestoFormData } from '../schemas/traspasoPuesto.schema'
import type { PuestoConRelaciones, UnidadConRelaciones } from '@/types/api'

interface Props {
  puesto: PuestoConRelaciones | null
  onClose: () => void
}

/**
 * Traspasar un puesto ocupado a otra unidad (LOSEP Art. 37; Reglamento a la
 * LOSEP Art. 69; fase 7.1a). El puesto pasa con su partida y sus ocupantes, y
 * cada uno recibe su acción de personal en un lote, que se suscribe y registra
 * entero desde Acciones de Personal.
 */
export function TraspasoPuestoModal({ puesto, onClose }: Props) {
  return puesto ? <Formulario puesto={puesto} onClose={onClose} /> : null
}

function Formulario({ puesto, onClose }: { puesto: PuestoConRelaciones; onClose: () => void }) {
  const contained = useContainedInput()
  const preparar = usePrepararTraspaso()
  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<TraspasoPuestoFormData>({
    resolver: zodResolver(traspasoPuestoSchema),
    defaultValues: { informe_uath_numero: '', descripcion: '', fecha_efectiva: hoyIso() },
  })
  const unidadDestino = useWatch({ control, name: 'unidad_destino_id' })
  const previa = useVistaPreviaTraspaso(Number(puesto.id), unidadDestino ?? null)

  const unidades = ((unidadesRaw ?? []) as UnidadConRelaciones[])
    .filter((u) => u.id !== puesto.unidad_administrativa_id)
    .map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))
  const impedimentos = previa.data?.impedimentos ?? []

  const enviar = async (v: TraspasoPuestoFormData) => {
    try {
      await preparar.mutateAsync({ puesto_id: Number(puesto.id), ...v })
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in traspasoPuestoSchema.shape) setError(campo as keyof TraspasoPuestoFormData, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={`Traspasar ${puesto.cargo?.nombre ?? 'el puesto'} a otra unidad`}
      size="lg"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Preparar el traspaso"
      submitting={preparar.isPending}
      submitDisabled={impedimentos.length > 0}
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          El puesto pasa, con su partida, de {puesto.unidad_administrativa?.nombre ?? 'su unidad'} a la que elija.
          Cada ocupante recibe su acción de personal en un lote, que se suscribe y registra entero en
          Acciones de Personal, «Actos en lote» (LOSEP Art. 37).
        </Text>

        <DataState loading={previa.isLoading} error={previa.error} errorTitle="No se pudo revisar el puesto" onRetry={previa.refetch}>
          <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />} title="Lo reciben">
            <List size="sm" spacing={2}>
              {(previa.data?.ocupantes ?? []).map((o) => (
                <List.Item key={o.servidor_id}>{o.nombre} · {o.tipo_nombramiento ?? '—'}</List.Item>
              ))}
            </List>
          </Alert>
          {impedimentos.length > 0 && (
            <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />} mt="sm">
              <Stack gap={4}>
                {impedimentos.map((i) => <Text key={i} size="sm">{i}</Text>)}
              </Stack>
            </Alert>
          )}
        </DataState>

        <Controller
          name="unidad_destino_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Unidad de destino"
              withAsterisk
              searchable
              data={unidades}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)}
              error={errors.unidad_destino_id?.message}
              {...contained}
            />
          )}
        />
        <Controller
          name="criterio"
          control={control}
          render={({ field }) => (
            <Select
              label="Criterio del traspaso"
              description="Reglamento a la LOSEP, Art. 69, numeral 1: el que dice el informe de la UATH."
              withAsterisk
              data={previa.data?.criterios ?? []}
              value={field.value ?? null}
              onChange={(v) => field.onChange(v ?? '')}
              error={errors.criterio?.message}
              {...contained}
            />
          )}
        />
        <TextInput
          label="N.º del informe técnico de la UATH"
          withAsterisk
          error={errors.informe_uath_numero?.message}
          {...contained}
          {...register('informe_uath_numero')}
        />
        <Controller
          name="fecha_efectiva"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Rige a partir de"
              withAsterisk
              valueFormat="DD/MM/YYYY"
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
              error={errors.fecha_efectiva?.message}
              {...contained}
            />
          )}
        />
        <Textarea
          label="Explicación"
          description="La imprime cada acción de personal, junto con el criterio."
          withAsterisk
          autosize
          minRows={2}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
