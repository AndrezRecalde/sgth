'use client'

import { confirmar, DataState, StatusBadge } from '@/components/ui'
import {
  Stack, Text, Button, Group, Card, ThemeIcon,
  ActionIcon, Tooltip, SegmentedControl,
} from '@mantine/core'
import { useState } from 'react'
import {
  IconPlus, IconFileText, IconTrash,
  IconExternalLink,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { getIcon } from '@/lib/tablerIcons'
import { SubirResultadoModal } from './SubirResultadoModal'
import {
  useResultadosPorConsulta,
  useResultadosPorPaciente,
  useEliminarResultado,
  useVerResultado,
} from '../hooks/useResultadoMedico'
import {
  TIPO_RESULTADO_OPTIONS,
  TIPO_RESULTADO_ICONS,
} from '../services/resultadoMedicoService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { ResultadoMedico } from '../services/resultadoMedicoService'
import { formatFechaMes } from '@/lib/fecha'
import { useAuthStore } from '@/store/auth.store'

interface Props {
  consulta:          ConsultaMedica
  historiaClinicaId: number
}

function getLabelTipo(tipo: string): string {
  return TIPO_RESULTADO_OPTIONS.find(o => o.value === tipo)?.label ?? tipo
}

type Alcance = 'consulta' | 'paciente'

const ALCANCES = [
  { value: 'consulta', label: 'Esta consulta' },
  { value: 'paciente', label: 'Todo el paciente' },
]

export function TabResultados({
  consulta, historiaClinicaId,
}: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  // Por defecto, los de esta consulta. «Todo el paciente» enseña también los
  // de consultas anteriores y los que no son de ninguna: un laboratorio que
  // llega una semana después ya no obliga a abrir el panel de aquella.
  const [alcance, setAlcance] = useState<Alcance>('consulta')
  const deConsulta = useResultadosPorConsulta(historiaClinicaId, consulta.id)
  const delPaciente = useResultadosPorPaciente(historiaClinicaId, alcance === 'paciente')
  const { data: resultados = [], isLoading, error, refetch } =
    alcance === 'consulta' ? deConsulta : delPaciente

  // De qué consulta es cada uno, cuando se ven todos los del paciente.
  const origen = (r: ResultadoMedico): string => {
    if (r.consulta_medica_id === consulta.id) return 'Esta consulta'
    if (r.consulta_medica) return `Consulta del ${formatFechaMes(r.consulta_medica.fecha_consulta)}`
    // Tiene consulta pero no llegó con ella (una consulta borrada no se
    // carga): decir «Sin consulta» sería falso.
    if (r.consulta_medica_id) return 'Otra consulta'
    return 'Sin consulta'
  }

  const eliminar = useEliminarResultado()
  const ver = useVerResultado()
  const { usuario, hasRole } = useAuthStore()

  // Lo retira quien lo subió o la administración, igual que exige el
  // servidor: un botón que siempre responde 403 es peor que no enseñarlo.
  const puedeRetirar = (r: ResultadoMedico) =>
    r.subido_por?.id === usuario?.id || hasRole('admin-dispensario')

  // Los registran el médico y el odontólogo (D2, P1); Enfermería solo los ve.
  const puedeSubir = hasRole('medico') || hasRole('odontologo')

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Group gap="sm">
          <Text size="sm" fw={500}>
            Resultados
            {resultados.length > 0 && (
              <Text span c="dimmed" ml={4}>
                ({resultados.length})
              </Text>
            )}
          </Text>
          <SegmentedControl
            size="xs"
            data={ALCANCES}
            value={alcance}
            onChange={(v) => setAlcance(v as Alcance)}
          />
        </Group>
        {puedeSubir && (
          <Button
            size="xs"
            leftSection={<IconPlus size={13} />}
            onClick={abrirModal}
          >
            Subir resultado
          </Button>
        )}
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los resultados"
        errorHint="No quiere decir que no se hayan subido: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!resultados.length}
        emptyProps={{
          icon: IconFileText,
          title: 'Sin resultados',
          description: alcance === 'consulta'
            ? 'No se han subido resultados médicos para esta consulta.'
            : 'Este paciente no tiene resultados médicos.',
        }}
        skeletonRows={2}
      >
        <Stack gap="sm">
          {resultados.map((r) => (
            <Card key={r.id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="sm" wrap="nowrap">
                  <ThemeIcon
                    size="md" variant="light" radius="xl"
                  >
                    {getIcon(TIPO_RESULTADO_ICONS[r.tipo] ?? 'IconFileText', 16)}
                  </ThemeIcon>
                  <Stack gap={0}>
                    <Group gap="xs">
                      <StatusBadge size="xs">
                        {getLabelTipo(r.tipo)}
                      </StatusBadge>
                      <Text size="xs" c="dimmed">
                        {formatFechaMes(r.fecha_resultado)}
                      </Text>
                    </Group>
                    <Text size="sm">{r.descripcion}</Text>
                    {alcance === 'paciente' && (
                      <Text size="xs" c="dimmed">{origen(r)}</Text>
                    )}
                    {r.subido_por?.servidor && (
                      <Text size="xs" c="dimmed">
                        Dr. {r.subido_por.servidor.nombre}{' '}
                        {r.subido_por.servidor.apellido}
                      </Text>
                    )}
                  </Stack>
                </Group>

                <Group gap="xs" wrap="nowrap">
                  <Tooltip label="Ver archivo" withArrow>
                    <ActionIcon
                      size="sm"
                      variant="light"
                      aria-label={`Ver el archivo de ${r.descripcion}`}
                      loading={ver.isPending && ver.variables?.id === r.id}
                      onClick={() => ver.mutate(r)}
                    >
                      <IconExternalLink size={13} />
                    </ActionIcon>
                  </Tooltip>
                  {puedeRetirar(r) && (
                    <Tooltip label="Retirar" withArrow>
                      <ActionIcon
                        size="sm"
                        variant="light"
                        color="red"
                        aria-label={`Retirar ${r.descripcion}`}
                        onClick={() => confirmar({
                          title:   'Retirar resultado',
                          message: 'Dejará de aparecer en la consulta. El archivo se conserva en el registro del Dispensario.',
                          confirmLabel: 'Retirar',
                          destructiva: true,
                          onConfirm: () => eliminar.mutate(r.id),
                        })}
                      >
                        <IconTrash size={13} />
                      </ActionIcon>
                    </Tooltip>
                  )}
                </Group>
              </Group>
            </Card>
          ))}
        </Stack>
      </DataState>

      <SubirResultadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        historiaClinicaId={historiaClinicaId}
      />
    </Stack>
  )
}
