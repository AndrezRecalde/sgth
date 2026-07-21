'use client'

import { useState } from 'react'
import {
  Box, Group, TextInput, Button, SimpleGrid, Card,
  Text, Alert, Skeleton, Stack,
} from '@mantine/core'
import { IconSearch, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDashboardSso } from '../hooks/useDashboardSso'

function StatCard({ label, value, color = 'emerald' }: { label: string; value: string | number; color?: string }) {
  return (
    <Card withBorder radius="md" padding="md">
      <Text size="xs" c="dimmed" tt="uppercase" fw={600}>{label}</Text>
      <Text size="xl" fw={700} c={color}>{value}</Text>
    </Card>
  )
}

export function DashboardSsoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)

  const params = periodo ? { periodo } : null
  const { data: resumen, isLoading } = useDashboardSso(params)

  const handleConsultar = () => {
    if (/^\d{4}(-\d{2})?$/.test(periodoInput)) {
      setPeriodo(periodoInput)
    }
  }

  return (
    <Stack gap="xl">
      <Box>
        <Group justify="space-between" mb="md" align="flex-end">
          <Group align="flex-end">
            <TextInput
              label="Período"
              placeholder="2026 o 2026-07"
              description="Formato AAAA (año) o AAAA-MM (mes)"
              {...contained}
              value={periodoInput}
              onChange={(e) => setPeriodoInput(e.currentTarget.value)}
            />
            <Button
              leftSection={<IconSearch size={16} />}
              color="emerald"
              onClick={handleConsultar}
              disabled={!/^\d{4}(-\d{2})?$/.test(periodoInput)}
            >
              Consultar
            </Button>
          </Group>
        </Group>

        {!periodo && (
          <Alert icon={<IconAlertCircle size={18} />} color="blue" variant="light">
            Ingrese un período y presione Consultar para ver el resumen de indicadores de todas las fases del módulo SSO.
          </Alert>
        )}

        {isLoading && <Skeleton height={200} radius="md" />}

        {!isLoading && resumen && (
          <Stack gap="lg">
            <Box>
              <Text fw={600} mb="xs">Riesgos y accidentes</Text>
              <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                <StatCard label="Riesgos activos" value={resumen.riesgos.total_activos} />
                <StatCard label="Accidentes en el período" value={resumen.accidentes.total} color="red" />
                <StatCard label="Con atención médica" value={resumen.accidentes.con_atencion_medica} color="orange" />
                <StatCard label="Días de reposo" value={resumen.accidentes.dias_reposo_total} color="orange" />
              </SimpleGrid>
            </Box>

            <Box>
              <Text fw={600} mb="xs">Índices CD 513 y EPP</Text>
              <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                <StatCard
                  label="Índice de frecuencia"
                  value={resumen.indicadores_reactivos.sin_datos ? '—' : resumen.indicadores_reactivos.indice_frecuencia ?? '—'}
                />
                <StatCard
                  label="Índice de gravedad"
                  value={resumen.indicadores_reactivos.sin_datos ? '—' : resumen.indicadores_reactivos.indice_gravedad ?? '—'}
                  color="orange"
                />
                <StatCard label="Equipos EPP activos" value={resumen.epp.equipos_activos} color="blue" />
                <StatCard
                  label="Cobertura EPP"
                  value={resumen.indicadores_proactivos.cobertura_epp.porcentaje !== null ? `${resumen.indicadores_proactivos.cobertura_epp.porcentaje}%` : '—'}
                  color="blue"
                />
              </SimpleGrid>
            </Box>

            <Box>
              <Text fw={600} mb="xs">Cumplimiento y programa de drogas</Text>
              <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                <StatCard label="Normativa cumple" value={resumen.cumplimiento.cumple} color="emerald" />
                <StatCard label="Normativa no cumple" value={resumen.cumplimiento.no_cumple} color="red" />
                <StatCard label="Actividades ejecutadas" value={resumen.programa_drogas.ejecutada} color="emerald" />
                <StatCard label="Actividades pendientes" value={resumen.programa_drogas.pendiente} color="gray" />
              </SimpleGrid>
            </Box>

            <Box>
              <Text fw={600} mb="xs">Tamizajes y ausentismo</Text>
              <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                <StatCard label="ASSIST — riesgo alto" value={resumen.assist.riesgo_alto} color="red" />
                <StatCard label="Psicosocial — riesgo alto" value={resumen.psicosocial.riesgo_alto} color="red" />
                <StatCard label="Servidores con permiso por enfermedad" value={resumen.ausentismo.servidores_afectados} />
                <StatCard label="Días de ausentismo" value={resumen.ausentismo.total_dias} />
              </SimpleGrid>
            </Box>
          </Stack>
        )}
      </Box>
    </Stack>
  )
}
