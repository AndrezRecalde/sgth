'use client'

import { Button, Group, Image, Stack, Text, Title, Typography } from '@mantine/core'
import { IconBuilding, IconDownload, IconFileTypePdf, IconPinned } from '@tabler/icons-react'
import { SectionHeading, StatusBadge } from '@/components/ui'
import type { Comunicado, ComunicadoAdjunto } from '../services/tipos'
import { estaFijada, fechaPublicacion, pesoArchivo } from '../utils/presentacion'
import classes from './ComunicadoContenido.module.css'

type Contenido = Pick<
  Comunicado,
  'tipo_etiqueta' | 'titulo' | 'cuerpo' | 'referencia' | 'fijado' | 'estado' | 'publicado_desde' | 'adjuntos'
>

/** «Publicado el…» solo si ya salió; un borrador o uno programado dicen cuándo saldrá. */
function cuando(c: Contenido): string {
  if (!c.publicado_desde) return 'Sin publicar'
  const fecha = fechaPublicacion(c.publicado_desde)
  const yaSalio = c.estado !== 'borrador' && new Date(c.publicado_desde) <= new Date()
  return yaSalio ? `Publicado el ${fecha}` : `Se publicará el ${fecha}`
}

interface Props {
  comunicado: Contenido
  /** La portada ya cargada (`data:` URL); sin ella no se dibuja el recuadro. */
  portadaSrc?: string | null
  onDescargar?: (adjunto: ComunicadoAdjunto) => void
  /** Lo que va al final: el aviso de confirmación en la vista previa, el botón en el Portal. */
  pie?: React.ReactNode
}

/**
 * Una noticia o un comunicado tal como lo lee el servidor
 * (docs/diseno-noticias-comunicados.md, 5.3): tipo, título, quién y cuándo,
 * portada, texto y documentos. Es el mismo en la vista previa de Talento
 * Humano y en el Portal, para que lo que se revisa sea lo que se publica.
 *
 * El texto llega limpio del backend (HtmlEnriquecido, al guardar): por eso se
 * pinta como HTML.
 */
export function ComunicadoContenido({ comunicado: c, portadaSrc, onDescargar, pie }: Props) {
  const adjuntos = c.adjuntos ?? []

  return (
    <Stack gap="lg">
      <Stack gap="xs">
        <Group gap={8}>
          <StatusBadge>{c.tipo_etiqueta}</StatusBadge>
          {estaFijada(c) && (
            <Group gap={4} c="dimmed">
              <IconPinned size={14} />
              <Text size="xs">Fijada</Text>
            </Group>
          )}
        </Group>
        <Title order={2} className={classes.titulo}>{c.titulo}</Title>
        <Group gap="md" c="dimmed">
          <Group gap={6}>
            <IconBuilding size={14} />
            <Text size="sm">Dirección de Talento Humano</Text>
          </Group>
          <Text size="sm">{cuando(c)}</Text>
          {c.referencia && <Text size="sm">{c.referencia}</Text>}
        </Group>
      </Stack>

      {portadaSrc && (
        <Image src={portadaSrc} alt={`Portada: ${c.titulo}`} className={classes.portada} />
      )}

      {c.cuerpo ? (
        <Typography className={classes.cuerpo} dangerouslySetInnerHTML={{ __html: c.cuerpo }} />
      ) : (
        <Text size="sm" c="dimmed">Todavía no tiene texto.</Text>
      )}

      {adjuntos.length > 0 && (
        <Stack gap="xs">
          <SectionHeading title="Documentos adjuntos" />
          {adjuntos.map((a) => (
            <Group key={a.id} gap="sm" wrap="nowrap" className={classes.adjunto}>
              <IconFileTypePdf size={20} />
              <Stack gap={0} className={classes.nombreAdjunto}>
                <Text size="sm" fw={500}>{a.nombre}</Text>
                <Text size="xs" c="dimmed">PDF · {pesoArchivo(a.tamano)}</Text>
              </Stack>
              {onDescargar && (
                <Button variant="light" size="xs" leftSection={<IconDownload size={14} />} onClick={() => onDescargar(a)}>
                  Descargar
                </Button>
              )}
            </Group>
          ))}
        </Stack>
      )}

      {pie}
    </Stack>
  )
}
