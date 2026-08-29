'use client'

import { useState } from 'react'
import {
  Stack, Text, Group, Badge, Button,
  Card, ActionIcon, ThemeIcon, Divider,
  Skeleton, Alert,
} from '@mantine/core'
import {
  IconPlus, IconTrash, IconToggleLeft,
  IconList, IconHash, IconCheckbox,
  IconInfoCircle,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useCriterios, useEliminarCriterio } from '../hooks/useCriterio'
import { AgregarCriterioModal } from './AgregarCriterioModal'
import type { CriterioEvaluacion, SeccionCriterio } from '../services/criterioService'
import { SeleccionarPlantillaModal } from './SeleccionarPlantillaModal'
import { IconTemplate } from '@tabler/icons-react'

interface Props {
  convocatoriaId: number
  editable:       boolean
}

const TIPO_ICONS: Record<string, React.ReactNode> = {
  radio:     <IconList size={13} />,
  checklist: <IconCheckbox size={13} />,
  numero:    <IconHash size={13} />,
}

const TIPO_LABELS: Record<string, string> = {
  radio:     'Opción única',
  checklist: 'Selección múltiple',
  numero:    'Valor numérico',
}

function SeccionCriterios({
  titulo, criterios, color, convocatoriaId, editable,
  onAgregar,
}: {
  titulo:         string
  criterios:      CriterioEvaluacion[]
  color:          string
  convocatoriaId: number
  editable:       boolean
  onAgregar:      () => void
}) {
  const eliminar = useEliminarCriterio(convocatoriaId)
  const total    = criterios.reduce(
    (acc, c) => acc + Number(c.puntaje_maximo), 0
  )

  return (
    <Stack gap="sm">
      <Group justify="space-between">
        <Group gap="xs">
          <Text size="sm" fw={700}>{titulo}</Text>
          <Badge size="sm" variant="light" color={color}>
            {total.toFixed(0)} pts totales
          </Badge>
        </Group>
        {editable && (
          <Button
            size="compact-xs"
            variant="light"
            color={color}
            leftSection={<IconPlus size={12} />}
            onClick={onAgregar}
          >
            Agregar criterio
          </Button>
        )}
      </Group>

      {criterios.length === 0 ? (
        <Alert color="gray" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            No hay criterios configurados para esta sección.
            {editable && ' Agrega criterios antes de publicar la convocatoria.'}
          </Text>
        </Alert>
      ) : (
        <Stack gap="xs">
          {criterios.map((c, i) => (
            <Card key={c.id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="sm" wrap="nowrap">
                  <ThemeIcon
                    size="sm" color={color} variant="light"
                  >
                    {TIPO_ICONS[c.tipo_input]}
                  </ThemeIcon>
                  <Stack gap={2}>
                    <Group gap="xs">
                      <Text size="sm" fw={500}>{i + 1}. {c.nombre}</Text>
                      <Badge size="xs" variant="dot" color="gray">
                        {TIPO_LABELS[c.tipo_input]}
                      </Badge>
                    </Group>
                    {c.descripcion && (
                      <Text size="xs" c="dimmed">{c.descripcion}</Text>
                    )}
                    {c.opciones.length > 0 && (
                      <Group gap="xs" mt={2}>
                        {c.opciones.map(op => (
                          <Badge
                            key={op.id}
                            size="xs"
                            variant="light"
                            color={color}
                          >
                            {op.etiqueta}: {op.puntaje} pts
                          </Badge>
                        ))}
                      </Group>
                    )}
                  </Stack>
                </Group>
                <Group gap="xs" wrap="nowrap">
                  <Badge size="md" variant="light" color={color}>
                    {c.puntaje_maximo} pts
                  </Badge>
                  {editable && (
                    <ActionIcon
                      size="sm"
                      color="red"
                      variant="subtle"
                      onClick={() => {
                        if (confirm(`¿Eliminar "${c.nombre}"?`)) {
                          eliminar.mutate(c.id)
                        }
                      }}
                    >
                      <IconTrash size={13} />
                    </ActionIcon>
                  )}
                </Group>
              </Group>
            </Card>
          ))}
        </Stack>
      )}
    </Stack>
  )
}

export function TabCriterios({ convocatoriaId, editable }: Props) {
  const { data: criterios = [], isLoading } =
    useCriterios(convocatoriaId)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [seccionModal, setSeccionModal] =
    useState<SeccionCriterio>('meritos')
  const [plantillaModalOpened,
    { open: abrirPlantilla, close: cerrarPlantilla }] =
    useDisclosure(false)

  const meritos   = criterios.filter(c => c.seccion === 'meritos')
  const oposicion = criterios.filter(c => c.seccion === 'oposicion')
  const totalPts  = criterios.reduce(
    (acc, c) => acc + Number(c.puntaje_maximo), 0
  )

  const abrirModal = (seccion: SeccionCriterio) => {
    setSeccionModal(seccion)
    open()
  }

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={60} radius="md" />
        <Skeleton height={60} radius="md" />
        <Skeleton height={60} radius="md" />
      </Stack>
    )
  }

  return (
    <Stack gap="md" p="md">
      {!editable && (
        <Alert color="blue" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Los criterios solo pueden modificarse mientras
            la convocatoria esté en estado Borrador.
          </Text>
        </Alert>
      )}

      <Group justify="space-between">
        <Text size="xs" fw={600} c="dimmed" tt="uppercase"
          style={{ letterSpacing: '0.05em' }}>
          Criterios configurados
        </Text>
        <Group gap="xs">
          <Badge
            size="md"
            variant="light"
            color={totalPts === 100 ? 'emerald' : 'orange'}
          >
            Total: {totalPts.toFixed(0)} / 100 pts
          </Badge>
          {editable && (
            <Button
              size="compact-xs"
              variant="light"
              color="blue"
              leftSection={<IconTemplate size={12} />}
              onClick={abrirPlantilla}
            >
              Usar plantilla
            </Button>
          )}
        </Group>
      </Group>

      {totalPts !== 100 && criterios.length > 0 && (
        <Alert color="orange" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Los criterios deben sumar exactamente 100 puntos.
            Actualmente suman {totalPts.toFixed(0)} puntos.
          </Text>
        </Alert>
      )}

      <SeccionCriterios
        titulo="Méritos (hoja de vida)"
        criterios={meritos}
        color="blue"
        convocatoriaId={convocatoriaId}
        editable={editable}
        onAgregar={() => abrirModal('meritos')}
      />

      <Divider />

      <SeccionCriterios
        titulo="Oposición (evaluación directa)"
        criterios={oposicion}
        color="orange"
        convocatoriaId={convocatoriaId}
        editable={editable}
        onAgregar={() => abrirModal('oposicion')}
      />

      <AgregarCriterioModal
        opened={modalOpened}
        onClose={close}
        convocatoriaId={convocatoriaId}
        seccionInicial={seccionModal}
      />
      <SeleccionarPlantillaModal
        opened={plantillaModalOpened}
        onClose={cerrarPlantilla}
        convocatoriaId={convocatoriaId}
        tieneCriterios={criterios.length > 0}
      />
    </Stack>
  )
}
