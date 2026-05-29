'use client'

import { Group, Select, Button, Stack } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useState } from 'react'
import { IconCalculator } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useNominaMutations } from '../hooks/useNominaMutations'

const ESTADO_OPTIONS = [
  { value: 'borrador',       label: 'Borrador' },
  { value: 'en_proceso',     label: 'En proceso' },
  { value: 'cerrada',        label: 'Cerrada' },
  { value: 'contabilizada',  label: 'Contabilizada' },
  { value: 'pagada',         label: 'Pagada' },
]

interface Props {
  onEstadoChange: (v: string | null) => void
}

export function NominaToolbar({ onEstadoChange }: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()
  const { calcular } = useNominaMutations()
  const [mes, setMes] = useState<Date | null>(null)

  const handleCalcular = () => {
    if (!mes) return
    const periodo = `${mes.getFullYear()}-${String(mes.getMonth() + 1).padStart(2, '0')}`
    calcular.mutate(periodo)
  }

  const fields = (
    <>
      <MonthPickerInput
        label="Período"
        placeholder="Seleccionar mes"
        valueFormat="YYYY-MM"
        {...contained}
        value={mes}
        onChange={(val: any) => setMes(val)}
        style={{ minWidth: 180 }}
      />
      <Select
        label="Estado"
        placeholder="Todos"
        data={ESTADO_OPTIONS}
        clearable
        {...contained}
        onChange={onEstadoChange}
        style={{ minWidth: 160 }}
      />
      <Button
        size="sm"
        color="emerald"
        variant="light"
        leftSection={<IconCalculator size={16} />}
        loading={calcular.isPending}
        disabled={!mes}
        onClick={handleCalcular}
      >
        Calcular nómina
      </Button>
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md" align="flex-end">{fields}</Group>
}
