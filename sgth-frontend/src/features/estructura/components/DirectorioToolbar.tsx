'use client'

import { TextInput, Select, ActionIcon, Stack, Group } from '@mantine/core'
import { IconX } from '@tabler/icons-react'
import { useUnidades } from '../hooks/useUnidades'
import type { UnidadConRelaciones } from '@/types/api'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

interface Props {
  onSearch:       (value: string) => void
  onUnidadChange: (value: string | null) => void
  onClear:        () => void
}

export function DirectorioToolbar({ onSearch, onUnidadChange, onClear }: Props) {
  const { data: unidades = [] } = useUnidades({ nivel: 2 })
  const { isMobile }            = useMobileBreakpoint()
  const contained               = useContainedInput()

  const unidadOptions = (unidades as unknown as UnidadConRelaciones[]).map(u => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const content = (
    <>
      <TextInput
        placeholder="Buscar por responsable, extensión o unidad"
        onChange={(e) => onSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        placeholder="Filtrar por gestión"
        data={unidadOptions}
        onChange={onUnidadChange}
        searchable
        clearable
        {...contained}
        style={{ minWidth: 240 }}
      />
      <ActionIcon
        variant="light"
        color="gray"
        size="lg"
        onClick={onClear}
        title="Limpiar filtros"
      >
        <IconX size={16} />
      </ActionIcon>
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{content}</Stack>
    : <Group gap="sm" mb="md" wrap="nowrap">{content}</Group>
}
