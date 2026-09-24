'use client'

import { ActionIcon, Card, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconPlus, IconTrash } from '@tabler/icons-react'
import { SectionHeading, StatusBadge } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import type { AlergiaPaciente } from '../services/historiaClinicaService'

const TONO_SEVERIDAD: Record<string, SemanticTone> = {
  leve:     'info',
  moderada: 'warning',
  grave:    'danger',
}

interface Props {
  alergias:  AlergiaPaciente[]
  onAgregar: () => void
  onAnular:  (alergia: AlergiaPaciente) => void
}

/**
 * El aviso de alergia grave, arriba del todo del panel y fuera de su sección.
 *
 * Va suelto a propósito: es lo primero que tiene que ver quien va a recetar, y
 * dentro de la lista quedaría a la altura de las demás.
 */
export function AvisoAlergiaGrave({ alergias }: { alergias: AlergiaPaciente[] }) {
  const graves = alergias.filter(a => a.severidad === 'grave')
  if (graves.length === 0) return null

  return (
    <Card
      withBorder
      radius="sm"
      p="xs"
      style={{
        borderColor: 'var(--mantine-color-red-6)',
        backgroundColor: 'var(--mantine-color-red-light)',
      }}
    >
      <Group gap="xs">
        <IconAlertTriangle size={14} color="var(--mantine-color-red-6)" />
        <Text size="xs" fw={600} c="red">
          Alergia grave detectada
        </Text>
      </Group>
      {graves.map(a => (
        <Text key={a.id} size="xs" c="red" ml="xs">
          · {a.descripcion}
        </Text>
      ))}
    </Card>
  )
}

/** La lista de alergias del panel de contexto. */
export function SeccionAlergias({ alergias, onAgregar, onAnular }: Props) {
  return (
    <>
      <SectionHeading
        title="Alergias"
        action={
          <ActionIcon
            size="xs"
            variant="subtle"
            aria-label="Agregar alergia"
            onClick={onAgregar}
          >
            <IconPlus size={10} />
          </ActionIcon>
        }
      />
      {alergias.length === 0 ? (
        <Text size="xs" c="dimmed">Ninguna registrada</Text>
      ) : (
        <Stack gap={3}>
          {alergias.map((a) => (
            <Group key={a.id} gap={5} wrap="nowrap" justify="space-between">
              <Group gap={5} wrap="nowrap" style={{ flex: 1 }}>
                {/* El backend la exige, pero el tipo la declara opcional; el
                    `?? ''` deja que caiga en 'neutral' en vez de romper. */}
                <StatusBadge tone={TONO_SEVERIDAD[a.severidad ?? ''] ?? 'neutral'} size="xs">
                  {a.severidad}
                </StatusBadge>
                <Text size="xs" lineClamp={1}>{a.descripcion}</Text>
              </Group>
              <ActionIcon
                size="xs"
                variant="subtle"
                aria-label={`Anular alergia: ${a.descripcion}`}
                onClick={() => onAnular(a)}
              >
                <IconTrash size={10} />
              </ActionIcon>
            </Group>
          ))}
        </Stack>
      )}
    </>
  )
}
