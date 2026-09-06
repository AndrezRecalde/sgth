'use client'

import { Badge, Card, Divider, Group, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import type { PermisoServidor } from '@/types/api'

/**
 * La ficha de un permiso, de solo lectura.
 *
 * Separada de la pantalla que la usa porque son dos cosas distintas: esto
 * muestra, y `PermisoPorFolio` decide qué se puede hacer con ello.
 */

function Dato({ etiqueta, valor }: { etiqueta: string; valor?: string | null }) {
  return (
    <Group justify="space-between" wrap="nowrap" align="flex-start" gap="md">
      <Text size="sm" c="dimmed" style={{ whiteSpace: 'nowrap' }}>{etiqueta}</Text>
      <Text size="sm" fw={500} ta="right">{valor?.trim() ? valor : '—'}</Text>
    </Group>
  )
}

export function PermisoResumen({ permiso }: { permiso: PermisoServidor }) {
  const estado = permiso.estado as string
  const servidor = permiso.servidor
  const nombre = [servidor?.apellido, servidor?.nombre].filter(Boolean).join(' ')

  return (
    <Card withBorder radius="lg" padding="lg">
      <Stack gap="sm">
        <Group justify="space-between" wrap="nowrap">
          <Text size="sm" c="dimmed">Estado</Text>
          <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
            {ESTADO_LABELS[estado] ?? estado}
          </StatusBadge>
        </Group>

        <Divider my={4} />

        <Dato etiqueta="Servidor" valor={nombre} />
        <Dato etiqueta="Cédula" valor={servidor?.cedula} />
        <Dato etiqueta="Unidad" valor={permiso.unidad_administrativa?.nombre} />

        <Divider my={4} />

        <Group justify="space-between" wrap="nowrap">
          <Text size="sm" c="dimmed">Tipo</Text>
          <Badge size="sm" variant="light" color="blue">
            {TIPO_LABELS[permiso.tipo as string] ?? permiso.tipo}
          </Badge>
        </Group>
        <Dato etiqueta="Fecha" valor={formatFecha(permiso.fecha)} />
        <Dato
          etiqueta="Horario"
          valor={`${permiso.hora_inicio?.substring(0, 5)} — ${permiso.hora_fin?.substring(0, 5)}`}
        />

        {/* El motivo llega tapado por la policy cuando no corresponde verlo:
            si no viene, es que este usuario no puede leerlo. */}
        <Dato etiqueta="Observación" valor={permiso.observacion ?? 'Reservada'} />
      </Stack>
    </Card>
  )
}
