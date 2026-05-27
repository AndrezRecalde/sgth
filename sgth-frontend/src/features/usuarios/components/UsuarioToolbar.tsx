'use client'

import { TextInput, Select, Group, Stack } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useRoles } from '../hooks/useRoles'

interface Props {
  onSearch:    (v: string) => void
  onRolChange: (v: string | null) => void
}

export function UsuarioToolbar({ onSearch, onRolChange }: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()
  const { data: roles = [] } = useRoles()

  const rolOptions = (roles as string[]).map(r => ({
    value: r,
    label: r.replace(/-/g, ' ')
             .replace(/\b\w/g, c => c.toUpperCase()),
  }))

  const fields = (
    <>
      <TextInput
        placeholder="Buscar por nombre, email o usuario"
        onChange={(e) => onSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        placeholder="Filtrar por rol"
        data={rolOptions}
        onChange={onRolChange}
        clearable
        searchable
        {...contained}
        style={{ minWidth: 200 }}
      />
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md" align="flex-end">{fields}</Group>
}
