'use client'

import { Center, Image, Skeleton } from '@mantine/core'
import { IconPhotoOff } from '@tabler/icons-react'
import { useArchivoVersion } from '../hooks/useIdentidad'
import classes from './VistaVersion.module.css'

interface Props {
  versionId: number | null | undefined
  alt: string
  /** Alto de la imagen en píxeles. */
  alto: number
  /** El negativo se ve sobre oscuro; los demás, sobre papel claro. */
  oscuro?: boolean
  /** Atenuada: se muestra el respaldo, no una versión propia. */
  atenuada?: boolean
}

/**
 * Una versión de un logo, sobre el fondo donde se usa. El papel es claro en los
 * dos modos de la pantalla: los logos se diseñan para el documento impreso.
 */
export function VistaVersion({ versionId, alt, alto, oscuro, atenuada }: Props) {
  const { data: src, isLoading } = useArchivoVersion(versionId)

  return (
    <Center h={alto + 24} bg={oscuro ? 'dark.7' : 'slate.0'} className={classes.marco} opacity={atenuada ? 0.55 : 1}>
      {isLoading ? (
        <Skeleton h={alto} w="60%" />
      ) : src ? (
        <Image src={src} alt={alt} h={alto} w="auto" fit="contain" />
      ) : (
        <IconPhotoOff size={22} color="var(--mantine-color-dimmed)" aria-label="La imagen no se pudo cargar" />
      )}
    </Center>
  )
}
