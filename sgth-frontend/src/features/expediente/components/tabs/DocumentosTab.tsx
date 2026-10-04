'use client'

import { Alert, Button, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconAlertTriangle, IconPaperclip, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { useDocumentos } from '../../hooks/useDocumentos'
import { useDocumentoMutations } from '../../hooks/useDocumentoMutations'
import { documentoService } from '../../services/documentoService'
import { getDocumentosColumns } from '../documentos.columns'
import { documentosQueFaltan } from '../../utils/documentosBasicos'
import { CertificadosEmitidosPanel } from '../CertificadosEmitidosPanel'
import { DocumentoModal } from '../DocumentoModal'
import type { DocumentoServidor } from '@/types/api'

interface Props { servidorId: number }

export function DocumentosTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: documentos = [], isLoading, error } = useDocumentos(servidorId)
  const { eliminar } = useDocumentoMutations(servidorId)

  const descargar = async (doc: DocumentoServidor) => {
    try {
      guardarArchivo(
        await documentoService.descargar(servidorId, doc.id),
        doc.nombre_archivo,
      )
    } catch (e) {
      // El interceptor de axios solo atiende el 401: sin esto, un fallo
      // (archivo borrado del disco, sin permiso) no decía nada.
      notificar.error('No se pudo descargar el documento', getApiErrorMessage(e))
    }
  }

  const columns = getDocumentosColumns({
    onDescargar: descargar,
    onDelete: (id) => eliminar.mutate(id),
  })

  const faltan = documentosQueFaltan(documentos)

  return (
    <Stack gap="md">
      <SectionCard
        title="Documentos del expediente"
        actions={
          <Button size="xs" variant="light"
            leftSection={<IconPlus size={14} />} onClick={open}>
            Subir documento
          </Button>
        }
      >
        {/* Qué falta por anexar: revisarlo a ojo obligaba a conocer de memoria
            la lista de documentos básicos del expediente. */}
        {/* Con la consulta fallida la lista viene vacía: avisaba que faltaban
            los cuatro, encima del error. */}
        {!isLoading && !error && faltan.length > 0 && (
          <Alert
            variant="light"
            color="amber"
            icon={<IconAlertTriangle size={16} />}
            title={`Faltan ${faltan.length} de los documentos básicos`}
            mb="md"
          >
            Sin anexar: {faltan.join(', ')}.
          </Alert>
        )}

        <DataState
          loading={isLoading}
          error={error}
          empty={documentos.length === 0}
          skeletonRows={3}
          emptyProps={{
            icon: IconPaperclip,
            title: 'Sin documentos',
            description: 'Sube los documentos del expediente del servidor.',
          }}
        >
          <SgthTable records={documentos} columns={columns} minHeight={100} />
        </DataState>
      </SectionCard>

      <CertificadosEmitidosPanel servidorId={servidorId} />

      <DocumentoModal opened={opened} onClose={close} servidorId={servidorId} />
    </Stack>
  )
}
