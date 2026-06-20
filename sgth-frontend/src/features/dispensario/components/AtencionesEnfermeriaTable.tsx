'use client'

import {
  Stack, Group, Text, Badge, Avatar, Skeleton,
} from '@mantine/core'
import { IconUser, IconUsers, IconVaccine } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useAtencionesEnfermeria } from '../hooks/useAtencionEnfermeria'

interface Props {
  fecha: string
}

export function AtencionesEnfermeriaTable({ fecha }: Props) {
  const { data, isLoading } = useAtencionesEnfermeria({ fecha })
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

        return (
          <Group
            key={atencion.id}
            justify="space-between"
            p="sm"
            wrap="nowrap"
            style={{
              border: '1px solid var(--mantine-color-gray-2)',
              borderRadius: 8,
            }}
          >
            <Group gap="sm" wrap="nowrap">
              <Avatar
                color={esServidor ? 'emerald' : 'blue'}
                radius="xl"
                size="sm"
              >
                {esServidor
                  ? <IconUser size={14} />
                  : <IconUsers size={14} />}
              </Avatar>
              <Stack gap={0}>
                <Text size="sm" fw={600}>
                  {nombrePaciente.trim() || '—'}
                </Text>
                <Group gap={6}>
                  <Text size="xs" c="dimmed" ff="monospace">
                    {atencion.folio}
                  </Text>
                  <Text size="xs" c="dimmed">
                    · {hora}
                  </Text>
                </Group>
              </Stack>
            </Group>

            <Stack gap={2} align="flex-end">
              <Badge size="sm" variant="light" color="violet">
                {atencion.catalogo_servicio?.nombre ?? '—'}
              </Badge>
              <Text size="xs" c="dimmed">
                Por: {atencion.enfermera?.nombre_completo
                  ?? atencion.enfermera?.usuario_ti ?? '—'}
              </Text>
            </Stack>
          </Group>
        )
      })}
    </Stack>
  )
}
