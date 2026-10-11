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
import { opcionesLugarDeTrabajo } from '../utils/lugaresDeTrabajo'
import { BloqueDetalle } from './BloqueDetalle'
import { SituacionActualPanel } from './SituacionActualPanel'

interface Props {
  form: UseFormReturn<MovimientoFormData>
  servidorId: number
  clase: ClaseDelCatalogo
}

const TITULO = {
  ocupacion: 'La ocupación nueva',
  lugar: 'El lugar nuevo',
  remuneracion: 'La remuneración nueva',
} as const

/**
 * Lo que cambia del contrato de trabajo de un obrero, por adenda (fase 4.3):
 *
 *  - la **ocupación**: otro puesto del catálogo de obreros, con el
 *    consentimiento escrito del trabajador (CT 192); la remuneración puede
 *    subir;
 *  - el **lugar**: otra unidad, en un puesto de la misma denominación, u otro
 *    lugar de trabajo;
 *  - la **remuneración**: un monto mayor que el actual.
 *
 * Lo que se deja vacío sigue como está. Que el puesto sea del catálogo de
 * obreros, que la remuneración no baje y que algo cambie de verdad lo dice el
 * backend, que conoce el contrato.
 */
export function MovimientoDatosCambioObrero({ form, servidorId, clase }: Props) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const cambio = clase.cambio_del_obrero ?? 'ocupacion'

  const unidadId = useWatch({ control, name: 'unidad_destino_id' })
  const puestoId = useWatch({ control, name: 'puesto_destino_id' })

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const {
    puestos, descripcionRecorte, searchValue, onSearchChange,
  } = usePuestosDeUnidad(unidadId, { seleccionadoId: puestoId })

  // Solo el catálogo de obreros: un puesto de la LOSEP no es una ocupación
  // que el Código del Trabajo pueda dar.
  const puestosDeObreros = puestos.filter((p) => p.regimen_laboral === 'codigo_trabajo')

  const conPuesto = cambio !== 'remuneracion'

  return (
    <>
      {clase.aviso && (
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          {clase.aviso}
        </Alert>
      )}

      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <SituacionActualPanel servidorId={servidorId} conLugar />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <BloqueDetalle>
            <SectionHeading title={TITULO[cambio]} mb={4} />
            {cambio === 'lugar' && (
              <Text size="xs" c="dimmed" mb="xs">Deje vacío lo que no cambia.</Text>
            )}

            <Stack gap="xs">
              {conPuesto && (
                <>
                  <Controller
                    name="unidad_destino_id"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Unidad administrativa"
                        placeholder={cambio === 'lugar' ? 'La misma' : 'Seleccionar'}
                        data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
                        searchable
                        clearable
                        withAsterisk={cambio === 'ocupacion'}
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
                        description={descripcionRecorte ?? (cambio === 'lugar'
                          ? 'De la misma denominación: si cambia, es un cambio de ocupación.'
                          : 'Del catálogo de obreros.')}
                        placeholder={unidadId ? 'Seleccionar' : 'Elija primero la unidad'}
                        data={puestosDeObreros.map((p) => ({
                          value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}`,
                        }))}
                        searchable
                        clearable
                        withAsterisk={cambio === 'ocupacion'}
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
                </>
              )}

              {cambio === 'lugar' && (
                <Controller
                  name="lugar_trabajo"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Lugar de trabajo"
                      placeholder="El mismo"
                      data={opcionesLugarDeTrabajo(field.value)}
                      searchable
                      clearable
                      value={field.value || null}
                      onChange={(v) => field.onChange(v ?? '')}
                      error={errors.lugar_trabajo?.message}
                      {...contained}
                    />
                  )}
                />
              )}

              {cambio !== 'lugar' && (
                <Controller
                  name="remuneracion_propuesta"
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      label="Remuneración mensual"
                      description="Nunca menor que la actual."
                      placeholder={cambio === 'ocupacion' ? 'La misma' : '0.00'}
                      withAsterisk={cambio === 'remuneracion'}
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
              )}

              {cambio === 'ocupacion' && (
                <Controller
                  name="fecha_consentimiento"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Consentimiento escrito del trabajador"
                      description="La fecha en que lo firmó. Sin él, el cambio equivale a un despido intempestivo."
                      placeholder="Seleccionar fecha"
                      valueFormat="DD/MM/YYYY"
                      withAsterisk
                      maxDate={new Date()}
                      value={toDateValue(field.value)}
                      onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                      error={errors.fecha_consentimiento?.message}
                      {...contained}
                    />
                  )}
                />
              )}
            </Stack>
          </BloqueDetalle>
        </Grid.Col>
      </Grid>
    </>
  )
}
