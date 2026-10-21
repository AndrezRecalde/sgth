import { useCallback, useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { borradorService } from '../services/borradorService'

/** Lo que se espera a que el médico deje de escribir antes de guardar. */
const ESPERA_MS = 2_500

export type EstadoBorrador = 'inactivo' | 'guardando' | 'guardado' | 'error'

/**
 * El guardado que sigue viajando, por turno. Vive fuera del componente porque
 * el que importa es el que se manda al cerrar la ficha: si se vuelve a abrir
 * antes de que llegue, la lectura lo espera en vez de traer la versión de
 * antes.
 */
const enVuelo = new Map<number, Promise<unknown>>()

/**
 * Guarda solo lo que se lleva escrito, sin que nadie tenga que acordarse.
 *
 * No usa `useMutation` porque no es una acción del médico: es un latido que se
 * dispara solo, del que solo interesa el último intento. Si mientras se guarda
 * llega otro cambio, el siguiente reemplaza al anterior; y al desmontar el
 * componente se guarda lo que quedara pendiente, que es justo cuando se navega
 * a otra pantalla y hasta ahora se perdía todo.
 */
export function useBorradorConsulta(agendaMedicaId: number, activo: boolean) {
  const qc = useQueryClient()
  const [estado, setEstado] = useState<EstadoBorrador>('inactivo')
  const [guardadoEn, setGuardadoEn] = useState<Date | null>(null)

  const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null)
  const pendiente    = useRef<Record<string, unknown> | null>(null)

  const { data: borrador, isLoading } = useQuery({
    queryKey: ['consulta-borrador', agendaMedicaId],
    queryFn:  async () => {
      await enVuelo.get(agendaMedicaId)?.catch(() => undefined)
      return borradorService.obtener(agendaMedicaId)
    },
    enabled:  activo && !!agendaMedicaId,
    staleTime: Infinity,
    // Recuperar lo escrito es cosa de la primera carga: refrescarlo al volver a
    // la pestaña pisaría con una copia vieja lo que se está escribiendo ahora.
    refetchOnWindowFocus: false,
    // Pero cada vez que se abre la ficha, del servidor y no de la caché. Lo que
    // se escribe se guarda allí y la caché no se entera: con la retención de
    // cinco minutos por defecto, volver a la cola y entrar otra vez al rato
    // recuperaba la foto de la primera carga —un vacío, o un borrador ya
    // viejo— y el guardado automático la escribía encima del bueno. Sin
    // retención, al cerrar la ficha se suelta y la siguiente apertura espera
    // al servidor antes de recuperar nada.
    gcTime: 0,
  })

  const enviar = useCallback(async () => {
    const contenido = pendiente.current
    if (!contenido) return

    pendiente.current = null
    setEstado('guardando')

    const guardado = borradorService.guardar(agendaMedicaId, contenido)
    enVuelo.set(agendaMedicaId, guardado)

    try {
      await guardado
      setEstado('guardado')
      setGuardadoEn(new Date())
    } catch {
      // Sin aviso emergente: es un guardado de fondo y el médico está
      // escribiendo. El estado se ve en la línea de la cabecera.
      setEstado('error')
    } finally {
      if (enVuelo.get(agendaMedicaId) === guardado) enVuelo.delete(agendaMedicaId)
    }
  }, [agendaMedicaId])

  const anotar = useCallback((contenido: Record<string, unknown>) => {
    if (!activo || !agendaMedicaId) return

    pendiente.current = contenido

    if (temporizador.current) clearTimeout(temporizador.current)
    temporizador.current = setTimeout(() => { void enviar() }, ESPERA_MS)
  }, [activo, agendaMedicaId, enviar])

  const descartar = useCallback(async () => {
    if (temporizador.current) clearTimeout(temporizador.current)
    pendiente.current = null

    // Un guardado que siga viajando llegaría después del borrado y volvería a
    // crear el borrador: se espera a que llegue y se borra después.
    await enVuelo.get(agendaMedicaId)?.catch(() => undefined)
    await borradorService.descartar(agendaMedicaId)

    qc.setQueryData(['consulta-borrador', agendaMedicaId], null)
    setEstado('inactivo')
    setGuardadoEn(null)
  }, [agendaMedicaId, qc])

  /** Lo llama el guardado de la consulta: el borrador ya no hace falta. */
  const olvidar = useCallback(() => {
    if (temporizador.current) clearTimeout(temporizador.current)
    pendiente.current = null
    qc.setQueryData(['consulta-borrador', agendaMedicaId], null)
    setEstado('inactivo')
  }, [agendaMedicaId, qc])

  useEffect(() => {
    return () => {
      if (temporizador.current) clearTimeout(temporizador.current)
      // Al salir de la pantalla se manda lo que quedara sin guardar.
      if (pendiente.current) void enviar()
    }
  }, [enviar])

  return {
    borrador,
    cargando: isLoading,
    estado,
    guardadoEn,
    anotar,
    descartar,
    olvidar,
  }
}
