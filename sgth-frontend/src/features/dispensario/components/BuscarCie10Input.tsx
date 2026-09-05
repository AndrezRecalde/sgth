'use client'

import { useState } from 'react'
import {
  TextInput, Badge, Stack, Combobox,
  useCombobox, Text, Group, ActionIcon,
} from '@mantine/core'
import { IconX } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useBuscarCie10 } from '../hooks/useCie10'
import type { DiagnosticoCie10 } from '../services/cie10Service'

interface Props {
  value:    DiagnosticoCie10 | null
  onChange: (diagnostico: DiagnosticoCie10 | null) => void
}

export function BuscarCie10Input({ value, onChange }: Props) {
  const contained = useContainedInput()
  const [termino, setTermino] = useState('')
  const combobox = useCombobox({
    onDropdownClose: () => combobox.resetSelectedOption(),
  })

  const { data: busqueda } = useBuscarCie10(termino)
  const resultados = busqueda?.resultados ?? []

  if (value) {
    return (
      <Stack gap={4}>
        <Text size="sm" fw={500}>Diagnóstico (CIE-10)</Text>
        <Group gap={6}>
          <Badge
            size="lg"
            variant="light"
            color="blue"
            rightSection={
              <ActionIcon
                size="xs"
                variant="transparent"
                color="blue"
                onClick={() => onChange(null)}
              >
                <IconX size={12} />
              </ActionIcon>
            }
          >
            <Text span ff="monospace" fw={600}>
              {value.codigo}
            </Text>
            {' — '}{value.descripcion}
          </Badge>
        </Group>
      </Stack>
    )
  }

  return (
    <Combobox
      store={combobox}
      onOptionSubmit={(id) => {
        const seleccionado = resultados.find(
          d => String(d.id) === id
        )
        if (seleccionado) onChange(seleccionado)
        setTermino('')
        combobox.closeDropdown()
      }}
    >
      <Combobox.Target>
        <TextInput
          label="Diagnóstico (CIE-10)"
          placeholder="Buscar código o descripción del diagnóstico"
          {...contained}
          value={termino}
          onChange={(e) => {
            setTermino(e.currentTarget.value)
            combobox.openDropdown()
          }}
          onFocus={() => combobox.openDropdown()}
        />
      </Combobox.Target>

      <Combobox.Dropdown>
        <Combobox.Options mah={220} style={{ overflowY: 'auto' }}>
          {resultados.length === 0 ? (
            <Combobox.Empty>
              {termino.length < 2
                ? 'Escribe al menos 2 caracteres'
                : 'Sin resultados'}
            </Combobox.Empty>
          ) : (
            resultados.map((d) => (
              <Combobox.Option value={String(d.id)} key={d.id}>
                <Group gap={6} wrap="nowrap">
                  <Text size="xs" ff="monospace" fw={600}>
                    {d.codigo}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {d.descripcion}
                  </Text>
                </Group>
              </Combobox.Option>
            ))
          )}
        </Combobox.Options>

        {/* El recorte a 20 se hacía en silencio: con 8918 códigos, quien
            buscaba algo general veía veinte y creía que eran todos. */}
        {busqueda?.hayMas && (
          <Combobox.Footer>
            <Text size="xs" c="dimmed">
              Mostrando {resultados.length} de {busqueda.total}.
              Añada otra palabra para acotar.
            </Text>
          </Combobox.Footer>
        )}
      </Combobox.Dropdown>
    </Combobox>
  )
}
