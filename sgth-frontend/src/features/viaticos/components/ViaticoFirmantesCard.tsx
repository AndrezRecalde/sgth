'use client'

import { Alert, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { SectionCard, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ViaticoFirmante } from '@/types/api'

interface Props {
  firmantes: ViaticoFirmante[]
}

/*
| Quién firma cada documento del viático, tal como quedó sellado al emitirlo.
|
| Antes las firmas se resolvían al imprimir, así que un informe reimpreso meses
| después salía con quien ocupara el cargo ese día. Aquí se ve quién firmó de
| verdad cada papel, y el aviso cuando el titular estaba de vacaciones sin
| subrogación registrada.
*/

const DOCUMENTOS: Record<string, string> = {
  solicitud:   'Solicitud',
  informe:     'Informe de liquidación',
  comprobante: 'Comprobante contable',
}

const ROLES: Record<string, string> = {
  maxima_autoridad:    'Máxima autoridad',
  jefe_unidad:         'Jefatura de la unidad',
  director_financiero: 'Dirección financiera',
}

export function ViaticoFirmantesCard({ firmantes }: Props) {
  if (firmantes.length === 0) return null

  const porDocumento = Object.keys(DOCUMENTOS)
    .map((documento) => ({
      documento,
      firmas: firmantes.filter((f) => f.documento === documento),
    }))
    .filter((grupo) => grupo.firmas.length > 0)

  return (
    <SectionCard
      title="Firmas"
      description="Quién firma cada documento, tal como quedó al emitirlo"
    >
      <Stack gap="md">
        {porDocumento.map(({ documento, firmas }) => (
          <Stack key={documento} gap={6}>
            <Group gap="xs">
              <Text size="sm" fw={600}>
                {DOCUMENTOS[documento]}
              </Text>
              <Text size="xs" c="dimmed">
                sellado el {formatFecha(firmas[0].sellado_en)}
              </Text>
            </Group>

            {firmas.map((firma) => (
              <Stack key={firma.id} gap={2}>
                <Group justify="space-between" gap="sm" wrap="nowrap">
                  <Text size="xs" c="dimmed">
                    {ROLES[firma.rol] ?? firma.rol}
                  </Text>
                  <Group gap={6} wrap="nowrap">
                    <Text size="xs" fw={600} ta="right">
                      {firma.nombre ?? 'Puesto vacante'}
                    </Text>
                    {firma.subrogado && <StatusBadge tone="info">Subrogado</StatusBadge>}
                  </Group>
                </Group>
                <Text size="xs" c="dimmed" ta="right">
                  {firma.cargo}
                </Text>
                {firma.aviso && (
                  <Alert
                    icon={<IconAlertTriangle size={14} />}
                    color={SEMANTIC_COLOR.warning}
                    variant="light"
                    p="xs"
                  >
                    <Text size="xs">{firma.aviso}</Text>
                  </Alert>
                )}
              </Stack>
            ))}
          </Stack>
        ))}
      </Stack>
    </SectionCard>
  )
}
