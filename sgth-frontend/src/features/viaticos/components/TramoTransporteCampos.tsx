'use client'

import { Alert, Grid, Select, Stack } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { Controller, useWatch, type Control, type FieldErrors, type UseFormSetValue } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFechaHora, fromDateTimeValue } from '@/lib/fecha'
import { useEmpresasPorTipo, useTiposTransporte } from '../hooks/useViaticos'
import type { TramoFormData } from '../schemas/viatico.schema'
import type { CatalogoTransporte, EmpresaTransporte, Viatico } from '@/types/api'

interface Props {
  control:       Control<TramoFormData>
  errors:        FieldErrors<TramoFormData>
  setValue:      UseFormSetValue<TramoFormData>
  viatico?:      Viatico | null
  esPrimerTramo: boolean
}

const opciones = (lista: { id: number; nombre?: string | null }[]) =>
  lista.map((o) => ({ value: String(o.id), label: o.nombre ?? '' }))

/**
 * En qué va el tramo y cuándo: el transporte, la empresa y los dos horarios,
 * con los avisos que los comparan con las fechas del viático.
 */
export function TramoTransporteCampos({ control, errors, setValue, viatico, esPrimerTramo }: Props) {
  const contained = useContainedInput()
  const [catalogoId, salida, llegada] = useWatch({
    control,
    name: ['catalogo_transporte_id', 'datetime_salida', 'datetime_llegada'],
  })

  const { data: tipos = [] } = useTiposTransporte()
  const { data: empresas = [] } = useEmpresasPorTipo(catalogoId || null)
  const empresaOptions = opciones(empresas as EmpresaTransporte[])

  // Solo el primer tramo tiene que salir con el viático; ninguno puede llegar
  // después del regreso.
  const salidaDistinta =
    esPrimerTramo && !!viatico?.datetime_salida && !!salida &&
    new Date(viatico.datetime_salida as string).getTime() !== new Date(salida).getTime()
  const llegaTarde =
    !!viatico?.datetime_llegada && !!llegada &&
    new Date(llegada).getTime() > new Date(viatico.datetime_llegada as string).getTime()

  const fechaHora = (name: 'datetime_salida' | 'datetime_llegada', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DateTimePicker
          label={label}
          valueFormat="DD/MM/YYYY HH:mm"
          {...contained}
          value={field.value ? new Date(field.value) : null}
          onChange={(v) => field.onChange(fromDateTimeValue(v))}
          error={errors[name]?.message}
        />
      )}
    />
  )

  return (
    <Stack gap="xs">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="catalogo_transporte_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de transporte"
                data={opciones(tipos as CatalogoTransporte[])}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : 0)
                  setValue('empresa_transporte_id', 0)
                }}
                error={errors.catalogo_transporte_id?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="empresa_transporte_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Empresa"
                data={empresaOptions}
                searchable
                disabled={empresaOptions.length === 0}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : 0)}
                error={errors.empresa_transporte_id?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>{fechaHora('datetime_salida', 'Salida')}</Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>{fechaHora('datetime_llegada', 'Llegada')}</Grid.Col>
      </Grid>

      {salidaDistinta && (
        <Alert color="amber" variant="light" p="xs">
          El primer tramo sale con el viático: el{' '}
          <strong>{formatFechaHora(viatico?.datetime_salida as string)}</strong>.
        </Alert>
      )}
      {llegaTarde && (
        <Alert color="red" variant="light" p="xs">
          La llegada no puede pasar del regreso del viático:{' '}
          <strong>{formatFechaHora(viatico?.datetime_llegada as string)}</strong>.
        </Alert>
      )}
    </Stack>
  )
}
