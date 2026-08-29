import { Group, Paper, Skeleton, Stack, Text, ThemeIcon } from '@mantine/core'
import type { Icon } from '@tabler/icons-react'
import type { SemanticTone } from '@/config/design.tokens'
import { SEMANTIC_COLOR } from '@/config/design.tokens'

interface Props {
  label: string
  value: React.ReactNode
  icon: Icon
  /** Tono semántico del indicador. Por defecto sigue el acento del subsistema. */
  tone?: SemanticTone
  /** Contexto bajo la cifra: "+3 este mes", "sobre 247 servidores". */
  hint?: string
  loading?: boolean
}

/**
 * Indicador numérico de un tablero.
 *
 * La cifra es lo único grande de la tarjeta; la etiqueta va arriba en
 * minúscula y pequeña. El icono es soporte visual, no protagonista, por eso
 * es un ThemeIcon `light` y no un bloque de color.
 *
 * `tone` se reserva para cuando el número TIENE una lectura buena o mala
 * (accidentes, stock bajo, vencimientos). Un conteo neutro no lleva tono.
 */
export function StatCard({ label, value, icon: Icono, tone, hint, loading }: Props) {
  const color = tone ? SEMANTIC_COLOR[tone] : undefined

  return (
    <Paper withBorder radius="lg" p="lg" h="100%">
      <Group justify="space-between" align="flex-start" wrap="nowrap" gap="sm">
        <Stack gap={2} style={{ minWidth: 0 }}>
          <Text size="xs" c="dimmed" fw={600} tt="uppercase" style={{ letterSpacing: '0.04em' }}>
            {label}
          </Text>

          {loading ? (
            <Skeleton height={30} width={72} mt={4} />
          ) : (
            <Text fz={28} fw={700} lh={1.15}>
              {value}
            </Text>
          )}

          {hint && !loading && (
            <Text size="xs" c="dimmed">
              {hint}
            </Text>
          )}
        </Stack>

        <ThemeIcon
          size={40}
          radius="md"
          variant="light"
          color={color}
          style={color ? undefined : { background: 'var(--sgth-accent-light)', color: 'var(--sgth-accent-text)' }}
        >
          <Icono size={20} stroke={1.7} />
        </ThemeIcon>
      </Group>
    </Paper>
  )
}
