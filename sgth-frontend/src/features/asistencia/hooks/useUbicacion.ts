import { useCallback, useEffect, useState } from 'react'

export interface Coordenadas {
  lat: number
  lon: number
  /** Radio de incertidumbre en metros, según el dispositivo. */
  precision: number
}

export type EstadoUbicacion = 'buscando' | 'lista' | 'denegada' | 'error' | 'no-disponible'

// Sin límite, `getCurrentPosition` puede no responder nunca en algunos
// teléfonos y la pantalla se quedaba en «Ubicando...».
const TIEMPO_LIMITE_MS = 15_000

/**
 * La ubicación del dispositivo para la marcación en línea.
 *
 * Antes se pedía una sola vez al abrir la página y se reenviaba esa misma en
 * cada marcación, aunque hubieran pasado horas: la salida podía guardarse con
 * la ubicación de la mañana. Ahora `actualizar()` la pide de nuevo, sin caché
 * (`maximumAge: 0`), y es lo que se usa al pulsar. La lectura al abrir solo
 * sirve para mostrar si el GPS está disponible antes de intentarlo.
 *
 * Los `setState` van en los callbacks del navegador, nunca en el cuerpo del
 * efecto (regla 08).
 */
export function useUbicacion(activa: boolean) {
  const [estado, setEstado] = useState<EstadoUbicacion>('buscando')
  const [coordenadas, setCoordenadas] = useState<Coordenadas | null>(null)

  const actualizar = useCallback(
    () =>
      new Promise<Coordenadas>((resolver, rechazar) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
          // Asíncrono a propósito: así tampoco cambia el estado dentro del efecto.
          queueMicrotask(() => setEstado('no-disponible'))
          rechazar(new Error('Este navegador no permite obtener la ubicación.'))
          return
        }

        setEstado('buscando')
        navigator.geolocation.getCurrentPosition(
          (posicion) => {
            const leida = {
              lat: posicion.coords.latitude,
              lon: posicion.coords.longitude,
              precision: Math.round(posicion.coords.accuracy),
            }
            setCoordenadas(leida)
            setEstado('lista')
            resolver(leida)
          },
          (error) => {
            const denegada = error.code === error.PERMISSION_DENIED
            setEstado(denegada ? 'denegada' : 'error')
            rechazar(new Error(
              denegada
                ? 'El navegador no tiene permiso para usar la ubicación.'
                : 'No se pudo obtener la ubicación. Verifique que el GPS esté activo.',
            ))
          },
          { enableHighAccuracy: true, timeout: TIEMPO_LIMITE_MS, maximumAge: 0 },
        )
      }),
    [],
  )

  useEffect(() => {
    if (!activa) return
    // El rechazo ya quedó reflejado en `estado`: aquí no hay nada que avisar.
    actualizar().catch(() => {})
  }, [activa, actualizar])

  return { estado, coordenadas, actualizar }
}
