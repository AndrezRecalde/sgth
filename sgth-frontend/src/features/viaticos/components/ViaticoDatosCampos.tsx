'use client'

import { Alert, Grid, Select, Text, Textarea } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useWatch, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateTimeValue } from '@/lib/fecha'
import {
  MODALIDAD_OPTIONS, PAISES_OPTIONS, TIPO_VIAJE_OPTIONS, ZONA_OPTIONS,
} from '../constants/viatico.constants'
import { nochesEntre, type ViaticoFormData } from '../schemas/viatico.schema'

interface Props {
  control: Control<ViaticoFormData>
  errors: FieldErrors<ViaticoFormData>
}

const SELECTOR_HORA = {
  withDropdown: true,
  popoverProps: { withinPortal: false },
  format: '24h' as const,
}

/*
| Los datos de un viático, los mismos al solicitarlo y al corregirlo.
|
| Antes cada modal tenía los suyos: «¿Necesita anticipo de dinero?» en uno y
| «Modalidad de anticipo» en el otro, y la edición no pedía el país ni el
| motivo si se cambiaba la zona al exterior.
*/
export function ViaticoDatosCampos({ control, errors }: Props) {
  const contained = useContainedInput()
  const [zona, salida, llegada] = useWatch({
    control,
    name: ['zona', 'datetime_salida', 'datetime_llegada'],
  })

  const noches = salida && llegada ? nochesEntre(salida, llegada) : null

  const fechaHora = (name: 'datetime_salida' | 'datetime_llegada', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DateTimePicker
          label={label}
          valueFormat="DD/MM/YYYY HH:mm"
          timePickerProps={SELECTOR_HORA}
          {...contained}
          value={field.value ? new Date(field.value) : null}
          onChange={(v) => field.onChange(fromDateTimeValue(v))}
          error={errors[name]?.message}
        />
      )}
    />
  )

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="zona"
          control={control}
          render={({ field }) => (
            <Select
              label="Zona del viaje"
              data={ZONA_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? 'fuera_provincia')}
              error={errors.zona?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="modalidad_anticipo"
          control={control}
          render={({ field }) => (
            <Select
              label="Anticipo"
              data={MODALIDAD_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? 'total')}
              error={errors.modalidad_anticipo?.message}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        {fechaHora('datetime_salida', 'Salida de Esmeraldas')}
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        {fechaHora('datetime_llegada', 'Regreso a Esmeraldas')}
      </Grid.Col>
      <Grid.Col span={12}>
        {/* Lo que se paga son las noches de pernocte, no los días. */}
        <Text size="sm" c="dimmed">
          {noches !== null && noches >= 1
            ? `${noches} ${noches === 1 ? 'noche' : 'noches'} de pernocte.`
            : 'El viático se paga por noche: la comisión debe incluir al menos una.'}
        </Text>
      </Grid.Col>

      {zona === 'exterior' && (
        <>
          <Grid.Col span={12}>
            <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
              El monto lo calcula Financiero al aprobar, con el coeficiente del país de destino.
            </Alert>
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="tipo_viaje"
              control={control}
              render={({ field }) => (
                <Select
                  label="Motivo del viaje"
                  data={TIPO_VIAJE_OPTIONS}
                  searchable
                  {...contained}
                  value={field.value ?? null}
                  onChange={(v) => field.onChange(v ?? null)}
                  error={errors.tipo_viaje?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="pais_destino"
              control={control}
              render={({ field }) => (
                <Select
                  label="País de destino"
                  data={PAISES_OPTIONS}
                  searchable
                  {...contained}
                  value={field.value ?? null}
                  onChange={(v) => field.onChange(v ?? null)}
                  error={errors.pais_destino?.message}
                />
              )}
            />
          </Grid.Col>
        </>
      )}

      <Grid.Col span={12}>
        <Controller
          name="justificacion"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Justificación"
              description="El objetivo de la comisión, en al menos 10 caracteres"
              placeholder="Ej: Participación en el taller de contratación pública del SERCOP"
              autosize
              minRows={3}
              maxRows={6}
              {...contained}
              value={field.value}
              onChange={(e) => field.onChange(e.currentTarget.value)}
              error={errors.justificacion?.message}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
