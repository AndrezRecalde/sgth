'use client'

import {
  SimpleGrid, Stack, Group, Text, Badge, Table, Progress, Alert,
} from '@mantine/core'
import {
  IconStethoscope, IconDental, IconUsers, IconAlertTriangle,
  IconClockExclamation, IconPill, IconChartBar,
} from '@tabler/icons-react'
import {
  DataState, SectionCard, StatCard,
} from '@/components/ui'
import { useKpisDispensario } from '../hooks/useKpis'
import { useAuthStore } from '@/store/auth.store'
import { ETIQUETA_ESPECIALIDAD } from '../services/kpisService'
import type { Especialidad } from '../services/kpisService'

function InsigniaEspecialidad({ valor }: { valor: Especialidad }) {
  const esOdonto = valor === 'odontologia'

  return (
    <Badge size="xs" variant="light" color={esOdonto ? 'teal' : 'blue'}>
      {ETIQUETA_ESPECIALIDAD[valor]}
    </Badge>
  )
}

/**
 * Las cifras del mes del dispensario.
 *
 * El endpoint existía desde hacía tiempo pero ninguna pantalla lo pedía, así
 * que nadie había visto que devolvía un error. Ahora que la consulta registra
 * su especialidad, el reparto entre medicina general y odontología es lo
 * primero que se lee: es la pregunta que el módulo no podía responder.
 */
