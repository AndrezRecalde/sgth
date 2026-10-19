import { Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconCheck, IconFileTypePdf, IconFolder, IconX } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFecha } from '@/lib/fecha'
import type { SolicitudCargaFamiliar } from '@/types/api'
import { PARENTESCO_LABELS } from '../constants/cargaFamiliar'

interface Acciones {
  onDocumento:  (s: SolicitudCargaFamiliar) => void
  onValidar:    (s: SolicitudCargaFamiliar) => void
  onRechazar:   (s: SolicitudCargaFamiliar) => void
  onExpediente: (s: SolicitudCargaFamiliar) => void
}

/** El familiar de la solicitud: lo propuesto, o la carga en una baja. */
export const familiarDe = (s: SolicitudCargaFamiliar) =>
  `${s.apellidos ?? s.carga?.apellidos ?? ''} ${s.nombres ?? s.carga?.nombres ?? ''}`.trim()

/**
 * La bandeja «Cargas por validar» (docs/diseno-cargas-familiares-portal.md,
 * 6.2): de quién es cada solicitud, sobre quién y lo que hay que saber antes
 * de validarla.
 */
export function getCargasPorValidarColumns(a: Acciones): DataTableColumn<SolicitudCargaFamiliar>[] {
  return [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: ({ servidor }) => (
        <div>
          <Text size="sm" fw={500}>
            {[servidor?.apellido, servidor?.segundo_apellido, servidor?.nombre].filter(Boolean).join(' ')}
          </Text>
          <Text size="xs" c="dimmed">
            {servidor?.cedula}{servidor?.unidad ? ` · ${servidor.unidad}` : ''}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'tipo',
      title: 'Pide',
      width: 150,
      render: (s) => (
        <div>
          <StatusBadge size="xs">{s.tipo_etiqueta}</StatusBadge>
          <Text size="xs" c="dimmed" mt={2}>{formatFecha(s.created_at)}</Text>
        </div>
      ),
    },
    {
      accessor: 'nombres',
      title: 'Familiar',
      render: (s) => {
        const parentesco = s.parentesco ?? s.carga?.parentesco
        return (
          <div>
            <Text size="sm">{familiarDe(s)}</Text>
            <Text size="xs" c="dimmed">
              {s.cedula ? `CI: ${s.cedula}` : 'Sin cédula'}
              {parentesco ? ` · ${PARENTESCO_LABELS[parentesco] ?? parentesco}` : ''}
            </Text>
            {s.tipo === 'baja' && s.motivo && (
              <Text size="xs" c="dimmed" lineClamp={2}>Motivo: {s.motivo}</Text>
            )}
          </div>
        )
      },
    },
    {
      accessor: 'avisos',
      title: 'Antes de validar',
      width: 280,
      render: ({ avisos, tiene_documento }) => (avisos?.length
        ? (
          <Stack gap={4}>
            {avisos.map((aviso) => (
              <Group key={aviso} gap={4} wrap="nowrap" align="flex-start">
                <Text c={SEMANTIC_COLOR.warning} lh={1} mt={2}><IconAlertTriangle size={12} /></Text>
                <Text size="xs" c={SEMANTIC_COLOR.warning}>{aviso}</Text>
              </Group>
            ))}
          </Stack>
        )
        : <Text size="xs" c="dimmed">{tiene_documento ? 'Revise el documento adjunto.' : 'Sin observaciones.'}</Text>),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => (
        <TableActions
          actions={[
            {
              label: 'Ver documento',
              icon: <IconFileTypePdf size={14} />,
              hidden: !s.tiene_documento,
              onClick: () => a.onDocumento(s),
            },
            { label: 'Validar', icon: <IconCheck size={14} />, onClick: () => a.onValidar(s) },
            { label: 'Rechazar', icon: <IconX size={14} />, color: 'red', onClick: () => a.onRechazar(s) },
            { label: 'Abrir expediente', icon: <IconFolder size={14} />, onClick: () => a.onExpediente(s) },
          ]}
        />
      ),
    },
  ]
}
