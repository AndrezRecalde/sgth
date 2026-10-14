import { Group, Progress, Stack, Text } from '@mantine/core'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'

export type FilaReparto = {
  clave: string
  etiqueta: string
  total: number
  tono?: SemanticTone
}

/**
 * Un reparto en barras horizontales, cada una contra la mayor. Las filas en
 * cero se ven, atenuadas: que no haya equipos extraviados también es un dato.
 */
export function Reparto({ filas, vacio }: { filas: FilaReparto[]; vacio: string }) {
  const mayor = Math.max(0, ...filas.map((f) => f.total))
  if (mayor === 0) return <Text size="sm" c="dimmed">{vacio}</Text>

  return (
    <Stack gap="sm">
      {filas.map((f) => (
        <Stack key={f.clave} gap={4}>
          <Group justify="space-between" gap="xs" wrap="nowrap">
            <Text size="sm" lineClamp={1} c={f.total === 0 ? 'dimmed' : undefined}>{f.etiqueta}</Text>
            <Text size="sm" fw={600} c={f.total === 0 ? 'dimmed' : undefined}>{f.total}</Text>
          </Group>
          <Progress value={(f.total / mayor) * 100} size="sm" radius="xl" color={SEMANTIC_COLOR[f.tono ?? 'info']} />
        </Stack>
      ))}
    </Stack>
  )
}
