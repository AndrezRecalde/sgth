'use client'

import { useState } from 'react'
import {
  Stack, Group, Text, Badge, Avatar, Skeleton,
  ActionIcon, Tooltip,
} from '@mantine/core'
import {
  IconUser, IconUsers, IconVaccine, IconBan,
} from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import {
  useAtencionesEnfermeria, useAnularAtencionEnfermeria,
} from '../hooks/useAtencionEnfermeria'
import {
  AnularRegistroModal, MOTIVOS_ANULAR_ATENCION,
} from './AnularRegistroModal'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'

interface Props {
  fecha: string
}

export function AtencionesEnfermeriaTable({ fecha }: Props) {
  const { data, isLoading } = useAtencionesEnfermeria({ fecha })
  const anular = useAnularAtencionEnfermeria()
  const [aAnular, setAAnular] = useState<AtencionEnfermeria | null>(null)

  const atenciones = data?.data ?? []

  if (isLoading) {
    return (
      <Stack gap="xs">
        <Skeleton height={56} radius="md" />
        <Skeleton height={56} radius="md" />
      </Stack>
    )
  }

  if (atenciones.length === 0) {
    return (
      <EmptyState
        icon={IconVaccine}
        title="Sin servicios registrados"
        description="No hay atenciones de enfermería
          para esta fecha."
      />
    )
  }

  return (
    <>
      <Stack gap="xs">
        {atenciones.map((atencion) => {
          const esServidor = !!atencion.servidor_id
          const nombrePaciente = esServidor
            ? `${atencion.servidor?.nombre ?? ''} ${atencion.servidor?.apellido ?? ''}`
            : `${atencion.carga_familiar?.nombres ?? ''} ${atencion.carga_familiar?.apellidos ?? ''}`

          const hora = new Date(atencion.atendido_en)
            .toLocaleTimeString('es-EC', {
              hour: '2-digit', minute: '2-digit',
            })

          // Las anuladas no se esconden: siguen en la lista, atenuadas y con
          // su motivo. Esconderlas dejaría el mismo hueco que había antes,
          // cuando no se podían anular en absoluto.
          const anulada = !!atencion.anulado_en

          return (
            <Group
              key={atencion.id}
              justify="space-between"
              p="sm"
              wrap="nowrap"
              style={{
                border: '1px solid var(--mantine-color-gray-2)',
                borderRadius: 8,
                opacity: anulada ? 0.6 : 1,
              }}
            >
              <Group gap="sm" wrap="nowrap">
                <Avatar
                  color={anulada
                    ? 'gray'
                    : (esServidor ? 'emerald' : 'blue')}
                  radius="xl"
                  size="sm"
                >
                  {esServidor
                    ? <IconUser size={14} />
                    : <IconUsers size={14} />}
                </Avatar>
                <Stack gap={0}>
                  <Group gap={6} wrap="nowrap">
                    <Text
                      size="sm"
                      fw={600}
                      td={anulada ? 'line-through' : undefined}
                    >
                      {nombrePaciente.trim() || '—'}
                    </Text>
                    {anulada && (
                      <Badge size="xs" variant="light" color="orange">
                        Anulada
                      </Badge>
                    )}
                  </Group>
                  <Group gap={6}>
                    <Text size="xs" c="dimmed" ff="monospace">
                      {atencion.folio}
                    </Text>
                    <Text size="xs" c="dimmed">
                      · {hora}
                    </Text>
                  </Group>
                  {anulada && atencion.motivo_anulacion && (
                    <Text size="xs" c="dimmed" fs="italic">
                      Motivo: {atencion.motivo_anulacion}
                      {atencion.anulador && (
                        <> — {atencion.anulador.nombre_completo
                          ?? atencion.anulador.usuario_ti}</>
                      )}
                    </Text>
                  )}
                </Stack>
              </Group>

              <Group gap="xs" wrap="nowrap" align="flex-start">
                <Stack gap={2} align="flex-end">
                  <Badge
                    size="sm"
                    variant="light"
                    color={anulada ? 'gray' : 'violet'}
                  >
                    {atencion.catalogo_servicio?.nombre ?? '—'}
                  </Badge>
                  <Text size="xs" c="dimmed">
                    Por: {atencion.enfermera?.nombre_completo
                      ?? atencion.enfermera?.usuario_ti ?? '—'}
                  </Text>
                </Stack>

                {!anulada && (
                  <Tooltip label="Anular atención" withArrow>
                    <ActionIcon
                      variant="subtle"
                      color="orange"
                      onClick={() => setAAnular(atencion)}
                      aria-label="Anular atención"
                    >
                      <IconBan size={16} />
                    </ActionIcon>
                  </Tooltip>
                )}
              </Group>
            </Group>
          )
        })}
      </Stack>

      <AnularRegistroModal
        opened={!!aAnular}
        onClose={() => setAAnular(null)}
        titulo="Anular atención de enfermería"
        descripcion={`Se anulará ${aAnular?.folio ?? ''}.`}
        motivos={MOTIVOS_ANULAR_ATENCION}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!aAnular) return
          anular.mutate(
            { id: aAnular.id, motivo },
            { onSuccess: () => setAAnular(null) }
          )
        }}
      />
    </>
  )
}
