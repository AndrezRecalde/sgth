'use client'

import { useState } from 'react'
import {
  Box, Group, TextInput, Button, SimpleGrid, Card,
  Text, Alert, Skeleton, Badge, Stack,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconSearch, IconClock, IconAlertCircle, IconGauge, IconShieldCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useIndicadoresReactivos, useIndicadoresProactivos } from '../hooks/useIndicadoresSso'
import { GestionarHorasTrabajadasModal } from './GestionarHorasTrabajadasModal'

function StatCard({ label, value, color = 'emerald' }: { label: string; value: string | number; color?: string }) {
  return (
    <Card withBorder radius="md" padding="md">
      <Text size="xs" c="dimmed" tt="uppercase" fw={600}>{label}</Text>
      <Text size="xl" fw={700} c={color}>{value}</Text>
    </Card>
  )
}

export function IndicadoresSsoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [horasOpened, { open: openHoras, close: closeHoras }] = useDisclosure(false)

  const params = periodo ? { periodo } : null
  const { data: reactivos, isLoading: cargandoReactivos } = useIndicadoresReactivos(params)
  const { data: proactivos, isLoading: cargandoProactivos } = useIndicadoresProactivos(params)

  const handleConsultar = () => {
    if (/^\d{4}(-\d{2})?$/.test(periodoInput)) {
      setPeriodo(periodoInput)
    }
  }

  return (
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
        <Button leftSection={<IconClock size={16} />} variant="default" onClick={openHoras}>
          Horas trabajadas
        </Button>
      </Group>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="blue" variant="light">
          Ingrese un período y presione Consultar para ver los índices reactivos (CD 513) y proactivos.
        </Alert>
      )}

      {periodo && (
        <Stack gap="xl">
          <Box>
            <Group gap="xs" mb="sm">
              <IconGauge size={18} />
              <Text fw={600}>Índices reactivos — CD 513</Text>
            </Group>

            {cargandoReactivos && <Skeleton height={100} radius="md" />}

            {!cargandoReactivos && reactivos?.sin_datos && (
              <Alert icon={<IconAlertCircle size={18} />} color="yellow" variant="light">
                {reactivos.mensaje}
              </Alert>
            )}

            {!cargandoReactivos && reactivos && !reactivos.sin_datos && (
              <>
                <SimpleGrid cols={{ base: 2, sm: 3 }} spacing="md">
                  <StatCard label="Índice de frecuencia (IF)" value={reactivos.indice_frecuencia ?? '—'} />
                  <StatCard label="Índice de gravedad (IG)" value={reactivos.indice_gravedad ?? '—'} color="orange" />
                  <StatCard label="Tasa de riesgo (TR)" value={reactivos.tasa_riesgo ?? '—'} color="red" />
                  <StatCard label="Lesiones (accidentes)" value={reactivos.numero_lesiones} color="gray" />
                  <StatCard label="Días perdidos" value={reactivos.dias_perdidos} color="gray" />
                  <StatCard label="Horas trabajadas" value={reactivos.horas_trabajadas.toLocaleString()} color="gray" />
                </SimpleGrid>
                <Text size="xs" c="dimmed" mt="xs">
                  Fórmulas CD 513 (IESS): IF = (lesiones × 200000) / horas; IG = (días perdidos × 200000) / horas;
                  TR = IG / IF. Pendientes de confirmación legal directa por Talento Humano contra el reglamento oficial.
                </Text>
              </>
            )}
          </Box>

          <Box>
            <Group gap="xs" mb="sm">
              <IconShieldCheck size={18} />
              <Text fw={600}>Índices proactivos</Text>
            </Group>

            {cargandoProactivos && <Skeleton height={100} radius="md" />}

            {!cargandoProactivos && proactivos && (
              <>
                <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                  <StatCard label="Inspecciones realizadas" value={proactivos.inspecciones_realizadas} />
                  <StatCard label="Capacitaciones realizadas" value={proactivos.capacitaciones_realizadas} />
                  <StatCard label="Horas de capacitación" value={proactivos.horas_capacitacion_total} />
                  <StatCard
                    label="Cobertura EPP"
                    value={proactivos.cobertura_epp.porcentaje !== null ? `${proactivos.cobertura_epp.porcentaje}%` : '—'}
                    color="blue"
                  />
                </SimpleGrid>
                {proactivos.cobertura_epp.total_puestos_con_epp_requerido > 0 && (
                  <Group mt="xs" gap="xs">
                    <Badge variant="light" color="blue" size="sm">
                      {proactivos.cobertura_epp.puestos_con_entrega_en_periodo} de {proactivos.cobertura_epp.total_puestos_con_epp_requerido} puestos con entrega registrada en el período
                    </Badge>
                  </Group>
                )}
                <Text size="xs" c="dimmed" mt="xs">
                  El sistema no distingue actividades planificadas de realizadas; se reportan los conteos reales
                  registrados en el período.
                </Text>
              </>
            )}
          </Box>
        </Stack>
      )}

      <GestionarHorasTrabajadasModal opened={horasOpened} onClose={closeHoras} />
    </Box>
  )
}
