import { Group, Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ServidorConFotografia } from '../services/tipos'
import { FotoServidor } from './FotoServidor'

/** Los servidores con su foto, en la pantalla Fotografías (fase 1.2). */
export function columnasFotografias(
  accionesDe: (s: ServidorConFotografia) => TableAction[],
): DataTableColumn<ServidorConFotografia>[] {
  return [
    {
      accessor: 'foto',
      title: '',
      width: 60,
      render: (s) => (
        <FotoServidor fotografiaId={s.fotografia?.id} nombre={s.nombre_completo} size={40} radius="xl" />
      ),
    },
    {
      accessor: 'nombre_completo',
      title: 'Servidor',
      render: (s) => (
        <Stack gap={0}>
          <Group gap={6} wrap="nowrap">
            <Text size="sm" fw={500}>{s.nombre_completo}</Text>
            {!s.estado && <StatusBadge size="xs">Inactivo</StatusBadge>}
          </Group>
          <Text size="xs" c="dimmed">{s.cedula}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'unidad',
      title: 'Unidad y cargo',
      render: (s) => (
        <Stack gap={0}>
          <Text size="sm">{s.unidad?.nombre ?? 'Sin unidad'}</Text>
          <Text size="xs" c="dimmed">{s.cargo ?? 'Sin puesto asignado'}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'fotografia',
      title: 'Foto',
      width: 150,
      render: (s) => s.fotografia ? (
        <Stack gap={2}>
          <StatusBadge tone="success" size="xs">Con foto</StatusBadge>
          <Text size="xs" c="dimmed">{formatFecha(s.fotografia.subida_en)}</Text>
        </Stack>
      ) : (
        // Espera algo de alguien: que Comunicación le tome la foto.
        <StatusBadge tone="warning" size="xs">Sin foto</StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => <TableActions actions={accionesDe(s)} />,
    },
  ]
}
