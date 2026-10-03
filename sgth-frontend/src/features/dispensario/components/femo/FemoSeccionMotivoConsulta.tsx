'use client'

import { Grid, Select, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { TIPO_FICHA_OPTIONS } from '../../services/femoOptions'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import { FemoSeccion } from './FemoSeccion'
import { PuestoSellado } from './PuestoSellado'

interface Props {
  fichaData: Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

/**
 * Sección B del formulario 028 — motivo de consulta.
 *
 * Reúne el puesto que se evalúa, el tipo de evaluación y las cuatro fechas del
 * impreso. Antes todo esto estaba mezclado dentro de «A. Datos generales», que
 * no es una sección del formulario del MSP: allí van los datos del usuario.
 */
export function FemoSeccionMotivoConsulta({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  const set = (cambios: Partial<FichaBaseForm>) =>
    onFichaChange({ ...fichaData, ...cambios })

  return (
    <FemoSeccion letra="B" titulo="Motivo de consulta">
      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <DatePickerInput
            label="Fecha de atención"
            valueFormat="DD/MM/YYYY"
            required
            {...contained}
            value={toDateValue(fichaData.fecha_evaluacion)}
            onChange={(d) => set({ fecha_evaluacion: fromDateValue(d) })}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          {/* Lo fija la solicitud de Talento Humano, y el backend lo toma de
              ella: un retiro no se evalúa como ingreso. */}
          <Select
            label="Tipo de evaluación"
            description="Lo define la solicitud de Talento Humano"
            data={TIPO_FICHA_OPTIONS}
            disabled
            {...contained}
            value={fichaData.tipo_ficha ?? null}
          />
        </Grid.Col>

        {/* El puesto viene del sistema —del aspirante, del expediente o de la
            convocatoria— y no se teclea. El código CIUO cuelga del cargo, así
            que llega con él. Solo se escriben a mano cuando no hay puesto que
            heredar, que es el caso de un candidato externo sin plaza asignada. */}
        <Grid.Col span={12}>
          {fichaData.puesto_id ? (
            <PuestoSellado
              nombre={fichaData.puesto_trabajo}
              ciuo={fichaData.puesto_trabajo_ciuo}
            />
          ) : (
            <Grid>
              <Grid.Col span={{ base: 12, md: 8 }}>
                <TextInput
                  label="Puesto de trabajo"
                  placeholder="Ej: Técnico de campo"
                  description="No hay puesto asignado en el sistema para esta persona"
                  {...contained}
                  value={fichaData.puesto_trabajo ?? ''}
                  onChange={(e) => set({ puesto_trabajo: e.currentTarget.value })}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <TextInput
                  label="Código CIUO"
                  placeholder="Ej: 3112"
                  {...contained}
                  value={fichaData.puesto_trabajo_ciuo ?? ''}
                  onChange={(e) => set({ puesto_trabajo_ciuo: e.currentTarget.value })}
                />
              </Grid.Col>
            </Grid>
          )}
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 4 }}>
          <DatePickerInput
            label="Fecha de ingreso al trabajo"
            valueFormat="DD/MM/YYYY"
            clearable
            {...contained}
            value={toDateValue(fichaData.fecha_ingreso_trabajo)}
            onChange={(d) => set({ fecha_ingreso_trabajo: fromDateValueOrNull(d) })}
          />
        </Grid.Col>

        {/* Cada una aplica a un tipo de evaluación: la de reintegro a las de
            reintegro, la de salida a las de retiro. */}
        {fichaData.tipo_ficha === 'reintegro' && (
          <Grid.Col span={{ base: 12, md: 4 }}>
            <DatePickerInput
              label="Fecha de reintegro"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDateValue(fichaData.fecha_reintegro)}
              onChange={(d) => set({ fecha_reintegro: fromDateValueOrNull(d) })}
            />
          </Grid.Col>
        )}

        {fichaData.tipo_ficha === 'retiro' && (
          <Grid.Col span={{ base: 12, md: 4 }}>
            <DatePickerInput
              label="Último día laboral"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDateValue(fichaData.fecha_ultimo_dia_laboral)}
              onChange={(d) => set({ fecha_ultimo_dia_laboral: fromDateValueOrNull(d) })}
            />
          </Grid.Col>
        )}

        {/* La «Observación» de la sección B: el PDF la imprime aquí, y antes
            se capturaba al final del formulario como «Observaciones generales». */}
        <Grid.Col span={12}>
          <Textarea
            label="Observación"
            autosize
            minRows={2}
            {...contained}
            value={fichaData.observaciones ?? ''}
            onChange={(e) => set({ observaciones: e.currentTarget.value })}
          />
        </Grid.Col>
      </Grid>
    </FemoSeccion>
  )
}
