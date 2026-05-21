'use client'

import { TextInput, Select, Group, Stack, Button } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

const ESTADO_OPTIONS = [
  { value: 'vigente',   label: 'Vigente' },
  { value: 'terminado', label: 'Terminado' },
  { value: 'cancelado', label: 'Cancelado' },
]

interface Props {
  onSearch: (v: string) => void
  onEstadoChange: (v: string | null) => void
  onNuevo: () => void
}

export function ServidorToolbar({ onSearch, onEstadoChange, onNuevo }: Props) {
  const contained  = useContainedInput()
  const { isMobile } = useMobileBreakpoint()

  const fields = (
    <>
      <TextInput
        placeholder="Buscar por nombre o cédula"
        onChange={(e) => onSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        placeholder="Estado contrato"
        data={ESTADO_OPTIONS}
        onChange={onEstadoChange}
        clearable
        {...contained}
        style={{ minWidth: 180 }}
      />
      <Button
        leftSection={<IconPlus size={16} />}
        color="emerald"
        onClick={onNuevo}
      >
        Nuevo servidor
      </Button>
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md" align="flex-end">{fields}</Group>
}
