'use client'

import { DatePickerInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

export type Rango = { desde: string; hasta: string }

/** Desde y hasta, dentro de un `Toolbar`. El backend acepta hasta un año. */
export function RangoFechas({ rango, onChange }: { rango: Rango; onChange: (r: Rango) => void }) {
  const compacto = useContainedInput('sm')

  return (
    <>
      <DatePickerInput label="Desde" valueFormat="DD/MM/YYYY" maxDate={rango.hasta} {...compacto}
        value={toDateValue(rango.desde)} onChange={(d) => onChange({ ...rango, desde: fromDateValueOrNull(d) ?? rango.desde })} />
      <DatePickerInput label="Hasta" valueFormat="DD/MM/YYYY" minDate={rango.desde} {...compacto}
        value={toDateValue(rango.hasta)} onChange={(d) => onChange({ ...rango, hasta: fromDateValueOrNull(d) ?? rango.hasta })} />
    </>
  )
}
