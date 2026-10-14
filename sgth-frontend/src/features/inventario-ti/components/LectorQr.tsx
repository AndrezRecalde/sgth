'use client'

import { useEffect, useRef, useState } from 'react'
import { Alert, Box, Text } from '@mantine/core'
import { IconCameraOff } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'

type Estado = 'iniciando' | 'activo' | 'sin_camara' | 'sin_permiso'

/** El mismo código leído dos veces seguidas no se manda de nuevo hasta pasado este tiempo. */
const PAUSA_MISMO_CODIGO_MS = 3000

interface Props {
  /** Lo que dice la etiqueta: la URL de la ficha del equipo. */
  onLeer: (texto: string) => void
}

/**
 * La cámara del celular leyendo las etiquetas QR del inventario (5.2), con
 * `qr-scanner`: usa el lector nativo del navegador cuando lo hay y, si no, el
 * suyo. La cámara solo se abre en HTTPS o en localhost; sin ella queda el
 * código escrito a mano.
 */
export function LectorQr({ onLeer }: Props) {
  const video = useRef<HTMLVideoElement>(null)
  const ultimo = useRef<{ texto: string; en: number } | null>(null)
  const alLeer = useRef(onLeer)
  const [estado, setEstado] = useState<Estado>('iniciando')
  const seguro = typeof window === 'undefined' || window.isSecureContext

  useEffect(() => {
    alLeer.current = onLeer
  }, [onLeer])

  useEffect(() => {
    if (!seguro || !video.current) return
    let lector: { start: () => Promise<void>; destroy: () => void } | null = null
    let cancelado = false
    const elemento = video.current

    import('qr-scanner').then(async ({ default: QrScanner }) => {
      if (cancelado) return
      if (!(await QrScanner.hasCamera())) {
        setEstado('sin_camara')
        return
      }
      lector = new QrScanner(elemento, (r) => {
        const ahora = Date.now()
        if (ultimo.current?.texto === r.data && ahora - ultimo.current.en < PAUSA_MISMO_CODIGO_MS) return
        ultimo.current = { texto: r.data, en: ahora }
        alLeer.current(r.data)
      }, { preferredCamera: 'environment', highlightScanRegion: true, maxScansPerSecond: 5, returnDetailedScanResult: true })
      lector.start().then(() => setEstado('activo')).catch(() => setEstado('sin_permiso'))
    })

    return () => {
      cancelado = true
      lector?.destroy()
    }
  }, [seguro])

  if (!seguro) {
    return (
      <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconCameraOff size={16} />}>
        La cámara solo funciona con el sistema en HTTPS. Escriba el código de la etiqueta.
      </Alert>
    )
  }

  return (
    <Box>
      {estado === 'sin_camara' && (
        <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconCameraOff size={16} />}>
          Este dispositivo no tiene cámara. Escriba el código de la etiqueta.
        </Alert>
      )}
      {estado === 'sin_permiso' && (
        <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconCameraOff size={16} />}>
          No se pudo abrir la cámara: permita su uso en el navegador, o escriba el código.
        </Alert>
      )}
      <Box
        component="video"
        ref={video}
        muted
        playsInline
        w="100%"
        mah={360}
        style={{ borderRadius: 'var(--mantine-radius-md)', background: 'var(--sgth-surface-sunken)', display: estado === 'sin_camara' || estado === 'sin_permiso' ? 'none' : 'block', objectFit: 'cover' }}
      />
      {estado === 'iniciando' && <Text size="xs" c="dimmed" mt={4}>Abriendo la cámara…</Text>}
      {estado === 'activo' && <Text size="xs" c="dimmed" mt={4}>Apunte a la etiqueta QR del equipo.</Text>}
    </Box>
  )
}
