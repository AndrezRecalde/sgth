'use client'

import { Checkbox, Group, NumberInput, Radio, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { CriterioEvaluacion } from '../../services/criterioService'
import type { ValorCriterio } from './calificacion'

interface Props {
  criterio: CriterioEvaluacion
  value:    ValorCriterio
  onChange: (v: ValorCriterio) => void
  error?:   string
}

const Etiqueta = ({ texto, puntaje }: { texto: string; puntaje: string }) => (
  <Group gap="xs">
    <Text size="sm">{texto}</Text>
    <StatusBadge size="xs">{puntaje}</StatusBadge>
  </Group>
)

/** El campo de un criterio según su tipo: número, una opción o varias. */
export function CriterioInput({ criterio, value, onChange, error }: Props) {
  const contained = useContainedInput()
  const maximo = Number(criterio.puntaje_maximo)

  if (criterio.tipo_input === 'numero') {
    return (
      <NumberInput
        label="Puntaje"
        placeholder={`0 — ${maximo} pts`}
        min={0}
        max={maximo}
        decimalScale={2}
        {...contained}
        value={value.valor_numerico ?? ''}
        // 0 es un puntaje válido: `Number(v) || null` lo borraba.
        onChange={(v) => onChange({ valor_numerico: v === '' ? null : Number(v) })}
        error={error}
      />
    )
  }

  if (criterio.tipo_input === 'radio') {
    return (
      <Radio.Group
        value={value.opcion_id != null ? String(value.opcion_id) : null}
        onChange={(v) => onChange({ opcion_id: v ? Number(v) : null })}
        error={error}
      >
        <Stack gap="xs">
          {criterio.opciones.map(op => (
            <Radio key={op.id} value={String(op.id)} size="sm"
              label={<Etiqueta texto={op.etiqueta} puntaje={`${op.puntaje} pts`} />} />
          ))}
        </Stack>
      </Radio.Group>
    )
  }

  const marcadas = value.opciones_ids ?? []
  return (
    <Checkbox.Group
      value={marcadas.map(String)}
      onChange={(v) => onChange({ opciones_ids: v.map(Number) })}
      error={error}
    >
      <Stack gap="xs">
        {criterio.opciones.map(op => (
          <Checkbox key={op.id} value={String(op.id)} size="sm"
            label={<Etiqueta texto={op.etiqueta} puntaje={`+${op.puntaje} pts`} />} />
        ))}
      </Stack>
    </Checkbox.Group>
  )
}
