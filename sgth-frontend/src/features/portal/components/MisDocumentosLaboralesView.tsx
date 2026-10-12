'use client'

import { useState } from 'react'
import { Alert, Text } from '@mantine/core'
import { IconFileCertificate, IconFileText, IconInfoCircle, IconSignature } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, PageHeader, PageShell, SgthTable, StatusBadge, TableActions, confirmar, notificar,
  type TableAction,
} from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { formatFecha, formatFechaHora } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useAcusarRecibo, useMisDocumentos } from '../hooks/useMisDocumentos'
import { misDocumentosService, type MiDocumentoLaboral } from '../services/misDocumentosService'

/**
 * «Mis documentos laborales» (diseño, 9.13; TH 26; fase 6.3a): las acciones de
 * personal que se le notificaron —y las que se anularon después—, sus hojas de
 * registro interno y constancias. Descarga el documento y acusa recibo de lo
 * que se le notificó por el portal.
 */
export function MisDocumentosLaboralesView() {
  const { data, isLoading, error } = useMisDocumentos()
  const acusar = useAcusarRecibo()
  const [abriendo, setAbriendo] = useState<number | null>(null)
  const documentos = data ?? []
  const pendientes = documentos.filter((d) => d.puede_acusar).length

  const verDocumento = async (d: MiDocumentoLaboral) => {
    setAbriendo(d.id)
    try {
      const blob = await misDocumentosService.pdf(d.id)
      if (!abrirOGuardarArchivo(blob, `${d.codigo_registro}.pdf`)) {
        notificar.aviso('El navegador bloqueó la pestaña', 'El documento se descargó.')
      }
    } catch (e) {
      notificar.error('No se pudo abrir el documento', getApiErrorMessage(e))
    } finally {
      setAbriendo(null)
    }
  }

  const acusarRecibo = (d: MiDocumentoLaboral) => confirmar({
    title: 'Acusar recibo',
    message: `Deja constancia de que recibió el documento ${d.codigo_registro}, con la fecha y la hora de hoy. `
      + 'No se deshace. Acusar recibo no significa estar de acuerdo con su contenido.',
    confirmLabel: 'Acusar recibo',
    onConfirm: () => acusar.mutate(d.id),
  })

  const accionesDe = (d: MiDocumentoLaboral): TableAction[] => [
    { label: abriendo === d.id ? 'Abriendo…' : 'Ver documento', icon: <IconFileText size={14} />, onClick: () => verDocumento(d), disabled: abriendo === d.id },
    ...(d.puede_acusar
      ? [{ label: 'Acusar recibo', icon: <IconSignature size={14} />, onClick: () => acusarRecibo(d) }]
      : []),
  ]

  const columnas: DataTableColumn<MiDocumentoLaboral>[] = [
    {
      accessor: 'accion',
      title: 'Documento',
      render: (d) => (
        <div>
          <Text size="sm" fw={500}>{d.accion}{d.causal ? ` — ${d.causal}` : ''}</Text>
          <Text size="xs" c="dimmed">{d.codigo_registro} · {d.instrumento_etiqueta}</Text>
        </div>
      ),
    },
    { accessor: 'fecha_efectiva', title: 'Rige desde', width: 120, render: (d) => formatFecha(d.fecha_efectiva) },
    {
      accessor: 'fecha_notificacion',
      title: 'Notificación',
      width: 170,
      render: (d) => (d.anulada
        ? <StatusBadge tone="danger">Anulada</StatusBadge>
        : d.fecha_notificacion
          ? (
            <div>
              <Text size="sm">{formatFecha(d.fecha_notificacion)}</Text>
              <Text size="xs" c="dimmed">{d.notificacion_medio === 'personal' ? 'En persona' : 'Por el portal'}</Text>
            </div>
          )
          : <Text size="xs" c="dimmed">No se notifica</Text>),
    },
    {
      accessor: 'acuse_en',
      title: 'Acuse de recibo',
      width: 170,
      render: (d) => (d.acuse_en
        ? <StatusBadge tone="success">{formatFechaHora(d.acuse_en)}</StatusBadge>
        : d.puede_acusar
          ? <StatusBadge tone="warning">Pendiente</StatusBadge>
          : <Text size="xs" c="dimmed">—</Text>),
    },
    { accessor: 'acciones', title: '', width: 50, render: (d) => <TableActions actions={accionesDe(d)} /> },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Mis documentos laborales"
        description="Las acciones de personal que se le notificaron, sus registros internos y constancias"
      />

      {pendientes > 0 && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />}>
          {pendientes === 1
            ? 'Tiene un documento notificado que espera su acuse de recibo.'
            : `Tiene ${pendientes} documentos notificados que esperan su acuse de recibo.`}
        </Alert>
      )}

      <DataState
        loading={isLoading}
        error={error}
        empty={!documentos.length}
        emptyProps={{
          icon: IconFileCertificate,
          title: 'Todavía no tiene documentos laborales',
          description: 'Las acciones de personal aparecen aquí cuando Talento Humano se las notifica.',
        }}
      >
        <SgthTable records={documentos} columns={columnas} minHeight={160} />
      </DataState>
    </PageShell>
  )
}
