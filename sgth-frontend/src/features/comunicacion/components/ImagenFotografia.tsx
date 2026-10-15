'use client'

import { AspectRatio, Center, Image, Skeleton } from '@mantine/core'
import { IconPhotoOff } from '@tabler/icons-react'
import { useImagenFotografia } from '../hooks/useImagenFotografia'
import classes from './ImagenFotografia.module.css'

interface Props {
  fotografiaId: number
  /** Texto alternativo: «Foto de Ana Pérez, subida el 14/10/2026». */
  alt: string
  /** Ancho en píxeles; el alto sale de la proporción 3:4. */
  ancho: number
}

/** Una foto entera, en su proporción de credencial (3:4). */
export function ImagenFotografia({ fotografiaId, alt, ancho }: Props) {
  const { data: src, isLoading, isError } = useImagenFotografia(fotografiaId, 'completa')

  return (
    <AspectRatio ratio={3 / 4} w={ancho} className={classes.marco}>
      {isLoading ? (
        <Skeleton />
      ) : isError || !src ? (
        <Center aria-label="La foto no se pudo cargar">
          <IconPhotoOff size={24} color="var(--mantine-color-dimmed)" />
        </Center>
      ) : (
        <Image src={src} alt={alt} />
      )}
    </AspectRatio>
  )
}
