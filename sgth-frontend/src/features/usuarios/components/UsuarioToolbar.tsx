'use client'

import { useState } from 'react'
import { TextInput, Select, Group, Stack, ActionIcon } from '@mantine/core'
import { IconSearch, IconX } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useRoles } from '../hooks/useRoles'

interface Props {
  /** El retardo lo aplica la vista; aquí se propaga cada pulsación. */
  onSearch:       (v: string) => void
  onRolChange:    (v: string | null) => void
  onEstadoChange: (v: string | null) => void
}

const ESTADOS = [
  { value: 'true',  label: 'Activos' },
  { value: 'false', label: 'Inactivos' },
]

export function UsuarioToolbar({ onSearch, onRolChange, onEstadoChange }: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()
  const { data: roles = [] } = useRoles()

  // Estado local solo para poder ofrecer el botón de limpiar.
  const [texto, setTexto] = useState('')

  const escribir = (v: string) => {
    setTexto(v)
    onSearch(v)
  }

  const rolOptions = roles.map(r => ({ value: r.valor, label: r.etiqueta }))

  const fields = (
    <>
      <TextInput
        label="Buscar usuario"
        placeholder="Nombre, cédula, correo o usuario"
        value={texto}
        onChange={(e) => escribir(e.currentTarget.value)}
        leftSection={<IconSearch size={14} />}
        rightSection={
          texto ? (
            <ActionIcon
              size="sm"
              variant="subtle"
              color="gray"
              aria-label="Limpiar búsqueda"
              onClick={() => escribir('')}
            >
              <IconX size={12} />
            </ActionIcon>
          ) : null
        }
        {...contained}
        style={{ flex: 1 }}
      />
      <Select
        label="Rol"
        placeholder="Todos"
        data={rolOptions}
        onChange={onRolChange}
        clearable
        searchable
        {...contained}
        style={{ minWidth: 200 }}
      />
      <Select
        label="Estado"
        placeholder="Todos"
        data={ESTADOS}
        onChange={onEstadoChange}
        clearable
        {...contained}
        style={{ minWidth: 140 }}
      />
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md" align="flex-end">{fields}</Group>
}