export function TableroDispensario() {
  // El endpoint es de la administración del dispensario y de la máxima
  // autoridad. A `/salud` llega cualquiera del módulo, así que sin esta
  // comprobación un médico o una enfermera se encontrarían la pantalla de
  // inicio presidida por un error de permisos.
  const hasRole = useAuthStore((s) => s.hasRole)
  const puedeVerlo =
    hasRole('admin-dispensario') || hasRole('maxima-autoridad')

  const { data: kpis, isLoading, error } = useKpisDispensario(puedeVerlo)

  if (!puedeVerlo) return null

  const general = kpis?.atenciones_por_especialidad.medicina_general ?? 0
  const odonto  = kpis?.atenciones_por_especialidad.odontologia ?? 0
  const total   = general + odonto

  const bajoStock  = kpis?.alertas_inventario.medicamentos_bajo_stock ?? []
  const porCaducar = kpis?.alertas_inventario.medicamentos_por_caducar ?? []

  return (
    <DataState loading={isLoading} error={error} skeletonRows={4}>
      <Stack gap="lg">
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }} spacing="md">
          <StatCard
            label="Atenciones del mes"
            value={kpis?.atenciones_mes_actual ?? 0}
            icon={IconChartBar}
          />
          <StatCard
            label="Medicina general"
            value={general}
            icon={IconStethoscope}
            hint={total > 0
              ? `${Math.round((general / total) * 100)}% del mes`
              : 'Sin atenciones este mes'}
          />
          <StatCard
            label="Odontología"
            value={odonto}
            icon={IconDental}
            hint={total > 0
              ? `${Math.round((odonto / total) * 100)}% del mes`
              : 'Sin atenciones este mes'}
          />
          <StatCard
            label="Pacientes atendidos"
            value={kpis?.pacientes_por_tipo.titulares ?? 0}
            icon={IconUsers}
            hint={`${kpis?.pacientes_por_tipo.beneficiarios ?? 0} de carga familiar`}
          />
        </SimpleGrid>

        {total > 0 && (
          <SectionCard
            title="Reparto por especialidad"
            description="Sobre las atenciones registradas este mes."
          >
            <Stack gap="xs">
              <Progress.Root size="xl" radius="md">
                <Progress.Section
                  value={(general / total) * 100}
                  color="blue"
                >
                  {general > 0 && (
                    <Progress.Label>{general}</Progress.Label>
                  )}
                </Progress.Section>
                <Progress.Section
                  value={(odonto / total) * 100}
                  color="teal"
                >
                  {odonto > 0 && <Progress.Label>{odonto}</Progress.Label>}
                </Progress.Section>
              </Progress.Root>

              <Group gap="lg">
                <Group gap={6}>
                  <IconStethoscope size={14} color="var(--mantine-color-blue-6)" />
                  <Text size="xs" c="dimmed">Medicina general</Text>
                </Group>
                <Group gap={6}>
                  <IconDental size={14} color="var(--mantine-color-teal-6)" />
                  <Text size="xs" c="dimmed">Odontología</Text>
                </Group>
              </Group>
            </Stack>
          </SectionCard>
        )}

        <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
          <SectionCard
            title="Consultas por profesional"
            description="Este mes, separadas por especialidad."
          >
            {(kpis?.consultas_por_medico.length ?? 0) === 0 ? (
              <Text size="sm" c="dimmed">
                Todavía no hay consultas registradas este mes.
              </Text>
            ) : (
              <Table striped withRowBorders={false}>
                <Table.Tbody>
                  {kpis?.consultas_por_medico.map((fila, i) => (
                    <Table.Tr key={`${fila.medico}-${fila.especialidad}-${i}`}>
                      <Table.Td>
                        <Text size="sm">{fila.medico}</Text>
                      </Table.Td>
                      <Table.Td>
                        <InsigniaEspecialidad valor={fila.especialidad} />
                      </Table.Td>
                      <Table.Td ta="right">
                        <Text size="sm" fw={600}>{fila.total_consultas}</Text>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            )}
          </SectionCard>

          <SectionCard
            title="Diagnósticos más frecuentes"
            description="Los cinco más registrados del mes, con su CIE-10."
          >
            {(kpis?.top_diagnosticos.length ?? 0) === 0 ? (
              <Text size="sm" c="dimmed">
                Ninguna consulta del mes lleva todavía un código CIE-10.
              </Text>
            ) : (
              <Stack gap="xs">
                {kpis?.top_diagnosticos.map((d) => (
                  <Group key={`${d.codigo}-${d.especialidad}`} gap="xs" wrap="nowrap">
                    <Badge size="sm" variant="outline" ff="monospace">
                      {d.codigo}
                    </Badge>
                    <Text size="xs" style={{ flex: 1 }} lineClamp={1}>
                      {d.descripcion}
                    </Text>
                    <InsigniaEspecialidad valor={d.especialidad} />
                    <Text size="sm" fw={600}>{d.total}</Text>
                  </Group>
                ))}
              </Stack>
            )}
          </SectionCard>
        </SimpleGrid>

        <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
          <SectionCard
            title="Medicamentos más despachados"
            description="Unidades entregadas este mes."
          >
            {(kpis?.medicamentos_mas_despachados.length ?? 0) === 0 ? (
              <Text size="sm" c="dimmed">
                No se ha despachado nada este mes.
              </Text>
            ) : (
              <Stack gap={6}>
                {kpis?.medicamentos_mas_despachados.map((m) => (
                  <Group key={m.nombre} justify="space-between" gap="xs">
                    <Group gap={6} wrap="nowrap" style={{ minWidth: 0 }}>
                      <IconPill size={13} color="var(--mantine-color-gray-6)" />
                      <Text size="xs" lineClamp={1}>{m.nombre}</Text>
                    </Group>
                    <Text size="sm" fw={600}>{m.total_despachado}</Text>
                  </Group>
                ))}
              </Stack>
            )}
          </SectionCard>

          <SectionCard
            title="Avisos de inventario"
            description="Lo que hay que reponer o retirar."
          >
            <Stack gap="sm">
              {bajoStock.length === 0 && porCaducar.length === 0 && (
                <Text size="sm" c="dimmed">
                  Sin avisos: ningún medicamento está bajo mínimo ni por caducar.
                </Text>
              )}

              {bajoStock.length > 0 && (
                <Alert
                  icon={<IconAlertTriangle size={15} />}
                  color="orange"
                  variant="light"
                  p="xs"
                >
                  <Stack gap={4}>
                    <Text size="xs" fw={600}>
                      Bajo mínimo ({bajoStock.length})
                    </Text>
                    {bajoStock.slice(0, 5).map((m) => (
                      <Text key={m.nombre} size="xs">
                        {m.nombre} — quedan {m.stock_actual} de {m.stock_minimo}
                      </Text>
                    ))}
                  </Stack>
                </Alert>
              )}

              {porCaducar.length > 0 && (
                <Alert
                  icon={<IconClockExclamation size={15} />}
                  color="red"
                  variant="light"
                  p="xs"
                >
                  <Stack gap={4}>
                    <Text size="xs" fw={600}>
                      Por caducar en 60 días ({porCaducar.length})
                    </Text>
                    {porCaducar.slice(0, 5).map((l) => (
                      <Text key={`${l.nombre}-${l.lote}`} size="xs">
                        {l.nombre} · lote {l.lote} — {l.stock} u.,{' '}
                        {l.dias_restantes < 0
                          ? `vencido hace ${Math.abs(l.dias_restantes)} días`
                          : `${l.dias_restantes} días`}
                      </Text>
                    ))}
                  </Stack>
                </Alert>
              )}
            </Stack>
          </SectionCard>
        </SimpleGrid>
      </Stack>
    </DataState>
  )
}
