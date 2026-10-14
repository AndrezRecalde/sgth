'use client'

import { SegmentedControl, Stack, Text } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateTimeValue } from '@/lib/fecha'
import type { CondicionFisica } from '../services/tipos'

const CONDICIONES: { value: CondicionFisica; label: string }[] = [
  { value: 'bueno', label: 'Bueno' },
  { value: 'regular', label: 'Regular' },
  { value: 'malo', label: 'Malo' },
]

interface PropsCondicion {
  /** «Condición al salir», «Condición al volver»: sin ella no se sabía qué se elegía. */
  label: string
  value: CondicionFisica
  onChange: (v: CondicionFisica) => void
  error?: string
}

/** En qué condición sale o vuelve un equipo. */
export function CondicionEquipo({ label, value, onChange, error }: PropsCondicion) {
  return (
    <Stack gap={4}>
      <Text size="xs" fw={600} c="dimmed">{label}</Text>
      <SegmentedControl fullWidth data={CONDICIONES} value={value} aria-label={label}
        onChange={(v) => onChange(CONDICIONES.find((c) => c.value === v)?.value ?? 'bueno')} />
      {error && <Text size="xs" c={SEMANTIC_COLOR.danger}>{error}</Text>}
    </Stack>
  )
}

const SELECTOR_HORA = { withDropdown: true, popoverProps: { withinPortal: false }, format: '24h' as const }

/** La fecha y hora real de la entrega o la devolución: no puede ser futura. */
export function MomentoReal({ label, value, onChange, error }: { label: string; value: string; onChange: (v: string) => void; error?: string }) {
  const contained = useContainedInput()

  return (
    <DateTimePicker label={label} placeholder="Fecha y hora" valueFormat="DD/MM/YYYY HH:mm" withAsterisk timePickerProps={SELECTOR_HORA}
      maxDate={new Date()} error={error} {...contained}
      value={value ? new Date(value) : null} onChange={(d) => onChange(fromDateTimeValue(d))} />
  )
}

/** «Ahora», como lo espera el backend. */
export const ahoraLocal = () => fromDateTimeValue(new Date())
