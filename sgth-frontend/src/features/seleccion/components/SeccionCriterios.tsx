'use client'

import { ActionIcon, Alert, Button, Card, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core'
import { IconCheckbox, IconHash, IconInfoCircle, IconList, IconPlus, IconTrash } from '@tabler/icons-react'
import { confirmar, SectionHeading, StatusBadge } from '@/components/ui'
import { useEliminarCriterio } from '../hooks/useCriterio'
import type { CriterioEvaluacion } from '../services/criterioService'

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

interface Props {
  titulo:         string
  criterios:      CriterioEvaluacion[]
  convocatoriaId: number
  editable:       boolean
  onAgregar:      () => void
}

/** Una sección de criterios —méritos u oposición— con su subtotal. */
export function SeccionCriterios({ titulo, criterios, convocatoriaId, editable, onAgregar }: Props) {
  const eliminar = useEliminarCriterio(convocatoriaId)
  const total = criterios.reduce((s, c) => s + Number(c.puntaje_maximo), 0)

  return (
    <Stack gap="sm">
      <SectionHeading
        title={titulo}
        action={
          <Group gap="xs">
            <StatusBadge>{total.toFixed(0)} pts</StatusBadge>
            {editable && (
              <Button size="compact-xs" variant="light" leftSection={<IconPlus size={12} />} onClick={onAgregar}>
                Agregar criterio
              </Button>
            )}
          </Group>
        }
      />

      {criterios.length === 0 ? (
        <Alert color="slate" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            No hay criterios configurados para esta sección.
            {editable && ' Agregue criterios antes de publicar la convocatoria.'}
          </Text>
        </Alert>
      ) : criterios.map((c, i) => (
        <Card key={c.id} withBorder radius="md" p="sm">
          <Group justify="space-between" wrap="nowrap">
            <Group gap="sm" wrap="nowrap">
              <ThemeIcon size="sm" variant="light">{TIPO_ICONS[c.tipo_input]}</ThemeIcon>
              <Stack gap={2}>
                <Group gap="xs">
                  <Text size="sm" fw={500}>{i + 1}. {c.nombre}</Text>
                  <StatusBadge size="xs" variant="dot">{TIPO_LABELS[c.tipo_input]}</StatusBadge>
                </Group>
                {c.descripcion && <Text size="xs" c="dimmed">{c.descripcion}</Text>}
                {c.opciones.length > 0 && (
                  <Group gap="xs" mt={2}>
                    {c.opciones.map(op => (
                      <StatusBadge key={op.id} size="xs">{op.etiqueta}: {op.puntaje} pts</StatusBadge>
                    ))}
                  </Group>
                )}
              </Stack>
            </Group>
            <Group gap="xs" wrap="nowrap">
              <StatusBadge size="md">{c.puntaje_maximo} pts</StatusBadge>
              {editable && (
                <Tooltip label="Eliminar criterio">
                  <ActionIcon
                    size="sm"
                    color="red"
                    variant="subtle"
                    aria-label={`Eliminar el criterio ${c.nombre}`}
                    onClick={() => confirmar({
                      title: 'Eliminar criterio',
                      message: <>Se eliminará el criterio <b>{c.nombre}</b>. No se puede deshacer.</>,
                      destructiva: true,
                      onConfirm: () => eliminar.mutate(c.id),
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
  )
}
