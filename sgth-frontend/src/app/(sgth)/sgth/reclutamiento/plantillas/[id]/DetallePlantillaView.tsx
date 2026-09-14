'use client'

import { Stack, Group, Text, Button, Card, ActionIcon, ThemeIcon, Divider, Skeleton, Alert } from '@mantine/core'
import {
  IconArrowLeft,
  IconPlus,
  IconTrash,
  IconList,
  IconHash,
  IconCheckbox,
  IconInfoCircle,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import {
  usePlantillaDetalle,
  useEliminarCriterioPlantilla,
} from '@/features/seleccion/hooks/usePlantilla'
import { AgregarCriterioPlantillaModal } from
  '@/features/seleccion/components/AgregarCriterioPlantillaModal'
import { TIPO_CONTRATO_PLANTILLA_OPTIONS } from
  '@/features/seleccion/services/plantillaService'
import type { SeccionCriterio } from
  '@/features/seleccion/services/criterioService'
import { useState } from 'react'
import { confirmar, EmptyState, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'

interface Props {
  id: string
}

const TIPO_ICONS: Record<string, React.ReactNode> = {
  radio:     <IconList size={13} />,
  checklist: <IconCheckbox size={13} />,
  numero:    <IconHash size={13} />,
}

export function DetallePlantillaView({ id }: Props) {
  const plantillaId  = Number(id)
  const router       = useRouter()
  const [seccionModal, setSeccionModal] =
    useState<SeccionCriterio>('meritos')
  const [modalOpened,
    { open, close }] = useDisclosure(false)

  const { data: plantilla, isLoading } =
    usePlantillaDetalle(plantillaId)
  const eliminarCriterio =
    useEliminarCriterioPlantilla(plantillaId)

  const getLabelTipo = (tipo: string | null | undefined) =>
    TIPO_CONTRATO_PLANTILLA_OPTIONS.find(
      o => o.value === tipo
    )?.label ?? 'General'

  const abrirModal = (seccion: SeccionCriterio) => {
    setSeccionModal(seccion)
    open()
  }

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={80} radius="lg" />
        <Skeleton height={200} radius="lg" />
      </PageShell>
    )
  }

  // Antes devolvía null: una plantilla inexistente dejaba la página en blanco.
  if (!plantilla) {
    return (
      <PageShell>
        <PageHeader title="Plantilla" onBack={() => router.push(ROUTES.SGTH.PLANTILLAS)} />
        <EmptyState
          icon={IconList}
          title="Plantilla no encontrada"
          description="No existe o no está disponible. Vuelva al listado y ábrala desde allí."
        />
      </PageShell>
    )
  }

  const meritos   = plantilla.criterios?.filter(
    c => c.seccion === 'meritos'
  ) ?? []
  const oposicion = plantilla.criterios?.filter(
    c => c.seccion === 'oposicion'
  ) ?? []
  const totalPts  = plantilla.criterios?.reduce(
    (acc, c) => acc + Number(c.puntaje_maximo), 0
  ) ?? 0

  const renderCriterios = (
    criterios: typeof meritos,
  ) => (
    <Stack gap="xs">
      {criterios.length === 0 ? (
        <Alert color="slate" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">Sin criterios en esta sección.</Text>
        </Alert>
      ) : (
        criterios.map((c, i) => (
          <Card key={c.id} withBorder radius="md" p="sm">
            <Group justify="space-between" wrap="nowrap">
              <Group gap="sm" wrap="nowrap">
                <ThemeIcon
                  size="sm" variant="light"
                >
                  {TIPO_ICONS[c.tipo_input]}
                </ThemeIcon>
                <Stack gap={2}>
                  <Text size="sm" fw={500}>
                    {i + 1}. {c.nombre}
                  </Text>
                  {c.descripcion && (
                    <Text size="xs" c="dimmed">{c.descripcion}</Text>
                  )}
                  {c.opciones.length > 0 && (
                    <Group gap="xs" mt={2}>
                      {c.opciones.map(op => (
                        <StatusBadge key={op.id} size="xs">
                          {op.etiqueta}: {op.puntaje} pts
                        </StatusBadge>
                      ))}
                    </Group>
                  )}
                </Stack>
              </Group>
              <Group gap="xs" wrap="nowrap">
                <StatusBadge>
                  {c.puntaje_maximo} pts
                </StatusBadge>
                <ActionIcon
                  size="sm"
                  color="red"
                  variant="subtle"
                  onClick={() => confirmar({
                    title:   'Eliminar criterio',
                    message: <>Se eliminará el criterio <b>{c.nombre}</b>. No se puede deshacer.</>,
                    destructiva: true,
                    onConfirm: () => eliminarCriterio.mutate(c.id),
                  })}
                >
                  <IconTrash size={13} />
                </ActionIcon>
              </Group>
            </Group>
          </Card>
        ))
      )}
    </Stack>
  )

  return (
    <PageShell>
      <PageHeader
        title={plantilla.nombre}
        description={getLabelTipo(plantilla.tipo_contrato)}
        actions={
          <Button
            variant="default"
            leftSection={<IconArrowLeft size={14} />}
            onClick={() =>
              router.push(ROUTES.SGTH.PLANTILLAS)
            }
          >
            Volver
          </Button>
        }
      />

      <Card withBorder radius="lg" p="md">
        <Group justify="space-between">
          <Text size="xs" c="dimmed">{plantilla.descripcion}</Text>
          <StatusBadge tone={totalPts === 100 ? 'success' : 'warning'} size="md">
            Total: {totalPts.toFixed(0)} / 100 pts
          </StatusBadge>
        </Group>
      </Card>

      {totalPts !== 100 && (plantilla.criterios?.length ?? 0) > 0 && (
        <Alert color="amber" variant="light"
          icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Los criterios deben sumar exactamente 100 puntos.
            Actualmente suman {totalPts.toFixed(0)} puntos.
          </Text>
        </Alert>
      )}

      <Stack gap="md">
        <Stack gap="sm">
          <Group justify="space-between">
            <Group gap="xs">
              <Text size="sm" fw={700}>Méritos</Text>
              <StatusBadge>
                {meritos.reduce(
                  (a, c) => a + Number(c.puntaje_maximo), 0
                ).toFixed(0)} pts
              </StatusBadge>
            </Group>
            <Button
              size="compact-xs"
              variant="light"
              leftSection={<IconPlus size={12} />}
              onClick={() => abrirModal('meritos')}
            >
              Agregar criterio
            </Button>
          </Group>
          {renderCriterios(meritos)}
        </Stack>

        <Divider />

        <Stack gap="sm">
          <Group justify="space-between">
            <Group gap="xs">
              <Text size="sm" fw={700}>Oposición</Text>
              <StatusBadge>
                {oposicion.reduce(
                  (a, c) => a + Number(c.puntaje_maximo), 0
                ).toFixed(0)} pts
              </StatusBadge>
            </Group>
            <Button
              size="compact-xs"
              variant="light"
              leftSection={<IconPlus size={12} />}
              onClick={() => abrirModal('oposicion')}
            >
              Agregar criterio
            </Button>
          </Group>
          {renderCriterios(oposicion)}
        </Stack>
      </Stack>

      <AgregarCriterioPlantillaModal
        opened={modalOpened}
        onClose={close}
        plantillaId={plantillaId}
        seccionInicial={seccionModal}
      />
    </PageShell>
  )
}
