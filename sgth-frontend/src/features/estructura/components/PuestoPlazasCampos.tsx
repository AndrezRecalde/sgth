'use client'

import { Grid, Input, NumberInput, SegmentedControl, Switch } from '@mantine/core'
import { Controller, useWatch, type Control, type FieldErrors, type UseFormSetValue } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { TIPOS_PUESTO, type TipoPuesto } from '../constants/clasificacionPuesto'
import type { PuestoFormData } from '../schemas/puesto.schema'

interface Props {
  control:  Control<PuestoFormData>
  errors:   FieldErrors<PuestoFormData>
  setValue: UseFormSetValue<PuestoFormData>
  /** Ocupado, no se desactiva (fase 7.1a), y el tipo cambia solo si sus ocupantes caben en el otro. */
  ocupado:  boolean
}

/** Qué sostiene cada tipo de puesto (P9): lo que TH necesita para elegir. */
const AYUDA_TIPO: Record<TipoPuesto, string> = {
  plantilla: 'Del distributivo: tiene plazas, se concursa y cuenta en las vacantes. Lo ocupan nombramientos, obreros y autoridades electas.',
  contrato:  'Para servicios ocasionales y profesionales, uno por tipo de trabajo: no tiene plazas, no se concursa ni es jefatura.',
}

/** De qué tipo es el puesto, cuántas plazas tiene, si es la jefatura de su unidad y si está activo. */
export function PuestoPlazasCampos({ control, errors, setValue, ocupado }: Props) {
  const contained = useContainedInput()
  const tipo = useWatch({ control, name: 'tipo', defaultValue: 'plantilla' })
  const deContrato = tipo === 'contrato'

  /**
   * Al pasar a contrato se va lo que ese tipo no admite, para que no viaje
   * oculto y el backend lo rechace en un campo que no se ve. De vuelta a
   * plantilla, una plaza: es lo mínimo que tiene.
   */
  const limpiarSegunTipo = (nuevo: TipoPuesto) => {
    if (nuevo === 'contrato') {
      setValue('plazas', null, { shouldDirty: true })
      setValue('es_jefe', false, { shouldDirty: true })
    } else {
      setValue('plazas', 1, { shouldDirty: true })
    }
  }

  return (
    <>
      <Grid.Col span={12}>
        <Controller
          name="tipo"
          control={control}
          render={({ field }) => (
            <Input.Wrapper
              label="Tipo de puesto"
              description={ocupado
                ? `${AYUDA_TIPO[field.value]} Ocupado, cambia de tipo solo si sus ocupantes caben en el otro.`
                : AYUDA_TIPO[field.value]}
              error={errors.tipo?.message}
            >
              <SegmentedControl
                fullWidth
                data={TIPOS_PUESTO}
                value={field.value}
                onChange={(v) => {
                  const elegido = TIPOS_PUESTO.find((t) => t.value === v)?.value
                  if (!elegido) return
                  field.onChange(elegido)
                  limpiarSegunTipo(elegido)
                }}
                mt={4}
              />
            </Input.Wrapper>
          )}
        />
      </Grid.Col>
      {!deContrato && (
        <>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="plazas"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Plazas"
                  description="Una jefatura tiene una sola."
                  placeholder="1"
                  min={1}
                  {...contained}
                  value={field.value ?? 1}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                  error={errors.plazas?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="es_jefe"
              control={control}
              render={({ field }) => (
                <Switch
                  label="Es jefe de unidad"
                  checked={field.value}
                  onChange={(e) => field.onChange(e.currentTarget.checked)}
                  error={errors.es_jefe?.message}
                  mt="md"
                />
              )}
            />
          </Grid.Col>
        </>
      )}
      <Grid.Col span={{ base: 12, sm: deContrato ? 12 : 4 }}>
        <Controller
          name="activo"
          control={control}
          render={({ field }) => (
            <Switch
              label="Puesto activo"
              description={ocupado ? 'Ocupado: no se desactiva.' : undefined}
              disabled={ocupado && field.value}
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              mt="md"
            />
          )}
        />
      </Grid.Col>
    </>
  )
}
