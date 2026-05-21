'use client'

import { TextInput, Select, ActionIcon, Stack, Group } from '@mantine/core'
import { IconX } from '@tabler/icons-react'
import { useUnidades } from '../hooks/useUnidades'
import type { UnidadConRelaciones } from '@/types/api'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

interface DirectorioToolbarProps {
  onSearch: (value: string) => void
  onUnidadChange: (value: string | null) => void
  onClear: () => void
}

export function DirectorioToolbar({ onSearch, onUnidadChange, onClear }: DirectorioToolbarProps) {
  const { data: unidades = [] } = useUnidades()
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()

  const unidadOptions = unidades.map(u => ({
    value: u.id.toString(),
    label: (u as UnidadConRelaciones).nombre ?? `Unidad ${u.id}`
  }))

  const content = (
    <>
      <TextInput
        placeholder="Buscar por nombre, unidad o extensión"
        onChange={(e) => onSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        placeholder="Filtrar por unidad"
        data={unidadOptions}
        onChange={onUnidadChange}
        searchable
        clearable
        {...contained}
        style={{ flex: 1 }}
      />
      <ActionIcon 
        variant="light" 
        color="gray" 
        size="lg" 
        onClick={onClear}
        title="Limpiar filtros"
      >
        <IconX size={20} />
      </ActionIcon>
    </>
  )

  if (isMobile) {
    return <Stack gap="sm" mb="md">{content}</Stack>
  }

  return <Group gap="sm" mb="md" wrap="nowrap">{content}</Group>
}
