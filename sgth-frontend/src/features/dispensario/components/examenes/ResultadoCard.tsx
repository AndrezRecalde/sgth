'use client'

import { ActionIcon, Card, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core'
import { IconClipboardCheck, IconExternalLink, IconTrash } from '@tabler/icons-react'
import { confirmar, StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { getIcon } from '@/lib/tablerIcons'
import { useAuthStore } from '@/store/auth.store'
import { useEliminarResultado, useVerResultado } from '../../hooks/useResultadoMedico'
import {
  INTERPRETACION, TIPO_RESULTADO_ICONS, TIPO_RESULTADO_OPTIONS, type PersonaResultado, type ResultadoMedico,
} from '../../services/resultadoMedicoService'

interface Props {
  resultado: ResultadoMedico
  /** De qué consulta es, cuando se listan todos los del paciente. */
  origen?:   string
  onRevisar: (r: ResultadoMedico) => void
}

const nombre = (p?: PersonaResultado | null) =>
  p?.servidor ? `${p.servidor.nombre} ${p.servidor.apellido}` : null

/** Un resultado: qué es, de qué orden vino, quién lo subió y la lectura del médico. */
export function ResultadoCard({ resultado: r, origen, onRevisar }: Props) {
  const eliminar = useEliminarResultado()
  const ver = useVerResultado()
  const { usuario, hasRole } = useAuthStore()

  // Lo retira quien lo subió o la administración, igual que exige el
  // servidor: un botón que siempre responde 403 es peor que no enseñarlo.
  const puedeRetirar = r.subido_por?.id === usuario?.id || hasRole('admin-dispensario')
  // Lo revisa cualquier médico; una lectura ya hecha solo la corrige quien la
  // hizo (D3). Lo subido antes de la revisión queda como estaba.
  const puedeRevisar = (hasRole('medico') || hasRole('odontologo'))
    && (r.estado_revision === 'por_revisar' || r.revisor?.id === usuario?.id)
  const lectura = r.interpretacion ? INTERPRETACION[r.interpretacion] : null

  return (
    <Card withBorder radius="md" p="sm">
      <Group justify="space-between" wrap="nowrap" align="flex-start">
        <Group gap="sm" wrap="nowrap" align="flex-start">
          <ThemeIcon size="md" variant="light" radius="xl">
            {getIcon(TIPO_RESULTADO_ICONS[r.tipo] ?? 'IconFileText', 16)}
          </ThemeIcon>
          <Stack gap={0}>
            <Group gap="xs">
              <StatusBadge size="xs">{TIPO_RESULTADO_OPTIONS.find((o) => o.value === r.tipo)?.label ?? r.tipo}</StatusBadge>
              {r.estado_revision === 'por_revisar' && <StatusBadge size="xs" tone="warning" variant="dot">Por revisar</StatusBadge>}
              {lectura && <StatusBadge size="xs" tone={lectura.tone}>{lectura.label}</StatusBadge>}
              <Text size="xs" c="dimmed">{formatFechaMes(r.fecha_resultado)}</Text>
            </Group>
            <Text size="sm">{r.descripcion}</Text>
            {r.orden_examen && (
              <Text size="xs" c="dimmed">
                Orden <Text span ff="monospace" inherit>{r.orden_examen.folio}</Text>
                {r.items_orden?.length
                  ? ` · ${r.items_orden.map((i) => i.examen?.nombre).filter(Boolean).join(', ')}`
                  : ''}
              </Text>
            )}
            {origen && <Text size="xs" c="dimmed">{origen}</Text>}
            {r.envio_portal
              ? <Text size="xs" c="dimmed">Lo envió el paciente desde el Portal{nombre(r.subido_por) ? `; lo validó Dr. ${nombre(r.subido_por)}` : ''}</Text>
              : nombre(r.subido_por) && <Text size="xs" c="dimmed">Subió: Dr. {nombre(r.subido_por)}</Text>}
            {r.comentario_medico && (
              <Text size="xs" mt={4}>
                <Text span fw={500} inherit>Lectura{nombre(r.revisor) ? ` de Dr. ${nombre(r.revisor)}` : ''}:</Text>{' '}
                {r.comentario_medico}
              </Text>
            )}
          </Stack>
        </Group>

        <Group gap="xs" wrap="nowrap">
          {puedeRevisar && (
            <Tooltip label={r.estado_revision === 'por_revisar' ? 'Revisar' : 'Corregir mi lectura'} withArrow>
              <ActionIcon size="sm" variant="light" aria-label={`Revisar ${r.descripcion}`} onClick={() => onRevisar(r)}>
                <IconClipboardCheck size={13} />
              </ActionIcon>
            </Tooltip>
          )}
          <Tooltip label="Ver archivo" withArrow>
            <ActionIcon size="sm" variant="light" aria-label={`Ver el archivo de ${r.descripcion}`}
              loading={ver.isPending && ver.variables?.id === r.id} onClick={() => ver.mutate(r)}>
              <IconExternalLink size={13} />
            </ActionIcon>
          </Tooltip>
          {puedeRetirar && (
            <Tooltip label="Retirar" withArrow>
              <ActionIcon size="sm" variant="light" color="red" aria-label={`Retirar ${r.descripcion}`}
                onClick={() => confirmar({
                  title:   'Retirar resultado',
                  message: 'Dejará de aparecer en la consulta. El archivo se conserva en el registro del Dispensario.',
                  confirmLabel: 'Retirar',
                  destructiva: true,
                  onConfirm: () => eliminar.mutate(r.id),
                })}>
                <IconTrash size={13} />
              </ActionIcon>
            </Tooltip>
          )}
        </Group>
      </Group>
    </Card>
  )
}
