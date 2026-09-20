import { Text } from '@mantine/core'
import { IconDownload, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { DocumentoServidor } from '@/types/api'

export const TIPO_DOCUMENTO_LABELS: Record<string, string> = {
  cedula_identidad: 'Cédula de identidad',
  papeleta_votacion: 'Papeleta de votación',
  carnet_conadis: 'Carnet CONADIS',
  titulo_tercer_nivel: 'Título de tercer nivel',
  titulo_cuarto_nivel: 'Título de cuarto nivel',
  certificado_trabajo_anterior: 'Certificado trabajo anterior',
  contrato_laboral: 'Contrato laboral',
  nombramiento: 'Nombramiento',
  certificado_medico: 'Certificado médico',
  certificado_enfermedad_catastrofica: 'Certificado enfermedad catastrófica',
  otro: 'Otro documento',
}

function pesoLegible(bytes?: number): string {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/** Un vencimiento importa cuando se acerca; el de 2031 no dice nada hoy. */
const POR_VENCER_EN_DIAS = 60

function toneVencimiento(fecha: string): 'danger' | 'warning' | undefined {
  const dias = Math.ceil(
    (new Date(fecha).getTime() - Date.now()) / (1000 * 60 * 60 * 24),
  )
  if (dias < 0) return 'danger'
  if (dias <= POR_VENCER_EN_DIAS) return 'warning'
  return undefined
}

type Handlers = {
  onDescargar: (documento: DocumentoServidor) => void
  onDelete: (id: number) => void
}

export const getDocumentosColumns = (
  { onDescargar, onDelete }: Handlers,
): DataTableColumn<DocumentoServidor>[] => [
  {
    accessor: 'tipo_documento',
    title: 'Documento',
    render: (doc) => (
      <div>
        <Text size="sm" fw={500}>
          {TIPO_DOCUMENTO_LABELS[doc.tipo_documento] ?? doc.tipo_documento}
        </Text>
        <Text size="xs" c="dimmed">
          {doc.nombre_archivo} · {pesoLegible(doc.tamanio_bytes)}
        </Text>
      </div>
    ),
  },
  {
    accessor: 'created_at',
    title: 'Subido',
    width: 160,
    render: (doc) => (
      <div>
        <Text size="sm">{formatFecha(doc.created_at?.slice(0, 10))}</Text>
        {doc.subido_por?.usuario_ti && (
          <Text size="xs" c="dimmed">{doc.subido_por.usuario_ti}</Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'fecha_vencimiento',
    title: 'Vence',
    width: 130,
    render: (doc) => {
      if (!doc.fecha_vencimiento) return <Text size="sm" c="dimmed">—</Text>
      const tone = toneVencimiento(doc.fecha_vencimiento)
      const texto = formatFecha(doc.fecha_vencimiento)
      return tone
        ? <StatusBadge tone={tone} size="xs">{texto}</StatusBadge>
        : <Text size="sm">{texto}</Text>
    },
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (doc) => (
      <TableActions actions={[
        {
          label: 'Descargar',
          icon: <IconDownload size={14} />,
          onClick: () => onDescargar(doc),
        },
        {
          label: 'Eliminar',
          icon: <IconTrash size={14} />,
          color: 'red',
          onClick: () => confirmar({
            title: 'Eliminar documento',
            message: <>Se eliminará el documento <b>{doc.nombre_archivo}</b>. No se puede deshacer.</>,
            destructiva: true,
            onConfirm: () => onDelete(doc.id),
          }),
        },
      ]} />
    ),
  },
]
