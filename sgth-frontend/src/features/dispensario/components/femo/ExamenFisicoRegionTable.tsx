'use client'

import { Stack, Grid, Text, Checkbox, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ExamenFisicoItemForm } from '../../schemas/femo.schema'

interface Props {
  region:   string
  items:    string[]
  valores:  ExamenFisicoItemForm[]
  onChange: (valores: ExamenFisicoItemForm[]) => void
}

/**
 * Los ítems de una región de la sección F.
 *
 * Como el impreso, se marca la **patología**, no la normalidad: «si existe
 * evidencia de patología marcar con X y describir». Un ítem sin marcar es un
 * ítem sin hallazgos, y así lo imprime el PDF.
 */
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
      {items.map((item, i) => {
        const valor = getItem(item)
        const letra = String.fromCharCode(97 + i)
        return (
          <Grid key={item} align="center">
            <Grid.Col span={{ base: 8, sm: 4 }}>
              <Text size="sm">{letra}. {item}</Text>
            </Grid.Col>
            <Grid.Col span={{ base: 4, sm: 2 }}>
              <Checkbox
                label="Patología"
                checked={!valor.normal}
                onChange={(e) => setItem(item, {
                  normal: !e.currentTarget.checked,
                  // Sin patología no hay nada que describir.
                  observacion: e.currentTarget.checked ? valor.observacion : null,
                })}
              />
            </Grid.Col>
            {!valor.normal && (
              <Grid.Col span={{ base: 12, sm: 6 }}>
                <TextInput
                  label={`Hallazgo en ${letra}. ${item}`}
                  placeholder="Describa el hallazgo"
                  {...contained}
                  value={valor.observacion ?? ''}
                  onChange={(e) => setItem(item, { observacion: e.currentTarget.value })}
                />
              </Grid.Col>
            )}
          </Grid>
        )
      })}
    </Stack>
  )
}
