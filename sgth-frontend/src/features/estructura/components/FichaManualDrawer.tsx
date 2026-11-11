'use client'

import { useState } from 'react'
import { Alert, Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconLinkOff, IconUpload } from '@tabler/icons-react'
import { DataState, SectionHeading, SgthDrawer, StatusBadge, confirmar } from '@/components/ui'
import { formatFechaMesHora } from '@/lib/fecha'
import { estadoFichaManual, textoContenido } from '../constants/manualPuestos'
import { useFichaManual, useManualPuestosMutations } from '../hooks/useManualPuestos'
import type { FichaManualDetalle } from '../services/manualPuestosService'
import { FichaManualCandidatos } from './FichaManualCandidatos'
import { FichaManualPropuesta } from './FichaManualPropuesta'

interface Props {
  fichaId: number | null
  onClose: () => void
}

/**
 * Una ficha del Manual de Puestos en la revisión (P4, PR 12): su estado, el
 * puesto en que se carga y lo que se carga. Sin aprobación (D6), lo que TH
 * confirma aquí queda como el perfil del puesto.
 */
export function FichaManualDrawer({ fichaId, onClose }: Props) {
  const { data: ficha, isLoading, error } = useFichaManual(fichaId)

  return (
    <SgthDrawer
      opened={fichaId !== null}
      onClose={onClose}
      title="Ficha del Manual de Puestos"
      description={ficha ? [ficha.denominacion, ficha.gestion].filter(Boolean).join(' · ') : undefined}
      ancho="lg"
    >
      <DataState loading={isLoading} error={error}>
        {/* `key`: el puesto elegido es de esta ficha; al abrir otra, vuelve al sugerido. */}
        {ficha && <Contenido key={ficha.id} ficha={ficha} />}
      </DataState>
    </SgthDrawer>
  )
}

function Contenido({ ficha }: { ficha: FichaManualDetalle }) {
  const { cargar, desvincular } = useManualPuestosMutations()
  const sugerido = ficha.candidatos.find((c) => c.puesto_id === ficha.puesto?.id && !c.ficha_cargada)
  const [puestoId, setPuestoId] = useState<number | null>(sugerido?.puesto_id ?? null)
  const elegido = ficha.candidatos.find((c) => c.puesto_id === puestoId)
  const { label, tone } = estadoFichaManual(ficha.estado)

  const alCargar = () => {
    if (!elegido) return
    const tiene = textoContenido(elegido.contenido)
    confirmar({
      title: 'Cargar la ficha en el puesto',
      message: (
        <>
          El perfil de <b>{elegido.cargo}</b> queda como el de esta ficha: misión, requisitos, capacitación,
          {' '}{ficha.propuesta.actividades.length} actividades y {ficha.propuesta.competencias.length} competencias.
          {tiene ? <> Lo que tiene ahora ({tiene}) se reemplaza.</> : null}
        </>
      ),
      confirmLabel: 'Cargar',
      onConfirm: () => cargar.mutate({ fichaId: ficha.id, puestoId: elegido.puesto_id }),
    })
  }

  const alDesvincular = () => confirmar({
    title: 'Quitar el puesto de la ficha',
    message: <>La ficha vuelve a quedar sin puesto. El perfil que se cargó en <b>{ficha.puesto?.cargo}</b> se queda como está: se edita desde el perfil del puesto.</>,
    confirmLabel: 'Quitar',
    destructiva: true,
    onConfirm: () => desvincular.mutate(ficha.id),
  })

  return (
    <Stack gap="lg">
      <Card withBorder radius="md" p="sm">
        <Group gap="xs" mb={4}>
          <StatusBadge tone={tone}>{label}</StatusBadge>
          <Text size="xs" c="dimmed">{ficha.archivo}</Text>
        </Group>
        <Text size="sm">{ficha.motivo}</Text>
      </Card>

      {ficha.estado === 'cargada' && ficha.puesto ? (
        <div>
          <SectionHeading title="Puesto del SGTH" />
          <Text size="sm" fw={600}>{ficha.puesto.cargo}</Text>
          <Text size="xs" c="dimmed">
            {ficha.puesto.unidad}{ficha.cargada_en ? ` · cargada el ${formatFechaMesHora(ficha.cargada_en)}` : ''}{ficha.cargada_por ? ` por ${ficha.cargada_por}` : ''}
          </Text>
          {ficha.puesto.eliminado && <Text size="xs" c="red" mt={4}>El puesto se eliminó después de la carga.</Text>}
          <Button mt="sm" size="xs" variant="light" color="red" leftSection={<IconLinkOff size={14} />}
            loading={desvincular.isPending} onClick={alDesvincular}>
            Quitar el puesto
          </Button>
        </div>
      ) : (
        <div>
          <SectionHeading title="¿En qué puesto se carga?" />
          {!ficha.unidad_sgth && (
            <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
              Elija primero a qué unidad de hoy corresponde «{ficha.gestion || 'su gestión'}», en «Unidades del manual».
            </Alert>
          )}
          {ficha.unidad_sgth && ficha.candidatos.length === 0 && (
            <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
              {ficha.unidad_sgth.nombre} no tiene puestos de plantilla. Cree el puesto en «Puestos» y vuelva a esta ficha.
            </Alert>
          )}
          {ficha.candidatos.length > 0 && (
            <>
              <Text size="xs" c="dimmed" mb="xs">Los puestos de plantilla de {ficha.unidad_sgth?.nombre}, del más parecido al menos.</Text>
              <FichaManualCandidatos candidatos={ficha.candidatos} valor={puestoId} onChange={setPuestoId} />
              <Group justify="flex-end" mt="sm">
                <Button leftSection={<IconUpload size={16} />} disabled={!elegido || elegido.ficha_cargada !== null}
                  loading={cargar.isPending} onClick={alCargar}>
                  Cargar en el puesto
                </Button>
              </Group>
            </>
          )}
        </div>
      )}

      <FichaManualPropuesta ficha={ficha} />
    </Stack>
  )
}
