'use client'

import { useState } from 'react'
import {
  Modal, Stack, Group, Button, Text,
  FileInput,
} from '@mantine/core'
import { IconUpload, IconCheck } from '@tabler/icons-react'
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
    <Modal
      opened={opened}
      onClose={onClose}
      title="Subir documento de respaldo"
      size="sm"
      radius="xl"
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

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            disabled={!archivo}
            loading={subir.isPending}
            onClick={handleSubir}
          >
            Subir documento
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
