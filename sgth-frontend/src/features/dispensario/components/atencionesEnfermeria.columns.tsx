'use client'

import { Group, Stack, Text, Avatar } from '@mantine/core'
import { IconUser, IconUsers, IconBan } from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import { StatusBadge } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'

interface ColumnActions {
  onAnular: (atencion: AtencionEnfermeria) => void
}

function nombrePaciente(atencion: AtencionEnfermeria): string {
  const nombre = atencion.servidor_id
    ? `${atencion.servidor?.nombre ?? ''} ${atencion.servidor?.apellido ?? ''}`
    : `${atencion.carga_familiar?.nombres ?? ''} ${atencion.carga_familiar?.apellidos ?? ''}`

  return nombre.trim() || '—'
}

function horaDe(atencion: AtencionEnfermeria): string {
  return new Date(atencion.atendido_en).toLocaleTimeString('es-EC', {
    hour: '2-digit', minute: '2-digit',
  })
}

export function getAtencionesEnfermeriaColumns(
  actions: ColumnActions
): DataTableColumn<AtencionEnfermeria>[] {
  return [
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (atencion) => {
        const esServidor = !!atencion.servidor_id
        // Las anuladas no se esconden: siguen en la lista, atenuadas por el
        // `rowStyle` de la tabla y con su motivo a la vista. Esconderlas
        // dejaría el mismo hueco que había antes, cuando no se podían anular.
        const anulada = !!atencion.anulado_en

        return (
          <Group gap="sm" wrap="nowrap">
            <Avatar color={anulada ? 'slate' : undefined} radius="xl" size="sm">
              {esServidor ? <IconUser size={14} /> : <IconUsers size={14} />}
            </Avatar>
            <Stack gap={0}>
              <Group gap={6} wrap="nowrap">
                <Text size="sm" fw={600} td={anulada ? 'line-through' : undefined}>
                  {nombrePaciente(atencion)}
                </Text>
                {anulada && (
                  <StatusBadge tone="danger" size="xs">Anulada</StatusBadge>
                )}
              </Group>
              <Group gap={6}>
                <Text size="xs" c="dimmed" ff="monospace">{atencion.folio}</Text>
                <Text size="xs" c="dimmed">· {horaDe(atencion)}</Text>
              </Group>
              {anulada && atencion.motivo_anulacion && (
                <Text size="xs" c="dimmed" fs="italic">
                  Motivo: {atencion.motivo_anulacion}
                  {atencion.anulador && (
                    <> — {atencion.anulador.nombre_completo ?? atencion.anulador.usuario_ti}</>
                  )}
                </Text>
              )}
            </Stack>
          </Group>
        )
      },
    },
    {
      accessor: 'catalogo_servicio',
      title:    'Servicio',
      width:    170,
      render: (atencion) => (
        <Stack gap={2} align="flex-start">
          <StatusBadge>{atencion.catalogo_servicio?.nombre ?? '—'}</StatusBadge>
          <Text size="xs" c="dimmed">
            Por: {atencion.enfermera?.nombre_completo
              ?? atencion.enfermera?.usuario_ti ?? '—'}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (atencion) => {
        const anulada = !!atencion.anulado_en

        return (
          <TableActions actions={[
            {
              // Una atención anulada no se anula dos veces, pero la acción se
              // deja a la vista inhabilitada: esconderla haría pensar que
              // faltan permisos (ver regla 06).
              label:    anulada ? 'Ya está anulada' : 'Anular atención',
              icon:     <IconBan size={14} />,
              color:    anulada ? undefined : 'red',
              onClick:  () => actions.onAnular(atencion),
              disabled: anulada,
            },
          ]} />
        )
      },
    },
  ]
}
