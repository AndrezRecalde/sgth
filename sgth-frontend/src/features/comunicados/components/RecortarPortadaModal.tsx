'use client'

import { useState } from 'react'
import { Button, Group, Input, Slider, Stack, Text } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconPhoto, IconUpload, IconX } from '@tabler/icons-react'
import Cropper, { type Area, type Point } from 'react-easy-crop'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { recortarImagen, recorteAlcanza } from '@/lib/recortarImagen'
import { useArchivosComunicado } from '../hooks/useArchivosComunicado'
import classes from './RecortarPortadaModal.module.css'

const TIPOS = ['image/jpeg', 'image/png', 'image/webp']
const PESO_MAXIMO_MB = 5
const PROPORCION = 16 / 9
/** Lo que exige el backend (SubirPortadaRequest); menos se vería pixelado en la tarjeta. */
const RECORTE_MINIMO = { ancho: 640, alto: 360 }
/** Más ancho no se nota en una tarjeta y solo hace más lenta la subida. */
const ANCHO_MAXIMO = 1600

interface Props {
  comunicadoId: number
  opened: boolean
  onClose: () => void
}

/**
 * La portada de una publicación (regla 6): se elige la imagen, se encuadra en
 * 16:9 —como sale en la tarjeta del Portal— y se sube ya recortada.
 */
export function RecortarPortadaModal({ comunicadoId, opened, onClose }: Props) {
  const { subirPortada } = useArchivosComunicado(comunicadoId)
  const [url, setUrl] = useState<string | null>(null)
  const [encuadre, setEncuadre] = useState<Point>({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [area, setArea] = useState<Area | null>(null)
  const [preparando, setPreparando] = useState(false)

  // La URL de la imagen se libera en el manejador y no en un efecto (regla 08).
  const soltar = () => {
    if (url) URL.revokeObjectURL(url)
    setUrl(null)
    setArea(null)
    setZoom(1)
    setEncuadre({ x: 0, y: 0 })
  }

  const cerrar = () => {
    soltar()
    onClose()
  }

  const guardar = async () => {
    if (!url || !area) return

    setPreparando(true)
    let portada: Blob
    try {
      portada = await recortarImagen(url, area, ANCHO_MAXIMO)
    } catch (error) {
      notificar.error('No se pudo preparar la portada', error instanceof Error ? error.message : undefined)
      return
    } finally {
      setPreparando(false)
    }

    subirPortada.mutate(portada, { onSuccess: cerrar })
  }

  const alcanza = recorteAlcanza(area, RECORTE_MINIMO)

  return (
    <SgthModal opened={opened} onClose={cerrar} title="Portada de la publicación" size="lg">
      <Stack gap="md">
        {!url ? (
          <Dropzone
            onDrop={(archivos) => { soltar(); if (archivos[0]) setUrl(URL.createObjectURL(archivos[0])) }}
            onReject={() => notificar.error('La imagen no sirve', `Se acepta JPG, PNG o WebP de hasta ${PESO_MAXIMO_MB} MB.`)}
            accept={TIPOS}
            maxSize={PESO_MAXIMO_MB * 1024 * 1024}
            multiple={false}
          >
            <Group justify="center" gap="xl" mih={160}>
              <Dropzone.Accept><IconUpload size={32} color="var(--sgth-accent)" /></Dropzone.Accept>
              <Dropzone.Reject><IconX size={32} color="var(--mantine-color-red-6)" /></Dropzone.Reject>
              <Dropzone.Idle><IconPhoto size={32} color="var(--mantine-color-dimmed)" /></Dropzone.Idle>
              <div>
                <Text size="sm" fw={500}>Arrastre la imagen aquí o haga clic para elegirla</Text>
                <Text size="xs" c="dimmed" mt={4}>JPG, PNG o WebP · hasta {PESO_MAXIMO_MB} MB · apaisada</Text>
              </div>
            </Group>
          </Dropzone>
        ) : (
          <>
            <div className={classes.lienzo}>
              <Cropper
                image={url}
                crop={encuadre}
                zoom={zoom}
                aspect={PROPORCION}
                onCropChange={setEncuadre}
                onZoomChange={setZoom}
                onCropComplete={(_, pixeles) => setArea(pixeles)}
                showGrid
              />
            </div>
            <Input.Wrapper label="Acercar">
              <Slider value={zoom} onChange={setZoom} min={1} max={4} step={0.05} label={null} />
            </Input.Wrapper>
            {area && !alcanza && (
              <Text size="sm" c="red">
                El recorte mide {Math.round(area.width)} × {Math.round(area.height)} píxeles y se necesitan al menos{' '}
                {RECORTE_MINIMO.ancho} × {RECORTE_MINIMO.alto}. Acerque menos la imagen o elija una más grande.
              </Text>
            )}
          </>
        )}
      </Stack>

      <ModalFooter
        onCancel={cerrar}
        leftSection={url && <Button variant="default" onClick={soltar}>Elegir otra</Button>}
        onSubmit={guardar}
        submitLabel="Guardar portada"
        submitting={preparando || subirPortada.isPending}
        submitDisabled={!url || !alcanza}
      />
    </SgthModal>
  )
}
