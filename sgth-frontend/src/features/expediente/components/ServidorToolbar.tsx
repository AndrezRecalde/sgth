'use client'

import { useState, useEffect } from 'react'
import { useDebouncedValue } from '@mantine/hooks'
import { TextInput, Select, Group, Stack } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

const ESTADO_OPTIONS = [
  { value: 'vigente',   label: 'Vigente' },
  { value: 'terminado', label: 'Terminado' },
  { value: 'cancelado', label: 'Cancelado' },
]

interface Props {
  onSearch:       (v: string) => void
  onEstadoChange: (v: string | null) => void
}

export function ServidorToolbar({ onSearch, onEstadoChange }: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()

  const [localSearch, setLocalSearch] = useState('')
  const [debounced] = useDebouncedValue(localSearch, 400)

  useEffect(() => {
    onSearch(debounced)
  }, [debounced, onSearch])

  const fields = (
    <>
      <TextInput
        label="Buscar servidor"
        placeholder="Nombre o cédula"
        value={localSearch}
        onChange={(e) => setLocalSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        label="Estado contrato"
        placeholder="Todos"
        data={ESTADO_OPTIONS}
        onChange={onEstadoChange}
        clearable
        {...contained}
        style={{ minWidth: 180 }}
      />
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md">{fields}</Group>
}
