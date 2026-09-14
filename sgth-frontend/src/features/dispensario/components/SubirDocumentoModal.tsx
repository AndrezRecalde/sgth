'use client'

import { useState } from 'react'
import {
  Stack, Text,
  FileInput,
} from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { IconUpload } from '@tabler/icons-react'
import { useSubirDocumentoAdquisicion } from '../hooks/useAdquisicion'
import type { Adquisicion } from '../services/adquisicionService'

interface Props {
  opened:       boolean
  onClose:      () => void
  adquisicion:  Adquisicion | null
}

export function SubirDocumentoModal({
  opened, onClose, adquisicion,
}: Props) {
  const [archivo, setArchivo] = useState<File | null>(null)
  const subir = useSubirDocumentoAdquisicion()

  const handleSubir = () => {
    if (!adquisicion || !archivo) return
    subir.mutate(
      { id: adquisicion.id, archivo },
      { onSuccess: () => { setArchivo(null); onClose() } }
    )
  }

  if (!adquisicion) return null

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Subir documento de respaldo"
      size="sm"
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          Adjunta la factura, contrato o acta de donación
          correspondiente al folio{' '}
          <Text span fw={600}>{adquisicion.folio}</Text>
        </Text>

        <FileInput
          label="Documento (PDF o imagen)"
          placeholder="Seleccionar archivo"
          leftSection={<IconUpload size={14} />}
          accept="application/pdf,image/png,image/jpeg"
          value={archivo}
          onChange={setArchivo}
        />
      </Stack>
      <ModalFooter
        onCancel={onClose}
        submitLabel="Subir documento"
        submitting={subir.isPending}
        submitDisabled={!archivo}
        onSubmit={handleSubir}
      />
    </SgthModal>
  )
}
