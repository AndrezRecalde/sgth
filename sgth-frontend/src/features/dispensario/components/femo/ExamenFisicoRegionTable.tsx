'use client'

import { Stack, Group, Text, Checkbox, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ExamenFisicoItemForm } from '../../schemas/femo.schema'

interface Props {
  region:   string
  items:    string[]
  valores:  ExamenFisicoItemForm[]
  onChange: (valores: ExamenFisicoItemForm[]) => void
}

export function ExamenFisicoRegionTable({ region, items, valores, onChange }: Props) {
  const contained = useContainedInput()

  const getItem = (item: string): ExamenFisicoItemForm =>
    valores.find(v => v.region === region && v.item === item) ??
    { region, item, normal: true, observacion: null }

  const setItem = (item: string, cambios: Partial<ExamenFisicoItemForm>) => {
    const actual = getItem(item)
    const actualizado = { ...actual, ...cambios }
    const resto = valores.filter(v => !(v.region === region && v.item === item))
    onChange([...resto, actualizado])
  }

  return (
    <Stack gap="xs">
      {items.map((item) => {
        const valor = getItem(item)
        return (
          <Group key={item} align="flex-start" wrap="nowrap">
            <Text size="sm" w={180}>{item}</Text>
            <Checkbox
              label="Normal"
              checked={valor.normal}
              onChange={(e) => setItem(item, {
                normal: e.currentTarget.checked,
                observacion: e.currentTarget.checked ? null : valor.observacion,
              })}
            />
            {!valor.normal && (
              <TextInput
                size="xs"
                placeholder="Observación / hallazgo"
                style={{ flex: 1 }}
                {...contained}
                value={valor.observacion ?? ''}
                onChange={(e) => setItem(item, { observacion: e.currentTarget.value })}
              />
            )}
          </Group>
        )
      })}
    </Stack>
  )
}
