'use client'

import { useState } from 'react'
import {
  Box, Group, TextInput, Button, SimpleGrid,
  Text, Alert, Stack,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import {
  IconSearch, IconClock, IconAlertCircle, IconGauge, IconShieldCheck,
  IconAlertTriangle, IconBed, IconClipboardList, IconSchool, IconHelmet,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useIndicadoresReactivos, useIndicadoresProactivos } from '../hooks/useIndicadoresSso'
import { AYUDA_PERIODO, EJEMPLO_PERIODO, esPeriodoValido } from '../constants/periodo'
import { GestionarHorasTrabajadasModal } from './GestionarHorasTrabajadasModal'
import { DataState, StatCard } from '@/components/ui'

export function IndicadoresSsoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [horasOpened, { open: openHoras, close: closeHoras }] = useDisclosure(false)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const params = periodo ? { periodo } : null
  const {
    data: reactivos, isLoading: cargandoReactivos,
    error: errorReactivos, refetch: recargarReactivos,
  } = useIndicadoresReactivos(params)
  const {
    data: proactivos, isLoading: cargandoProactivos,
    error: errorProactivos, refetch: recargarProactivos,
  } = useIndicadoresProactivos(params)

  const handleConsultar = () => {
    if (esPeriodoValido(periodoInput)) {
      setPeriodo(periodoInput)
    }
  }

  return (
    <Box>
      <Group justify="space-between" mb="md" align="flex-end">
        <Group align="flex-end">
          <TextInput
            label="Período"
            placeholder={EJEMPLO_PERIODO}
            description={AYUDA_PERIODO}
            {...contained}
            value={periodoInput}
            onChange={(e) => setPeriodoInput(e.currentTarget.value)}
          />
          <Button
            leftSection={<IconSearch size={16} />}
            onClick={handleConsultar}
            disabled={!esPeriodoValido(periodoInput)}
          >
            Consultar
          </Button>
        </Group>
        {puedeGestionar && (
          <Button leftSection={<IconClock size={16} />} variant="default" onClick={openHoras}>
            Horas trabajadas
          </Button>
        )}
      </Group>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
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

            <DataState
              loading={cargandoReactivos}
              error={errorReactivos}
              errorTitle="No se pudieron calcular los índices reactivos"
              errorHint="No quiere decir que el período no tenga accidentes: no se pudo consultar."
              onRetry={() => recargarReactivos()}
              skeletonRows={2}
              // El backend marca `sin_datos` cuando falta el denominador: es el
              // estado vacío de esta pantalla, y su mensaje ya dice qué cargar.
              empty={!!reactivos?.sin_datos}
              emptyProps={{
                icon: IconClock,
                title: 'Faltan las horas trabajadas del período',
                description: reactivos?.mensaje,
                action: puedeGestionar ? (
                  <Button variant="light" leftSection={<IconClock size={16} />} onClick={openHoras}>
                    Cargar horas trabajadas
                  </Button>
                ) : undefined,
              }}
            >
              {reactivos && !reactivos.sin_datos && (
                <>
                  <SimpleGrid cols={{ base: 2, sm: 3 }} spacing="md">
                    {/* Los tres índices no llevan tono: su lectura buena o mala
                        depende de la serie histórica del GAD, que el sistema
                        todavía no guarda. Un rojo fijo diría que 15,71 es malo
                        sin nada contra qué compararlo. */}
                    <StatCard label="Índice de frecuencia (IF)" value={reactivos.indice_frecuencia ?? '—'} icon={IconGauge} />
                    <StatCard label="Índice de gravedad (IG)" value={reactivos.indice_gravedad ?? '—'} icon={IconGauge} />
                    <StatCard label="Tasa de riesgo (TR)" value={reactivos.tasa_riesgo ?? '—'} icon={IconGauge} hint="días perdidos por lesión" />
                    <StatCard label="Lesiones (accidentes)" value={reactivos.numero_lesiones} icon={IconAlertTriangle} />
                    <StatCard label="Días perdidos" value={reactivos.dias_perdidos} icon={IconBed} />
                    <StatCard label="Horas trabajadas" value={reactivos.horas_trabajadas.toLocaleString()} icon={IconClock} />
                  </SimpleGrid>

                  {/* El denominador puede venir del período exacto o de la suma
                      de sus meses, y del total institucional o de la suma de
                      unidades: sin decirlo, el índice no se puede auditar. */}
                  {reactivos.horas_trabajadas_detalle && (
                    <Text size="xs" c="dimmed" mt="xs">
                      {reactivos.horas_trabajadas_detalle}
                    </Text>
                  )}

                  <Text size="xs" c="dimmed" mt={4}>
                    Fórmulas CD 513 (IESS): IF = (lesiones × 200000) / horas; IG = (días perdidos × 200000) / horas;
                    TR = IG / IF. Pendientes de confirmación legal directa por Talento Humano contra el reglamento oficial.
                  </Text>
                </>
              )}
            </DataState>
          </Box>

          <Box>
            <Group gap="xs" mb="sm">
              <IconShieldCheck size={18} />
              <Text fw={600}>Índices proactivos</Text>
            </Group>

            <DataState
              loading={cargandoProactivos}
              error={errorProactivos}
              errorTitle="No se pudieron calcular los índices proactivos"
              errorHint="No quiere decir que no haya inspecciones ni capacitaciones en el período: no se pudo consultar."
              onRetry={() => recargarProactivos()}
              skeletonRows={2}
            >
              {proactivos && (
                <>
                  <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <StatCard label="Inspecciones realizadas" value={proactivos.inspecciones_realizadas} icon={IconClipboardList} />
                    <StatCard label="Capacitaciones realizadas" value={proactivos.capacitaciones_realizadas} icon={IconSchool} />
                    <StatCard label="Horas de capacitación" value={proactivos.horas_capacitacion_total} icon={IconClock} />
                    <StatCard
                      label="Cobertura EPP"
                      value={proactivos.cobertura_epp.porcentaje !== null ? `${proactivos.cobertura_epp.porcentaje}%` : '—'}
                      icon={IconHelmet}
                      hint={proactivos.cobertura_epp.total_puestos_con_epp_requerido > 0
                        ? `${proactivos.cobertura_epp.puestos_con_entrega_en_periodo} de ${proactivos.cobertura_epp.total_puestos_con_epp_requerido} puestos`
                        : undefined}
                    />
                  </SimpleGrid>
                  <Text size="xs" c="dimmed" mt="xs">
                    El sistema no distingue actividades planificadas de realizadas; se reportan los conteos reales
                    registrados en el período.
                  </Text>
                </>
              )}
            </DataState>
          </Box>
        </Stack>
      )}

      <GestionarHorasTrabajadasModal opened={horasOpened} onClose={closeHoras} />
    </Box>
  )
}
