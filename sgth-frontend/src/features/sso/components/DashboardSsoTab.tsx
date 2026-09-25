'use client'

import { useState } from 'react'
import {
  Box, Group, TextInput, Button, SimpleGrid,
  Text, Alert, Stack,
} from '@mantine/core'
import {
  IconSearch, IconAlertCircle, IconShieldCheck, IconAlertTriangle, IconStethoscope,
  IconBed, IconGauge, IconHelmet, IconClipboardCheck, IconChecklist, IconVaccine,
  IconMoodSmile, IconCalendarOff, IconUsers,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, StatCard } from '@/components/ui'
import { useDashboardSso } from '../hooks/useDashboardSso'

export function DashboardSsoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)

  const params = periodo ? { periodo } : null
  const { data: resumen, isLoading, error, refetch } = useDashboardSso(params)

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
              onClick={handleConsultar}
              disabled={!/^\d{4}(-\d{2})?$/.test(periodoInput)}
            >
              Consultar
            </Button>
          </Group>
        </Group>

        {!periodo && (
          <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
            Ingrese un período y presione Consultar para ver el resumen de indicadores de todas las fases del módulo SSO.
          </Alert>
        )}

        {periodo && (
          <DataState
            loading={isLoading}
            error={error}
            errorTitle="No se pudo calcular el resumen del período"
            errorHint="No quiere decir que el período no tenga actividad registrada: no se pudo consultar."
            onRetry={() => refetch()}
            skeletonRows={4}
          >
            {resumen && (
              <Stack gap="lg">
                <Box>
                  <Text fw={600} mb="xs">Riesgos y accidentes</Text>
                  <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <StatCard label="Riesgos activos" value={resumen.riesgos.total_activos} icon={IconShieldCheck} />
                    <StatCard label="Accidentes en el período" value={resumen.accidentes.total} icon={IconAlertTriangle} tone="danger" />
                    <StatCard label="Con atención médica" value={resumen.accidentes.con_atencion_medica} icon={IconStethoscope} tone="warning" />
                    <StatCard label="Días de reposo" value={resumen.accidentes.dias_reposo_total} icon={IconBed} tone="warning" />
                  </SimpleGrid>
                </Box>

                <Box>
                  <Text fw={600} mb="xs">Índices CD 513 y EPP</Text>
                  <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <StatCard
                      label="Índice de frecuencia"
                      value={resumen.indicadores_reactivos.sin_datos ? '—' : resumen.indicadores_reactivos.indice_frecuencia ?? '—'}
                      icon={IconGauge}
                      hint={resumen.indicadores_reactivos.sin_datos ? 'Faltan las horas del período' : undefined}
                    />
                    <StatCard
                      label="Índice de gravedad"
                      value={resumen.indicadores_reactivos.sin_datos ? '—' : resumen.indicadores_reactivos.indice_gravedad ?? '—'}
                      icon={IconGauge}
                      hint={resumen.indicadores_reactivos.sin_datos ? 'Faltan las horas del período' : undefined}
                    />
                    <StatCard label="Equipos EPP activos" value={resumen.epp.equipos_activos} icon={IconHelmet} />
                    <StatCard
                      label="Cobertura EPP"
                      value={resumen.indicadores_proactivos.cobertura_epp.porcentaje !== null ? `${resumen.indicadores_proactivos.cobertura_epp.porcentaje}%` : '—'}
                      icon={IconHelmet}
                      hint={`${resumen.indicadores_proactivos.cobertura_epp.puestos_con_entrega_en_periodo} de ${resumen.indicadores_proactivos.cobertura_epp.total_puestos_con_epp_requerido} puestos`}
                    />
                  </SimpleGrid>
                </Box>

                <Box>
                  <Text fw={600} mb="xs">Cumplimiento y programa de drogas</Text>
                  <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <StatCard label="Normativa cumple" value={resumen.cumplimiento.cumple} icon={IconClipboardCheck} tone="success" hint={`sobre ${resumen.cumplimiento.total}`} />
                    <StatCard label="Normativa no cumple" value={resumen.cumplimiento.no_cumple} icon={IconClipboardCheck} tone="danger" />
                    <StatCard label="Actividades ejecutadas" value={resumen.programa_drogas.ejecutada} icon={IconChecklist} tone="success" hint={`sobre ${resumen.programa_drogas.total}`} />
                    <StatCard label="Actividades pendientes" value={resumen.programa_drogas.pendiente} icon={IconChecklist} tone="warning" />
                  </SimpleGrid>
                </Box>

                <Box>
                  <Text fw={600} mb="xs">Tamizajes y ausentismo</Text>
                  <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <StatCard label="ASSIST — riesgo alto" value={resumen.assist.riesgo_alto} icon={IconVaccine} tone="danger" hint={`sobre ${resumen.assist.total_respuestas} respuestas`} />
                    <StatCard label="Psicosocial — riesgo alto" value={resumen.psicosocial.riesgo_alto} icon={IconMoodSmile} tone="danger" hint={`sobre ${resumen.psicosocial.total_respuestas} respuestas`} />
                    <StatCard label="Servidores con permiso por enfermedad" value={resumen.ausentismo.servidores_afectados} icon={IconUsers} />
                    <StatCard label="Días de ausentismo" value={resumen.ausentismo.total_dias} icon={IconCalendarOff} />
                  </SimpleGrid>
                </Box>
              </Stack>
            )}
          </DataState>
        )}
      </Box>
    </Stack>
  )
}
