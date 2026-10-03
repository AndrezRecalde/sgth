'use client'

import { Stack, Grid, Select, Group, Text, Button, Card, ActionIcon } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarCie10Input } from '../BuscarCie10Input'
import type { DiagnosticoFemoForm } from '../../schemas/femo.schema'
import type { DiagnosticoCie10 } from '../../services/cie10Service'
import { FemoSeccion } from './FemoSeccion'
import { confirmar, StatusBadge } from '@/components/ui'

interface Props {
  diagnosticos:     DiagnosticoFemoForm[]
  onChange:         (data: DiagnosticoFemoForm[]) => void
}

const MAXIMO = 6

export function FemoDiagnosticosCie10({ diagnosticos, onChange }: Props) {
  const contained = useContainedInput()
  const [cie10Sel, setCie10Sel] = useState<DiagnosticoCie10 | null>(null)
  const [tipoDiag, setTipoDiag] = useState<'presuntivo' | 'definitivo'>('presuntivo')

  const repetido = !!cie10Sel && diagnosticos.some(d => d.diagnostico_cie10_id === cie10Sel.id)

  const handleAgregar = () => {
    if (!cie10Sel || repetido || diagnosticos.length >= MAXIMO) return
    onChange([
      ...diagnosticos,
      {
        diagnostico_cie10_id: cie10Sel.id,
        tipo:  tipoDiag,
        orden: diagnosticos.length + 1,
        // Solo para mostrarlo: antes la lista decía «CIE-10 #123».
        diagnostico: { codigo: cie10Sel.codigo, descripcion: cie10Sel.descripcion },
      },
    ])
    setCie10Sel(null)
  }

  const handleEliminar = (idx: number) => {
    const d = diagnosticos[idx]
    confirmar({
      title: 'Quitar diagnóstico',
      message: <>Se quitará <b>{d.diagnostico?.codigo ?? 'el diagnóstico'}</b> de la ficha.</>,
      confirmLabel: 'Quitar',
      destructiva: true,
      onConfirm: () => onChange(
        diagnosticos.filter((_, i) => i !== idx).map((x, i) => ({ ...x, orden: i + 1 }))
      ),
    })
  }

  return (
    <FemoSeccion letra="K" titulo={`Diagnóstico CIE-10 (máx. ${MAXIMO})`}>
      {diagnosticos.length < MAXIMO && (
        <Grid align="flex-end">
          <Grid.Col span={{ base: 12, md: 7 }}>
            <BuscarCie10Input value={cie10Sel} onChange={setCie10Sel} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <Select
              label="Tipo"
              data={[
                { value: 'presuntivo', label: 'Presuntivo (PRE)' },
                { value: 'definitivo', label: 'Definitivo (DEF)' },
              ]}
              {...contained}
              value={tipoDiag}
              onChange={(v) => setTipoDiag((v ?? 'presuntivo') as 'presuntivo' | 'definitivo')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, md: 2 }}>
            {/* A la altura de los campos de la fila, no 12px más bajo. */}
            <Button
              fullWidth
              h={48}
              leftSection={<IconPlus size={13} />}
              disabled={!cie10Sel || repetido}
              onClick={handleAgregar}
            >
              Agregar
            </Button>
          </Grid.Col>
          {repetido && (
            <Grid.Col span={12}>
              <Text size="xs" c="dimmed">Ese diagnóstico ya está en la lista.</Text>
            </Grid.Col>
          )}
        </Grid>
      )}

      {diagnosticos.length === 0 ? (
        <Text size="sm" c="dimmed">Ningún diagnóstico registrado.</Text>
      ) : (
        <Stack gap="xs">
          {diagnosticos.map((d, i) => (
            <Card key={d.diagnostico_cie10_id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="xs" wrap="nowrap">
                  <Text size="xs" c="dimmed">{i + 1}.</Text>
                  <StatusBadge tone={d.tipo === 'definitivo' ? 'success' : 'warning'}>
                    {d.tipo === 'definitivo' ? 'DEF' : 'PRE'}
                  </StatusBadge>
                  <Text size="sm" fw={500} ff="monospace">{d.diagnostico?.codigo ?? '—'}</Text>
                  <Text size="sm" lineClamp={1}>{d.diagnostico?.descripcion ?? ''}</Text>
                </Group>
                <ActionIcon
                  size="sm"
                  color="red"
                  variant="subtle"
                  aria-label={`Quitar el diagnóstico ${d.diagnostico?.codigo ?? i + 1}`}
                  onClick={() => handleEliminar(i)}
                >
                  <IconTrash size={13} />
                </ActionIcon>
              </Group>
            </Card>
          ))}
        </Stack>
      )}
    </FemoSeccion>
  )
}
