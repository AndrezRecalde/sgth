'use client'

import { SgthDrawer } from '@/components/ui'
import { useState } from 'react'
import { Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useContainedInput } from '@/hooks/useContainedInput'
import { AtencionesEnfermeriaTable } from './AtencionesEnfermeriaTable'
import { fromDateValue, hoyIso } from '@/lib/fecha'

interface Props {
  opened:  boolean
  onClose: () => void
}

export function AtencionesEnfermeriaDrawer({
  opened, onClose,
}: Props) {
  const contained = useContainedInput('sm')
  // La fecha como `AAAA-MM-DD`, que es lo que devuelve el selector y lo que
  // pide el API: el estado en `Date` obligaba a reparsear la cadena a mano.
  const [fecha, setFecha] = useState(hoyIso)

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
          onChange={(v) => setFecha(v ? fromDateValue(v) : hoyIso())}
          valueFormat="DD/MM/YYYY"
        />

        <AtencionesEnfermeriaTable fecha={fecha} />
      </Stack>
    </SgthDrawer>
  )
}
