'use client'

import { ActionIcon, Button, FileButton, Group, Stack, Text, Tooltip } from '@mantine/core'
import { IconDownload, IconFileTypePdf, IconPaperclip, IconTrash } from '@tabler/icons-react'
import { confirmar, notificar, SectionCard } from '@/components/ui'
import { MAXIMO_ADJUNTOS, PESO_MAXIMO_ADJUNTO_MB } from '../constants/comunicados'
import { useArchivosComunicado } from '../hooks/useArchivosComunicado'
import type { Comunicado } from '../services/tipos'
import { pesoArchivo } from '../utils/presentacion'
import classes from './ComunicadoContenido.module.css'

interface Props {
  comunicado: Comunicado
}

/**
 * Los PDF de un borrador: la circular firmada, un formato, un cronograma
 * (regla 6). Hasta cinco, de hasta 10 MB; el backend mira el contenido, no la
 * extensión.
 */
export function AdjuntosComunicado({ comunicado }: Props) {
  const { agregarAdjunto, quitarAdjunto, descargarAdjunto } = useArchivosComunicado(comunicado.id)
  const adjuntos = comunicado.adjuntos ?? []
  const lleno = adjuntos.length >= MAXIMO_ADJUNTOS

  const agregar = (archivo: File | null) => {
    if (!archivo) return
    if (archivo.size > PESO_MAXIMO_ADJUNTO_MB * 1024 * 1024) {
      notificar.error('El documento pesa demasiado', `Se aceptan PDF de hasta ${PESO_MAXIMO_ADJUNTO_MB} MB.`)
      return
    }
    agregarAdjunto.mutate(archivo)
  }

  return (
    <SectionCard
      title="Documentos adjuntos"
      description={`Opcional. PDF de hasta ${PESO_MAXIMO_ADJUNTO_MB} MB · ${adjuntos.length} de ${MAXIMO_ADJUNTOS}`}
      actions={
        <FileButton onChange={agregar} accept="application/pdf" disabled={lleno}>
          {(props) => (
            <Button {...props} variant="light" leftSection={<IconPaperclip size={16} />} loading={agregarAdjunto.isPending} disabled={lleno}>
              Agregar PDF
            </Button>
          )}
        </FileButton>
      }
    >
      {adjuntos.length === 0 ? (
        <Text size="sm" c="dimmed">Sin documentos.</Text>
      ) : (
        <Stack gap="xs">
          {adjuntos.map((a) => (
            <Group key={a.id} gap="sm" wrap="nowrap" className={classes.adjunto}>
              <IconFileTypePdf size={20} />
              <Stack gap={0} className={classes.nombreAdjunto}>
                <Text size="sm" fw={500}>{a.nombre}</Text>
                <Text size="xs" c="dimmed">PDF · {pesoArchivo(a.tamano)}</Text>
              </Stack>
              <Tooltip label="Descargar">
                <ActionIcon variant="subtle" aria-label={`Descargar ${a.nombre}`} onClick={() => descargarAdjunto.mutate(a)}>
                  <IconDownload size={16} />
                </ActionIcon>
              </Tooltip>
              <Tooltip label="Quitar">
                <ActionIcon
                  variant="subtle"
                  color="red"
                  aria-label={`Quitar ${a.nombre}`}
                  onClick={() => confirmar({
                    title: 'Quitar el documento',
                    message: <>Se quitará <b>{a.nombre}</b> de la publicación.</>,
                    confirmLabel: 'Quitar',
                    destructiva: true,
                    onConfirm: () => quitarAdjunto.mutate(a),
                  })}
                >
                  <IconTrash size={16} />
                </ActionIcon>
              </Tooltip>
            </Group>
          ))}
        </Stack>
      )}
    </SectionCard>
  )
}
