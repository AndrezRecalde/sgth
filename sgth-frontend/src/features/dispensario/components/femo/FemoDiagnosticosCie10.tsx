'use client'

import { Stack, Grid, Select, Group, Text, Button, Card, ActionIcon, Badge } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarCie10Input } from '../BuscarCie10Input'
import type { DiagnosticoFemoForm } from '../../schemas/femo.schema'
import type { DiagnosticoCie10 } from '../../services/cie10Service'

interface Props {
  diagnosticos:     DiagnosticoFemoForm[]
  onChange:         (data: DiagnosticoFemoForm[]) => void
}

export function FemoDiagnosticosCie10({ diagnosticos, onChange }: Props) {
  const contained = useContainedInput()
  const [cie10Sel, setCie10Sel] = useState<DiagnosticoCie10 | null>(null)
  const [tipoDiag, setTipoDiag] = useState<'presuntivo' | 'definitivo'>('presuntivo')

  const handleAgregar = () => {
    if (!cie10Sel) return
    if (diagnosticos.length >= 6) return
    if (diagnosticos.find(d => d.diagnostico_cie10_id === cie10Sel.id)) return
    onChange([
      ...diagnosticos,
      { diagnostico_cie10_id: cie10Sel.id, tipo: tipoDiag, orden: diagnosticos.length + 1 },
    ])
    setCie10Sel(null)
  }

  const handleEliminar = (idx: number) => {
    onChange(
      diagnosticos.filter((_, i) => i !== idx).map((d, i) => ({ ...d, orden: i + 1 }))
    )
  }

  return (
    <Stack gap="xs">
      <Text size="xs" fw={600} c="dimmed" tt="uppercase" style={{ letterSpacing: '0.05em' }}>
        K. Diagnósticos CIE-10 (máx. 6)
      </Text>

      {diagnosticos.length < 6 && (
        <Grid align="flex-end">
          <Grid.Col span={{ base: 12, md: 7 }}>
            <BuscarCie10Input value={cie10Sel} onChange={setCie10Sel} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 3 }}>
            <Select
              label="Tipo"
              data={[
                { value: 'presuntivo', label: 'Presuntivo' },
                { value: 'definitivo', label: 'Definitivo' },
              ]}
              {...contained}
              value={tipoDiag}
              onChange={(v) => setTipoDiag((v ?? 'presuntivo') as 'presuntivo' | 'definitivo')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 2 }}>
            <Button
              fullWidth
              color="emerald"
              leftSection={<IconPlus size={13} />}
              disabled={!cie10Sel}
              onClick={handleAgregar}
            >
              Agregar
            </Button>
          </Grid.Col>
        </Grid>
      )}

      {diagnosticos.length === 0 ? (
        <Text size="sm" c="dimmed">Ningún diagnóstico registrado.</Text>
      ) : (
        <Stack gap="xs">
          {diagnosticos.map((d, i) => (
            <Card key={i} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="xs">
                  <Text size="xs" c="dimmed">{i + 1}.</Text>
                  <Badge size="sm" variant="light" color={d.tipo === 'definitivo' ? 'emerald' : 'orange'}>
                    {d.tipo}
                  </Badge>
                  <Text size="sm" fw={500}>CIE-10 #{d.diagnostico_cie10_id}</Text>
                </Group>
                <ActionIcon size="sm" color="red" variant="subtle" onClick={() => handleEliminar(i)}>
                  <IconTrash size={13} />
                </ActionIcon>
              </Group>
            </Card>
          ))}
        </Stack>
      )}
    </Stack>
  )
}
