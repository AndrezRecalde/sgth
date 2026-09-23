'use client'

import { SgthDrawer } from '@/components/ui'
import { useState } from 'react'
import { Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { AtencionesEnfermeriaTable } from './AtencionesEnfermeriaTable'
import { fromDateValue } from '@/lib/fecha'

interface Props {
  opened:  boolean
  onClose: () => void
}

export function AtencionesEnfermeriaDrawer({
  opened, onClose,
}: Props) {
  const contained = useContainedInput('sm')
  const [fecha, setFecha] = useState<Date | null>(new Date())

  const fechaStr = fromDateValue(fecha ?? new Date())

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Servicios de enfermería"
    >
      <Stack gap="md">
        <DatePickerInput
          label="Fecha"
          {...contained}
          value={fecha}
          onChange={(v) => {
            if (!v) { setFecha(new Date()); return }
            const str = typeof v === 'string' ? v : String(v)
            const [y, m, d] = str.slice(0, 10).split('-').map(Number)
            setFecha(new Date(y, m - 1, d))
          }}
          valueFormat="DD/MM/YYYY"
        />

        <AtencionesEnfermeriaTable fecha={fechaStr} />
      </Stack>
    </SgthDrawer>
  )
}
