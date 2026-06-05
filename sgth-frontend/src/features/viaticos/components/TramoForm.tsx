'use client'

import {
  Stack, Grid, Select, Button, Group,
  Divider, Text, SegmentedControl, TextInput,
} from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import React from 'react'
import { IconCheck, IconX } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useTiposTransporte,
  useEmpresasPorTipo,
} from '../hooks/useViaticos'
import { viaticoService } from '../services/viaticoService'
import { getApiErrorMessage } from '@/types/api'
import type { CatalogoTransporte, EmpresaTransporte } from '@/types/api'
import {
  tramoSchema,
  type TramoFormData,
} from '../schemas/viatico.schema'

interface Props {
  viaticoId: number
  onSuccess: () => void
  onCancel:  () => void
}

const fromDateTime = (d: Date | string | null): string => {
  if (!d) return ''
  const date = typeof d === 'string' ? new Date(d) : d
  return date.toISOString().slice(0, 16)
}

export function TramoForm({ viaticoId, onSuccess, onCancel }: Props) {
  const contained  = useContainedInput()
  const qc         = useQueryClient()

  const { data: tipos = [] } = useTiposTransporte()
  const {
    control,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<TramoFormData>({
    resolver: zodResolver(tramoSchema),
    defaultValues: {
      origen_tipo:            'nacional',
      origen_provincia_id:    null,
      origen_canton_id:       null,
      origen_pais:            null,
      origen_ciudad:          '',
      destino_tipo:           'nacional',
      destino_provincia_id:   null,
      destino_canton_id:      null,
      destino_pais:           null,
      destino_ciudad:         '',
      catalogo_transporte_id: 0,
      empresa_transporte_id:  0,
      datetime_salida:        '',
      datetime_llegada:       '',
    },
  })

  const origenTipo       = watch('origen_tipo')
  const destinoTipo      = watch('destino_tipo')
  const catalogoSelId    = watch('catalogo_transporte_id')

  const { data: empresas = [] } =
    useEmpresasPorTipo(catalogoSelId || null)

  const tipoOptions = (tipos as CatalogoTransporte[]).map(t => ({
    value: String(t.id),
    label: t.nombre,
  }))

  const empresaOptions = (empresas as EmpresaTransporte[]).map(e => ({
    value: String(e.id),
    label: e.nombre,
  }))

  const crear = useMutation({
    mutationFn: (data: Parameters<
      typeof viaticoService.tramos.crear
    >[1]) =>
      viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notifications.show({
        title:   'Tramo agregado',
        message: 'El tramo fue registrado al itinerario.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['tramos', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico', viaticoId] })
      onSuccess()
    },
    onError: (error: unknown) => notifications.show({
      title:   'Error',
      message: getApiErrorMessage(error),
      color:   'red',
      icon:    React.createElement(IconX, { size: 16 }),
    }),
  })

  const onSubmit = (values: TramoFormData) => {
    const { catalogo_transporte_id: _, ...rest } = values
    crear.mutate(rest)
  }

  const PAISES_COMUNES = [
    'Colombia', 'Perú', 'Bolivia', 'Chile',
    'Argentina', 'Brasil', 'Venezuela', 'México',
    'España', 'Estados Unidos', 'Canadá', 'Otro',
  ]

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="sm">

        {/* ORIGEN */}
        <Divider label="Origen" labelPosition="left" />
        <Controller
          name="origen_tipo"
          control={control}
          render={({ field }) => (
            <SegmentedControl
              data={[
                { value: 'nacional',       label: 'Nacional'       },
                { value: 'internacional',  label: 'Internacional'  },
              ]}
              value={field.value}
              onChange={(v) => {
                field.onChange(v)
                setValue('origen_provincia_id', null)
                setValue('origen_canton_id',    null)
                setValue('origen_pais',         null)
              }}
              fullWidth
              size="xs"
            />
          )}
        />

        {origenTipo === 'internacional' ? (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="origen_pais"
                control={control}
                render={({ field }) => (
                  <Select
                    label="País de origen"
                    data={PAISES_COMUNES}
                    searchable
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => field.onChange(v ?? null)}
                    error={errors.origen_pais?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="origen_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad de origen"
                    placeholder="Ingrese la ciudad"
                    {...contained}
                    value={field.value || ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.origen_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        ) : (
          <Controller
            name="origen_ciudad"
            control={control}
            render={({ field }) => (
              <Select
                label="Ciudad / Cantón de origen"
                placeholder="Ej: Esmeraldas"
                data={[
                  'Esmeraldas', 'Quito', 'Guayaquil', 'Cuenca',
                  'Manta', 'Ambato', 'Loja', 'Ibarra', 'Riobamba',
                  'Santo Domingo', 'Machala', 'Portoviejo',
                  'Lago Agrio', 'Tena', 'Baños', 'Tulcán',
                  'Latacunga', 'Morona', 'Puyo', 'Otra ciudad',
                ]}
                searchable
                {...contained}
                value={field.value || null}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.origen_ciudad?.message}
              />
            )}
          />
        )}

        {/* TRANSPORTE */}
        <Divider label="Transporte" labelPosition="left" />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="catalogo_transporte_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de transporte"
                  data={tipoOptions}
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
                  label="Empresa / Aerolínea"
                  data={empresaOptions}
                  searchable
                  disabled={!catalogoSelId}
                  placeholder={
                    !catalogoSelId
                      ? 'Seleccione primero el tipo'
                      : 'Seleccionar empresa'
                  }
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) =>
                    field.onChange(v ? Number(v) : 0)
                  }
                  error={errors.empresa_transporte_id?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        {/* DESTINO */}
        <Divider label="Destino" labelPosition="left" />
        <Controller
          name="destino_tipo"
          control={control}
          render={({ field }) => (
            <SegmentedControl
              data={[
                { value: 'nacional',      label: 'Nacional'      },
                { value: 'internacional', label: 'Internacional' },
              ]}
              value={field.value}
              onChange={(v) => {
                field.onChange(v)
                setValue('destino_provincia_id', null)
                setValue('destino_canton_id',    null)
                setValue('destino_pais',         null)
              }}
              fullWidth
              size="xs"
            />
          )}
        />

        {destinoTipo === 'internacional' ? (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="destino_pais"
                control={control}
                render={({ field }) => (
                  <Select
                    label="País de destino"
                    data={PAISES_COMUNES}
                    searchable
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => field.onChange(v ?? null)}
                    error={errors.destino_pais?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="destino_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad de destino"
                    placeholder="Ingrese la ciudad"
                    {...contained}
                    value={field.value || ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.destino_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        ) : (
          <Controller
            name="destino_ciudad"
            control={control}
            render={({ field }) => (
              <Select
                label="Ciudad / Cantón de destino"
                placeholder="Ej: Quito"
                data={[
                  'Esmeraldas', 'Quito', 'Guayaquil', 'Cuenca',
                  'Manta', 'Ambato', 'Loja', 'Ibarra', 'Riobamba',
                  'Santo Domingo', 'Machala', 'Portoviejo',
                  'Lago Agrio', 'Tena', 'Baños', 'Tulcán',
                  'Latacunga', 'Morona', 'Puyo', 'Otra ciudad',
                ]}
                searchable
                {...contained}
                value={field.value || null}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.destino_ciudad?.message}
              />
            )}
          />
        )}

        {/* FECHAS */}
        <Divider label="Fechas y horas" labelPosition="left" />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="datetime_salida"
              control={control}
              render={({ field }) => (
                <DateTimePicker
                  label="Fecha y hora de salida"
                  placeholder="Seleccionar"
                  valueFormat="DD/MM/YYYY HH:mm"
                  {...contained}
                  value={field.value
                    ? new Date(field.value)
                    : null}
                  onChange={(v) =>
                    field.onChange(fromDateTime(v))
                  }
                  error={errors.datetime_salida?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="datetime_llegada"
              control={control}
              render={({ field }) => (
                <DateTimePicker
                  label="Fecha y hora de llegada"
                  placeholder="Seleccionar"
                  valueFormat="DD/MM/YYYY HH:mm"
                  {...contained}
                  value={field.value
                    ? new Date(field.value)
                    : null}
                  onChange={(v) =>
                    field.onChange(fromDateTime(v))
                  }
                  error={errors.datetime_llegada?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        <Group justify="flex-end" mt="sm">
          <Button
            variant="default"
            size="sm"
            onClick={onCancel}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            size="sm"
            color="emerald"
            variant="light"
            loading={crear.isPending}
          >
            Agregar tramo
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
