'use client'

import Link from 'next/link'
import { Alert, Anchor, Button, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { formatFechaMes } from '@/lib/fecha'
import { useAuthStore } from '@/store/auth.store'
import { useEnteradoCritico } from '../../hooks/useResultadoMedico'
import type { CriticoJornada } from '../../services/miJornadaService'

interface Props {
  porRevisar: number
  criticos:   CriticoJornada[]
}

/**
 * Los exámenes en «Mi jornada» (D3): los críticos que revisó otro médico sobre
 * lo que pidió este quedan fijos hasta que los vea, y un enlace a lo suyo que
 * nadie ha leído todavía.
 */
export function ExamenesEnJornada({ porRevisar, criticos }: Props) {
  const { usuario } = useAuthStore()
  const visto = useEnteradoCritico()

  if (!criticos.length && !porRevisar) return null

  return (
    <Stack gap="xs">
      {criticos.map((c) => (
        <Alert key={c.id} color={SEMANTIC_COLOR.danger} variant="light" icon={<IconAlertTriangle size={16} />}
          title={`Resultado crítico: ${c.descripcion}${c.paciente ? ` · ${c.paciente}` : ''}`}>
          <Group justify="space-between" wrap="nowrap" align="flex-end">
            <Text size="xs">
              Lo revisó {c.revisor ?? 'otro médico'}{c.revisado_en ? ` el ${formatFechaMes(c.revisado_en)}` : ''}
              {c.comentario ? `: ${c.comentario}` : '.'}
            </Text>
            <Button size="compact-xs" variant="light" color={SEMANTIC_COLOR.danger}
              loading={visto.isPending && visto.variables === c.id} onClick={() => visto.mutate(c.id)}>
              Visto
            </Button>
          </Group>
        </Alert>
      ))}
      {porRevisar > 0 && (
        <Group gap="xs">
          <Text size="xs" c="dimmed">
            Exámenes: {porRevisar} {porRevisar === 1 ? 'resultado suyo por revisar' : 'resultados suyos por revisar'}.
          </Text>
          <Anchor size="xs" component={Link} href={ROUTES.SALUD.EXAMENES_POR_REVISAR(usuario?.id)}>Revisar</Anchor>
        </Group>
      )}
    </Stack>
  )
}
