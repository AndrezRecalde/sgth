'use client'

import { useState } from 'react'
import { Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconEdit } from '@tabler/icons-react'
import { DataState, SectionHeading, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { textoExperiencia } from '../constants/perfilPuesto'
import { useDescripcionPuesto } from '../hooks/useDescripcionPuesto'
import { PuestoDescripcionModal } from './PuestoDescripcionModal'

interface Props {
  puestoId:    number
  soloLectura: boolean
}

/** Un dato de la ficha, o «Sin registrar» en gris. */
function Dato({ titulo, valor }: { titulo: string; valor?: string | null }) {
  return (
    <div>
      <Text size="xs" c="dimmed">{titulo}</Text>
      <Text size="sm" c={valor ? undefined : 'dimmed'} style={{ whiteSpace: 'pre-line' }}>
        {valor || 'Sin registrar'}
      </Text>
    </div>
  )
}

/**
 * La descripción del puesto, como la ficha del Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P1): misión y relaciones, requisitos de
 * instrucción y experiencia, y capacitación. Se lee aquí y se edita en un
 * modal, entera.
 */
export function PuestoDescripcionPanel({ puestoId, soloLectura }: Props) {
  const { data: descripcion, isLoading, error, refetch } = useDescripcionPuesto(puestoId)
  const [editando, setEditando] = useState(false)

  return (
    <DataState
      loading={isLoading}
      error={error}
      errorTitle="No se pudo cargar la descripción del puesto"
      onRetry={refetch}
      empty={false}
      skeletonRows={3}
    >
      {descripcion && (
        <Stack gap="lg">
          <Group justify="space-between" align="center">
            <Text size="xs" c="dimmed">
              {descripcion.actualizado_en
                ? `Actualizada el ${formatFecha(descripcion.actualizado_en)}.`
                : 'El puesto todavía no tiene descripción.'}
            </Text>
            {!soloLectura && (
              <Button size="xs" variant="light" leftSection={<IconEdit size={14} />} onClick={() => setEditando(true)}>
                Editar descripción
              </Button>
            )}
          </Group>

          <Stack gap="xs">
            <SectionHeading title="Misión y relaciones" />
            <Dato titulo="Misión" valor={descripcion.mision} />
            <Dato titulo="Relaciones internas y externas" valor={descripcion.relaciones} />
            <Dato titulo="Subproceso" valor={descripcion.subproceso} />
          </Stack>

          <Stack gap="xs">
            <SectionHeading
              title="Requisitos"
              action={descripcion.nivel_label ? <StatusBadge>{descripcion.nivel_label}</StatusBadge> : undefined}
            />
            {descripcion.requisitos.length === 0 ? (
              <Text size="sm" c="dimmed">Sin alternativas de instrucción registradas.</Text>
            ) : (
              <Stack gap={4}>
                <Text size="xs" c="dimmed">Instrucción formal y experiencia, una de estas alternativas:</Text>
                {descripcion.requisitos.map((r, i) => (
                  <Card key={i} withBorder radius="md" p="xs">
                    <Text size="sm" fw={500}>
                      {r.nivel_instruccion_label}{r.detalle ? ` (${r.detalle})` : ''}
                    </Text>
                    <Text size="xs" c="dimmed">Experiencia: {textoExperiencia(r.meses_experiencia)}</Text>
                  </Card>
                ))}
              </Stack>
            )}
            <Dato titulo="Área de conocimiento" valor={descripcion.area_conocimiento} />
            <Dato titulo="Especificidad de la experiencia" valor={descripcion.especificidad_experiencia} />
          </Stack>

          <Stack gap="xs">
            <SectionHeading title="Capacitación" />
            <Dato titulo="Temáticas" valor={descripcion.capacitacion} />
          </Stack>

          {!soloLectura && (
            <PuestoDescripcionModal
              opened={editando}
              onClose={() => setEditando(false)}
              puestoId={puestoId}
              descripcion={descripcion}
            />
          )}
        </Stack>
      )}
    </DataState>
  )
}
