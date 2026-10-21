import { Group, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  consulta: Pick<ConsultaMedica, 'diagnostico_cie10_principal' | 'diagnosticos_secundarios'>
  /** En la tarjeta del historial: solo el principal y cuántos secundarios hay. */
  compacto?: boolean
}

/**
 * Los diagnósticos codificados de una consulta: el principal y los secundarios.
 *
 * El historial y su detalle no los enseñaban, aunque el servidor los manda en
 * las dos respuestas: al repasar las consultas anteriores de un paciente, lo
 * que más se busca —qué se le diagnosticó— no aparecía.
 */
export function DiagnosticosCie10({ consulta, compacto = false }: Props) {
  const principal = consulta.diagnostico_cie10_principal
  const secundarios = (consulta.diagnosticos_secundarios ?? [])
    .flatMap((ds) => (ds.diagnostico ? [ds.diagnostico] : []))

  if (!principal && secundarios.length === 0) return null

  if (compacto) {
    return (
      <Group gap={6} wrap="nowrap">
        {principal && <StatusBadge size="xs">{principal.codigo}</StatusBadge>}
        <Text size="xs" lineClamp={1}>
          {principal?.descripcion ?? 'Sin diagnóstico principal'}
          {secundarios.length > 0 && (
            <Text span c="dimmed">
              {' '}· {secundarios.length} secundario{secundarios.length !== 1 ? 's' : ''}
            </Text>
          )}
        </Text>
      </Group>
    )
  }

  return (
    <Stack gap={4}>
      {principal && (
        <Group gap="xs" wrap="nowrap" align="flex-start">
          <StatusBadge size="sm">{principal.codigo}</StatusBadge>
          <Text size="sm" fw={500}>{principal.descripcion}</Text>
        </Group>
      )}
      {secundarios.map((d) => (
        <Group key={d.id} gap="xs" wrap="nowrap" align="flex-start">
          <StatusBadge size="xs">{d.codigo}</StatusBadge>
          <Text size="xs" c="dimmed">{d.descripcion}</Text>
        </Group>
      ))}
    </Stack>
  )
}
