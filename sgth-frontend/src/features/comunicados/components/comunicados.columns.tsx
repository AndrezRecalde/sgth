import { Group, Stack, Text, Tooltip } from '@mantine/core'
import { IconCopy, IconEdit, IconEye, IconPinned, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { TONO_VIGENCIA } from '../constants/comunicados'
import type { Comunicado } from '../services/tipos'
import { estaFijada, rangoVigencia, resumenDestinatarios } from '../utils/presentacion'

interface Opciones {
  redacta: boolean
  onVer: (c: Comunicado) => void
  onEditar: (c: Comunicado) => void
  onDuplicar: (c: Comunicado) => void
  onEliminar: (c: Comunicado) => void
}

/** Las lecturas solo cuentan desde que se publica. */
function Lecturas({ c }: { c: Comunicado }) {
  if (c.estado === 'borrador') return <Text size="sm" c="dimmed">—</Text>

  return (
    <Stack gap={0}>
      <Text size="sm">{c.leidos ?? 0} de {c.total_destinatarios ?? 0} leídos</Text>
      {c.requiere_confirmacion && (
        <Text size="xs" c="dimmed">{c.confirmados ?? 0} confirmados</Text>
      )}
    </Stack>
  )
}

export function columnasComunicados({ redacta, onVer, onEditar, onDuplicar, onEliminar }: Opciones): DataTableColumn<Comunicado>[] {
  return [
    {
      accessor: 'titulo',
      title: 'Publicación',
      render: (c) => (
        <Stack gap={4}>
          <Group gap={6} wrap="nowrap">
            {estaFijada(c) && (
              <Tooltip label="Fijada: sale primero en el Portal">
                <IconPinned size={14} aria-label="Fijada" />
              </Tooltip>
            )}
            <Text size="sm" fw={600} lineClamp={2}>{c.titulo}</Text>
          </Group>
          <Group gap={6}>
            <StatusBadge>{c.tipo_etiqueta}</StatusBadge>
            {c.referencia && <Text size="xs" c="dimmed">{c.referencia}</Text>}
          </Group>
        </Stack>
      ),
    },
    {
      accessor: 'vigencia',
      title: 'Estado',
      width: 130,
      render: (c) => <StatusBadge tone={TONO_VIGENCIA[c.vigencia]}>{c.vigencia_etiqueta}</StatusBadge>,
    },
    {
      accessor: 'audiencia',
      title: 'Destinatarios',
      render: (c) => (
        <Stack gap={0}>
          <Text size="sm" lineClamp={1}>{resumenDestinatarios(c)}</Text>
          <Text size="xs" c="dimmed">{c.total_destinatarios ?? 0} hoy</Text>
        </Stack>
      ),
    },
    {
      accessor: 'publicado_desde',
      title: 'Vigencia',
      render: (c) => <Text size="sm">{rangoVigencia(c)}</Text>,
    },
    { accessor: 'leidos', title: 'Lecturas', width: 150, render: (c) => <Lecturas c={c} /> },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => (
        <TableActions
          actions={[
            { label: 'Ver', icon: <IconEye size={14} />, onClick: () => onVer(c) },
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => onEditar(c), hidden: !redacta, disabled: c.estado !== 'borrador' },
            { label: 'Duplicar', icon: <IconCopy size={14} />, onClick: () => onDuplicar(c), hidden: !redacta },
            {
              label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', onClick: () => onEliminar(c),
              hidden: !redacta, disabled: c.estado !== 'borrador',
            },
          ]}
        />
      ),
    },
  ]
}
