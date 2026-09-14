'use client'

import { SgthDrawer } from '@/components/ui'
import { useState } from 'react'
import { Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { AtencionesEnfermeriaTable } from './AtencionesEnfermeriaTable'

interface Props {
  opened:  boolean
  onClose: () => void
}

function formatFechaLocal(d: Date): string {
  const year  = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day   = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function AtencionesEnfermeriaDrawer({
  opened, onClose,
}: Props) {
  const [fecha, setFecha] = useState<Date | null>(new Date())

  const fechaStr = formatFechaLocal(fecha ?? new Date())

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Servicios de enfermería"
    >
      <Stack gap="md">
        <DatePickerInput
          label="Fecha"
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
