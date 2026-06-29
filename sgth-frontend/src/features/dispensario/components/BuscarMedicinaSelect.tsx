'use client'

import { useState } from 'react'
import {
  Combobox, TextInput, useCombobox,
  Text, Group, Button,
} from '@mantine/core'
import { IconSearch, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useQuery } from '@tanstack/react-query'
import { inventarioMedicinaService } from '../services/inventarioMedicinaService'

interface Props {
  onSeleccionar:  (id: number, nombre: string) => void
  onCrearNueva:   () => void
}

export function BuscarMedicinaSelect({
  onSeleccionar, onCrearNueva,
}: Props) {
  const contained = useContainedInput()
  const [termino, setTermino] = useState('')
  const combobox = useCombobox({
    onDropdownClose: () => combobox.resetSelectedOption(),
  })

  const { data: resultados = [] } = useQuery({
    queryKey: ['medicinas-buscar', termino],
    queryFn:  () => inventarioMedicinaService.buscar(termino),
    enabled:  termino.length >= 2,
    staleTime: 1000 * 30,
  })

  return (
    <Combobox
      store={combobox}
      onOptionSubmit={(id) => {
        if (id === '__nueva__') {
          onCrearNueva()
          setTermino('')
          combobox.closeDropdown()
          return
        }
        const seleccionada = resultados.find(
          m => String(m.id) === id
        )
        if (seleccionada) {
          onSeleccionar(seleccionada.id, seleccionada.nombre)
        }
        setTermino('')
        combobox.closeDropdown()
      }}
    >
      <Combobox.Target>
        <TextInput
          placeholder="Buscar medicina por nombre o código"
          leftSection={<IconSearch size={14} />}
          size="sm"
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
          {termino.length < 2 ? (
            <Combobox.Empty>
              Escribe al menos 2 caracteres
            </Combobox.Empty>
          ) : resultados.length === 0 ? (
            <Combobox.Empty>Sin resultados</Combobox.Empty>
          ) : (
            resultados.map((m) => (
              <Combobox.Option value={String(m.id)} key={m.id}>
                <Group gap={6} justify="space-between">
                  <Text size="sm">
                    {m.nombre}
                    {m.concentracion && (
                      <Text span c="dimmed"> — {m.concentracion}</Text>
                    )}
                  </Text>
                  <Text size="xs" c="dimmed">
                    Stock: {m.stock_actual}
                  </Text>
                </Group>
              </Combobox.Option>
            ))
          )}

          <Combobox.Option value="__nueva__">
            <Group gap={6} c="emerald">
              <IconPlus size={13} />
              <Text size="sm" fw={500}>
                Crear medicina nueva
              </Text>
            </Group>
          </Combobox.Option>
        </Combobox.Options>
      </Combobox.Dropdown>
    </Combobox>
  )
}
