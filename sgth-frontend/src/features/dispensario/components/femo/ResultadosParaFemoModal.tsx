'use client'

import { useState } from 'react'
import { Card, Checkbox, Group, Stack, Text } from '@mantine/core'
import { IconFileText } from '@tabler/icons-react'
import { DataState, ModalFooter, SgthModal, StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { useResultadosParaFemo } from '../../hooks/useFemoResultados'
import { INTERPRETACION } from '../../services/resultadoMedicoService'
import type { ExamenForm } from '../../schemas/femoEvaluacion.schema'

interface Props {
  opened:   boolean
  onClose:  () => void
  cedula?:  string
  /** La fecha de la evaluación: trae lo del año anterior a ella. */
  hasta?:   string | null
  /** Los que ya están en la sección J, para no copiarlos dos veces. */
  yaCopiados: number[]
  onAgregar:  (filas: ExamenForm[]) => void
}

/**
 * Traer a la sección J los resultados que el Dispensario ya tiene del paciente
 * (5.6 del diseño de exámenes): cada uno con su nombre, su fecha y la lectura
 * del médico. Lo que no tenga archivo se sigue escribiendo a mano.
 */
export function ResultadosParaFemoModal({ opened, onClose, cedula, hasta, yaCopiados, onAgregar }: Props) {
  const { data = [], isLoading, error, refetch } = useResultadosParaFemo(cedula, hasta, opened)
  const [marcados, setMarcados] = useState<number[]>([])

  const cerrar = () => {
    setMarcados([])
    onClose()
  }
  const agregar = () => {
    onAgregar(data.filter((r) => marcados.includes(r.id)).map((r) => r.para_femo))
    cerrar()
  }

  return (
    <SgthModal opened={opened} onClose={cerrar} title="Traer de los resultados del Dispensario" size="lg">
      <Stack gap="sm">
        <Text size="xs" c="dimmed">
          Los del último año hasta la fecha de la evaluación. Se copian con la lectura del médico, si ya la tienen.
        </Text>
        <DataState loading={isLoading} error={error} onRetry={() => refetch()}
          errorTitle="No se pudieron cargar los resultados" empty={!data.length}
          emptyProps={{ icon: IconFileText, title: 'Sin resultados', description: 'El Dispensario no tiene resultados de este paciente en el último año.' }}
          skeletonRows={3}>
          <Stack gap="xs">
            {data.map((r) => {
              const copiado = yaCopiados.includes(r.id)
              const lectura = r.interpretacion ? INTERPRETACION[r.interpretacion] : null
              return (
                <Card key={r.id} withBorder radius="md" p="xs">
                  <Checkbox
                    disabled={copiado}
                    checked={copiado || marcados.includes(r.id)}
                    onChange={(e) => {
                      // Se lee antes: React corre el actualizador después,
                      // cuando el evento ya no tiene currentTarget.
                      const marcado = e.currentTarget.checked
                      setMarcados((m) => marcado ? [...m, r.id] : m.filter((id) => id !== r.id))
                    }}
                    label={
                      <Stack gap={2}>
                        <Group gap="xs">
                          <Text size="sm" fw={500}>{r.para_femo.nombre_examen}</Text>
                          {lectura
                            ? <StatusBadge size="xs" tone={lectura.tone}>{lectura.label}</StatusBadge>
                            : <StatusBadge size="xs" tone="warning" variant="dot">Sin lectura</StatusBadge>}
                          {copiado && <StatusBadge size="xs">Ya en la sección J</StatusBadge>}
                        </Group>
                        <Text size="xs" c="dimmed">
                          {formatFechaMes(r.fecha_resultado)}
                          {r.orden_examen ? ` · Orden ${r.orden_examen.folio}` : ''}
                          {r.para_femo.resultado ? ` · ${r.para_femo.resultado}` : ''}
                        </Text>
                      </Stack>
                    }
                  />
                </Card>
              )
            })}
          </Stack>
        </DataState>
      </Stack>
      <ModalFooter onCancel={cerrar} submitLabel={`Agregar a la sección J (${marcados.length})`}
        submitDisabled={!marcados.length} onSubmit={agregar} />
    </SgthModal>
  )
}
