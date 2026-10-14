import { Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { SectionCard, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { TableroEquipos } from '../services/tableroService'

interface Props {
  contratos: TableroEquipos['contratos_por_vencer']
  ventanas: TableroEquipos['ventanas_proximas']
}

/**
 * Los contratos de vigencia tecnológica que terminan en los próximos 90 días
 * —sus equipos se quedan sin el mantenimiento del proveedor— y las ventanas
 * de mantenimiento del cronograma que vienen en el próximo mes.
 */
export function VigenciasProximas({ contratos, ventanas }: Props) {
  return (
    <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
      <SectionCard title="Contratos de vigencia por vencer" description="Terminan en los próximos 90 días.">
        {contratos.length === 0 ? (
          <Text size="sm" c="dimmed">Ningún contrato termina en los próximos 90 días.</Text>
        ) : (
          <Stack gap="sm">
            {contratos.map((c) => (
              <Group key={c.id} justify="space-between" wrap="nowrap" gap="xs">
                <Stack gap={0} style={{ minWidth: 0 }}>
                  <Text size="sm" fw={600} lineClamp={1}>{c.proceso}</Text>
                  <Text size="xs" c="dimmed" lineClamp={1}>
                    {c.proveedor ?? 'Sin proveedor'} · {c.equipos} {c.equipos === 1 ? 'equipo' : 'equipos'} · hasta el {formatFecha(c.fecha_fin)}
                  </Text>
                </Stack>
                <StatusBadge tone={c.dias <= 30 ? 'danger' : 'warning'}>{c.dias === 0 ? 'Hoy' : `En ${c.dias} días`}</StatusBadge>
              </Group>
            ))}
          </Stack>
        )}
      </SectionCard>

      <SectionCard title="Mantenimientos del proveedor" description="Ventanas del cronograma en curso o en los próximos 30 días.">
        {ventanas.length === 0 ? (
          <Text size="sm" c="dimmed">No hay ventanas de mantenimiento en los próximos 30 días.</Text>
        ) : (
          <Stack gap="sm">
            {ventanas.map((v) => (
              <Stack key={v.id} gap={0}>
                <Text size="sm" fw={600} lineClamp={1}>{v.proceso ?? '—'}{v.proveedor ? ` · ${v.proveedor}` : ''}</Text>
                <Text size="xs" c="dimmed" lineClamp={1}>
                  Del {formatFecha(v.desde)} al {formatFecha(v.hasta)}{v.descripcion ? ` · ${v.descripcion}` : ''}
                </Text>
              </Stack>
            ))}
          </Stack>
        )}
      </SectionCard>
    </SimpleGrid>
  )
}
