'use client'

import { Select } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCatalogoAcciones } from '../hooks/useCatalogoAcciones'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { MovimientoActaConcurso } from './MovimientoActaConcurso'

/**
 * Para qué se da un nombramiento provisional (LOSEP Art. 17 b; fase 3.2) y, si
 * es de prueba, el acta final del concurso que lo origina. Los supuestos los
 * da el catálogo.
 */
export function MovimientoSupuestoProvisional({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form
  const catalogo = useCatalogoAcciones()

  const supuesto = useWatch({ control, name: 'supuesto_provisional_propuesto' })

  const opciones = (catalogo.data?.supuestos_provisional ?? []).map((s) => ({
    value: s.codigo,
    label: `${s.etiqueta} (${s.base_legal})`,
  }))

  return (
    <>
      <Controller
        name="supuesto_provisional_propuesto"
        control={control}
        render={({ field }) => (
          <Select
            label="Supuesto del nombramiento provisional"
            description="De prueba es el de quien gana un concurso: a los tres meses recibe el nombramiento definitivo."
            placeholder="Seleccione el supuesto"
            data={opciones}
            withAsterisk
            value={field.value ?? null}
            onChange={(v) => {
              field.onChange(v)
              // El acta es solo del de prueba.
              if (v !== 'de_prueba') {
                setValue('acta_concurso_numero', '')
                setValue('acta_concurso_fecha', null)
              }
            }}
            error={errors.supuesto_provisional_propuesto?.message}
            {...contained}
          />
        )}
      />

      {supuesto === 'de_prueba' && <MovimientoActaConcurso form={form} />}
    </>
  )
}
