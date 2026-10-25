'use client'

import { DataState } from '@/components/ui'
import { Stack, Text, Button, Group, SegmentedControl } from '@mantine/core'
import { useState } from 'react'
import { IconPlus, IconFileText } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { SubirResultadoModal } from './SubirResultadoModal'
import { ResultadoCard } from './examenes/ResultadoCard'
import { RevisarResultadoModal } from './examenes/RevisarResultadoModal'
import { useResultadosPorConsulta, useResultadosPorPaciente } from '../hooks/useResultadoMedico'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { ResultadoMedico } from '../services/resultadoMedicoService'
import { formatFechaMes } from '@/lib/fecha'
import { useAuthStore } from '@/store/auth.store'

interface Props {
  consulta:          ConsultaMedica
  historiaClinicaId: number
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

  const [revisando, setRevisando] = useState<ResultadoMedico | null>(null)
  const { hasRole } = useAuthStore()

  // Los registran el médico y el odontólogo (D2, P1).
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
            <ResultadoCard key={r.id} resultado={r} onRevisar={setRevisando}
              origen={alcance === 'paciente' ? origen(r) : undefined} />
          ))}
        </Stack>
      </DataState>

      <RevisarResultadoModal resultado={revisando} onClose={() => setRevisando(null)} />

      <SubirResultadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        historiaClinicaId={historiaClinicaId}
      />
    </Stack>
  )
}
