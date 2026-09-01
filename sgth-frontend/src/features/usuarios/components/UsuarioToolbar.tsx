'use client'

import { TextInput, Select, Group, Stack, ActionIcon } from '@mantine/core'
import { IconSearch, IconX } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useRoles } from '../hooks/useRoles'

interface Props {
  /**
   * Los tres controles son controlados por la vista. Con `Select` sin `value`,
   * «Limpiar filtros» vaciaba el estado y traía la lista completa, pero el
   * desplegable seguía mostrando el filtro anterior: la pantalla decía
   * «Inactivos» mientras listaba a todos.
   */
  search: string
  rol:    string | null
  estado: string | null
  /** El retardo lo aplica la vista; aquí se propaga cada pulsación. */
  onSearch:       (v: string) => void
  onRolChange:    (v: string | null) => void
  onEstadoChange: (v: string | null) => void
}

const ESTADOS = [
  { value: 'true',  label: 'Activos' },
  { value: 'false', label: 'Inactivos' },
]

export function UsuarioToolbar({
  search,
  rol,
  estado,
  onSearch,
  onRolChange,
  onEstadoChange,
}: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()
  const { data: roles = [] } = useRoles()

  const rolOptions = roles.map(r => ({ value: r.valor, label: r.etiqueta }))

  const fields = (
    <>
      <TextInput
        label="Buscar usuario"
        placeholder="Nombre, cédula, correo o usuario"
        value={search}
        onChange={(e) => onSearch(e.currentTarget.value)}
        leftSection={<IconSearch size={14} />}
        rightSection={
          search ? (
            <ActionIcon
              size="sm"
              variant="subtle"
              color="gray"
              aria-label="Limpiar búsqueda"
              onClick={() => onSearch('')}
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
        value={rol}
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
        value={estado}
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
