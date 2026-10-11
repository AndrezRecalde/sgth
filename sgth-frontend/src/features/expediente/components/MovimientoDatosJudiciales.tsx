'use client'

import { Grid, Input, NumberInput, SegmentedControl, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

const ORDENES = [
  { value: 'providencia', label: 'Providencia (medida cautelar)' },
  { value: 'sentencia', label: 'Sentencia ejecutoriada' },
]

/**
 * La sentencia que ordena la restitución [TH N15; fase 2.6]: la causa, quién
 * la dictó, cuándo y cuándo quedó ejecutoriada, y lo que el servidor dejó de
 * percibir. El puesto no se pide: vuelve al que tenía.
 *
 * Al obrero lo reintegra el juez que declara ineficaz su despido (Código del
 * Trabajo, Arts. 195.2 y 195.3; fase 4.5b), y basta la providencia que lo
 * ordena como medida cautelar. Las remuneraciones pendientes no se piden: si
 * la sentencia no las fija, el sistema las calcula como referencia.
 */
export function MovimientoDatosJudiciales({
  form,
  obrero = false,
}: {
  form: UseFormReturn<MovimientoFormData>
  /** El reintegro del obrero por despido ineficaz. */
  obrero?: boolean
}) {
  const contained = useContainedInput()
  const { control, register, setValue, formState: { errors } } = form

  const orden = useWatch({ control, name: 'orden_judicial' }) ?? 'providencia'
  const conSentencia = !obrero || orden === 'sentencia'

  const fecha = (name: 'fecha_sentencia' | 'fecha_ejecutoria' | 'fecha_providencia', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          withAsterisk
          maxDate={new Date()}
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValueOrNull(d))}
          error={errors[name]?.message}
          {...contained}
        />
      )}
    />
  )

  // Una orden u otra, no las dos: lo del camino que se deja se borra.
  const elegirOrden = (valor: string) => {
    setValue('orden_judicial', valor === 'sentencia' ? 'sentencia' : 'providencia')
    if (valor === 'sentencia') {
      setValue('fecha_providencia', null)
    } else {
      setValue('fecha_sentencia', null)
      setValue('fecha_ejecutoria', null)
    }
  }

  return (
    <div>
      <SectionHeading title={obrero ? 'Orden judicial' : 'Sentencia'} mb="xs" />
      <Grid gap="sm">
        {obrero && (
          <Grid.Col span={12}>
            {/* Sin el patrón contained: un SegmentedControl no tiene casilla
                donde ponerle la etiqueta. */}
            <Input.Wrapper
              label="Qué ordena el reintegro"
              description="El juez lo ordena en la providencia que califica la demanda, sin esperar la sentencia (Código del Trabajo, Art. 195.2)."
            >
              <SegmentedControl data={ORDENES} value={orden} onChange={elegirOrden} fullWidth mt={4} />
            </Input.Wrapper>
          </Grid.Col>
        )}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Número de la causa"
            placeholder={obrero ? 'Ej. 08371-2026-00456' : 'Ej. 08281-2025-00123'}
            withAsterisk
            error={errors.causa_judicial_numero?.message}
            {...contained}
            {...register('causa_judicial_numero')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label={obrero ? 'Unidad judicial' : 'Unidad judicial o tribunal'}
            placeholder={obrero
              ? 'Ej. Unidad Judicial de Trabajo de Esmeraldas'
              : 'Ej. Tribunal Distrital de lo Contencioso Administrativo'}
            withAsterisk
            error={errors.unidad_judicial?.message}
            {...contained}
            {...register('unidad_judicial')}
          />
        </Grid.Col>
        {conSentencia ? (
          <>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              {fecha('fecha_sentencia', 'Fecha de la sentencia')}
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              {fecha('fecha_ejecutoria', 'Fecha de ejecutoría')}
            </Grid.Col>
          </>
        ) : (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            {fecha('fecha_providencia', 'Fecha de la providencia')}
          </Grid.Col>
        )}
        {conSentencia && (
          <Grid.Col span={12}>
            <Controller
              name="remuneraciones_dejadas_de_percibir"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Remuneraciones dejadas de percibir"
                  description={obrero
                    ? 'Las que fija la sentencia, sin el recargo. Si no las fija, déjelo vacío: se calculan con la remuneración y los días fuera, más el 10 % (Art. 195.3).'
                    : 'Lo que la sentencia ordena pagar; 0 si no ordena nada. La nómina lo aplicará cuando llegue esa fase.'}
                  placeholder="0.00"
                  min={0}
                  decimalScale={2}
                  hideControls
                  withAsterisk={!obrero}
                  error={errors.remuneraciones_dejadas_de_percibir?.message}
                  value={field.value ?? ''}
                  onChange={(v) => {
                    const n = typeof v === 'number' ? v : parseFloat(String(v))
                    field.onChange(Number.isFinite(n) ? n : null)
                  }}
                  {...contained}
                />
              )}
            />
          </Grid.Col>
        )}
      </Grid>
    </div>
  )
}
