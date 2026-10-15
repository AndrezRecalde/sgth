'use client'

import { useState } from 'react'
import { Button, Group, Input, Slider, Stack, Text } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconPhoto, IconUpload, IconX } from '@tabler/icons-react'
import Cropper, { type Area, type Point } from 'react-easy-crop'
import { ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useMutacionesFotografia } from '../hooks/useFotografias'
import type { ServidorConFotografia } from '../services/tipos'
import { PROPORCION, RECORTE_MINIMO, recortarFoto, recorteSuficiente } from '../utils/recortarFoto'
import classes from './SubirFotografiaModal.module.css'

const TIPOS = ['image/jpeg', 'image/png', 'image/webp']
const PESO_MAXIMO_MB = 10

interface Props {
  servidor: ServidorConFotografia | null
  onClose: () => void
}

/**
 * Subir la foto de un servidor (fase 1.2): se elige la imagen, se encuadra en
 * 3:4 —la cara centrada— y se sube ya recortada. Si tenía foto, la anterior
 * queda en el historial.
 */
export function SubirFotografiaModal({ servidor, onClose }: Props) {
  const { subir } = useMutacionesFotografia()
  const [url, setUrl] = useState<string | null>(null)
  const [encuadre, setEncuadre] = useState<Point>({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [area, setArea] = useState<Area | null>(null)
  const [preparando, setPreparando] = useState(false)

  // La URL de la imagen elegida se libera al cambiarla o al cerrar, en el
  // manejador y no en un efecto (regla 08).
  const soltar = () => {
    if (url) URL.revokeObjectURL(url)
    setUrl(null)
    setArea(null)
    setZoom(1)
    setEncuadre({ x: 0, y: 0 })
  }

  const elegir = (archivo: File | undefined) => {
    if (!archivo) return
    soltar()
    setUrl(URL.createObjectURL(archivo))
  }

  const cerrar = () => {
    soltar()
    onClose()
  }

  const guardar = async () => {
    if (!servidor || !url || !area) return

    setPreparando(true)
    let foto: Blob
    try {
      foto = await recortarFoto(url, area)
    } catch (error) {
      notificar.error('No se pudo preparar la foto', error instanceof Error ? error.message : undefined)
      return
    } finally {
      setPreparando(false)
    }

    // Si el backend la rechaza, lo avisa la mutación y el modal sigue abierto.
    subir.mutate({ servidorId: servidor.id, foto }, { onSuccess: cerrar })
  }

  const suficiente = recorteSuficiente(area)

  return (
    <SgthModal opened={!!servidor} onClose={cerrar} title={servidor?.fotografia ? 'Reemplazar la foto' : 'Subir la foto'} size="lg">
      <Stack gap="md">
        <Text size="sm" c="dimmed">{servidor?.nombre_completo} · {servidor?.cedula}</Text>

        {!url ? (
          <Dropzone
            onDrop={(archivos) => elegir(archivos[0])}
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
                <Text size="sm" fw={500}>Arrastre la foto aquí o haga clic para elegirla</Text>
                <Text size="xs" c="dimmed" mt={4}>JPG, PNG o WebP · hasta {PESO_MAXIMO_MB} MB · de frente y con fondo claro</Text>
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
            {area && !suficiente && (
              <Text size="sm" c="red">
                El recorte mide {Math.round(area.width)} × {Math.round(area.height)} píxeles y se necesitan al menos{' '}
                {RECORTE_MINIMO.ancho} × {RECORTE_MINIMO.alto}. Acerque menos la imagen o elija una foto más grande.
              </Text>
            )}
          </>
        )}
      </Stack>

      <ModalFooter
        onCancel={cerrar}
        leftSection={url && <Button variant="default" onClick={soltar}>Elegir otra</Button>}
        onSubmit={guardar}
        submitLabel="Guardar foto"
        submitting={preparando || subir.isPending}
        submitDisabled={!url || !suficiente}
      />
    </SgthModal>
  )
}
