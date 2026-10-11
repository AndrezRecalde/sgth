'use client'

import { Alert, Grid, NumberInput, Select, Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestosDeUnidad } from '@/features/estructura/hooks/usePuestosDeUnidad'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { ClaseDelCatalogo } from '@/types/api'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { BloqueDetalle } from './BloqueDetalle'
import { SituacionActualPanel } from './SituacionActualPanel'

interface Props {
  form: UseFormReturn<MovimientoFormData>
  servidorId: number
  clase: ClaseDelCatalogo
}

/**
 * Lo que cambia la adenda del contrato civil (fase 4.2): la unidad y el puesto,
 * los honorarios y el plazo, cada uno si cambia. Lo que se deja vacío sigue
 * como está.
 *
 * No es la situación propuesta, que exige el puesto: una adenda puede cambiar
 * solo los honorarios. Que el plazo no salga del año calendario del contrato,
 * y que algo cambie de verdad, lo dice el backend, que conoce el contrato.
 */
export function MovimientoDatosAdenda({ form, servidorId, clase }: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form

  const unidadId = useWatch({ control, name: 'unidad_destino_id' })
  const puestoId = useWatch({ control, name: 'puesto_destino_id' })

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const {
    puestos, descripcionRecorte, searchValue, onSearchChange,
  } = usePuestosDeUnidad(unidadId, { seleccionadoId: puestoId })

  return (
    <>
      {clase.aviso && (
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          {clase.aviso}
        </Alert>
      )}

      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <SituacionActualPanel servidorId={servidorId} conPlazo />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <BloqueDetalle>
            <SectionHeading title="Lo que cambia la adenda" mb={4} />
            <Text size="xs" c="dimmed" mb="xs">Deje vacío lo que no cambia.</Text>

            <Stack gap="xs">
              <Controller
                name="unidad_destino_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Unidad administrativa"
                    placeholder="La misma"
                    data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
                    searchable
                    clearable
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : null)
                      setValue('puesto_destino_id', null)
                    }}
                    error={errors.unidad_destino_id?.message}
                    {...contained}
                  />
                )}
              />

              <Controller
                name="puesto_destino_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Puesto"
                    description={descripcionRecorte}
                    placeholder={unidadId ? 'Seleccionar' : 'Elija primero la unidad'}
                    data={puestos.map((p) => ({ value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}` }))}
                    searchable
                    clearable
                    searchValue={searchValue}
                    onSearchChange={onSearchChange}
                    disabled={!unidadId}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => field.onChange(v ? Number(v) : null)}
                    error={errors.puesto_destino_id?.message}
                    {...contained}
                  />
                )}
              />

              <Controller
                name="remuneracion_propuesta"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Honorarios mensuales"
                    placeholder="Los mismos"
                    min={0}
                    decimalScale={2}
                    error={errors.remuneracion_propuesta?.message}
                    value={field.value ?? ''}
                    onChange={(v) => {
                      const n = typeof v === 'number' ? v : parseFloat(String(v))
                      field.onChange(Number.isFinite(n) ? n : null)
                    }}
                    {...contained}
                  />
                )}
              />

              <Controller
                name="fecha_fin_propuesta"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Plazo nuevo"
                    placeholder="El mismo"
                    description="A más tardar, el 31 de diciembre del año del contrato."
                    valueFormat="DD/MM/YYYY"
                    clearable
                    value={toDateValue(field.value)}
                    onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                    error={errors.fecha_fin_propuesta?.message}
                    {...contained}
                  />
                )}
              />
            </Stack>
          </BloqueDetalle>
        </Grid.Col>
      </Grid>
    </>
  )
}
