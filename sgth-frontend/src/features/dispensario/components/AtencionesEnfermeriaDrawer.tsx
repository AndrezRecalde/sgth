'use client'

import { useState } from 'react'
import {
  Drawer, Stack, Group, Text, ThemeIcon,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconVaccine } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
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
  const { isMobile } = useMobileBreakpoint()
  const [fecha, setFecha] = useState<Date | null>(new Date())

  const fechaStr = formatFechaLocal(fecha ?? new Date())

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="violet" variant="light" size="md" radius="md">
            <IconVaccine size={16} />
          </ThemeIcon>
          <Text fw={700} size="sm">
            Servicios de enfermería
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 520}
      padding="lg"
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
    </Drawer>
  )
}
