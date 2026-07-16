'use client'

import { Stack, Grid, Text, Checkbox, NumberInput, TextInput, Card } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ConsumoSustanciaForm } from '../../schemas/femo.schema'
import { SUSTANCIA_OPTIONS } from '../../services/femoOptions'

interface Props {
  data:     ConsumoSustanciaForm[]
  onChange: (data: ConsumoSustanciaForm[]) => void
}

export function FemoConsumoSustanciasTable({ data, onChange }: Props) {
  const contained = useContainedInput()

  const getFila = (sustancia: string): ConsumoSustanciaForm =>
    data.find(d => d.sustancia === sustancia) ?? {
      sustancia: sustancia as ConsumoSustanciaForm['sustancia'],
      ex_consumidor: false,
      no_consume: false,
    }

  const setFila = (sustancia: string, valores: Partial<ConsumoSustanciaForm>) => {
    const existente = getFila(sustancia)
    const actualizada = { ...existente, ...valores }
    const resto = data.filter(d => d.sustancia !== sustancia)
    onChange([...resto, actualizada])
  }

  return (
    <Stack gap="xs">
      <Text size="xs" fw={600} c="dimmed" tt="uppercase" style={{ letterSpacing: '0.05em' }}>
        Consumo de sustancias
      </Text>
      <Stack gap="sm">
        {SUSTANCIA_OPTIONS.map((opt) => {
          const fila = getFila(opt.value)
          return (
            <Card key={opt.value} withBorder radius="md" p="sm">
              <Grid align="flex-end">
                <Grid.Col span={{ base: 12, md: 2 }}>
                  <Text size="sm" fw={500}>{opt.label}</Text>
                </Grid.Col>
                {opt.value === 'otra' && (
                  <Grid.Col span={{ base: 12, md: 3 }}>
                    <TextInput
                      label="¿Cuál?"
                      {...contained}
                      value={fila.sustancia_otra_detalle ?? ''}
                      onChange={(e) => setFila(opt.value, {
                        sustancia_otra_detalle: e.currentTarget.value,
                      })}
                    />
                  </Grid.Col>
                )}
                <Grid.Col span={{ base: 6, md: 2 }}>
                  <NumberInput
                    label="Tiempo consumo (meses)"
                    min={0}
                    disabled={fila.no_consume}
                    {...contained}
                    value={fila.tiempo_consumo_meses ?? undefined}
                    onChange={(v) => setFila(opt.value, {
                      tiempo_consumo_meses: v !== '' ? Number(v) : null,
                    })}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, md: 2 }}>
                  <Checkbox
                    label="Ex-consumidor"
                    mt={24}
                    disabled={fila.no_consume}
                    checked={fila.ex_consumidor ?? false}
                    onChange={(e) => setFila(opt.value, {
                      ex_consumidor: e.currentTarget.checked,
                    })}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, md: 2 }}>
                  <NumberInput
                    label="Abstinencia (meses)"
                    min={0}
                    disabled={!fila.ex_consumidor}
                    {...contained}
                    value={fila.tiempo_abstinencia_meses ?? undefined}
                    onChange={(v) => setFila(opt.value, {
                      tiempo_abstinencia_meses: v !== '' ? Number(v) : null,
                    })}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, md: 1 }}>
                  <Checkbox
                    label="No consume"
                    mt={24}
                    checked={fila.no_consume ?? false}
                    onChange={(e) => setFila(opt.value, {
                      no_consume: e.currentTarget.checked,
                    })}
                  />
                </Grid.Col>
              </Grid>
            </Card>
          )
        })}
      </Stack>
    </Stack>
  )
}
