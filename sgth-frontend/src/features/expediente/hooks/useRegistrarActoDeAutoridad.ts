import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { getApiErrorMessage } from '@/types/api'
import { movimientoService, type ActoDeAutoridadData } from '../services/movimientoService'

/** Lo que deja desactualizado registrar un acto de autoridad. */
const CLAVES_DEL_ACTO = [['autoridades-electas'], ['movimientos'], ['bandeja-movimientos']]

/**
 * Registrar un acto de una autoridad electa desde su pestaña (fase 4.1).
 *
 * Los tres —el inicio del período, la licencia del Consejo y la cesación—
 * hacen lo mismo con el resultado: nacen en borrador, se tramitan en la
 * bandeja, y refrescan tanto la pestaña como la bandeja. Y fallan igual: los
 * errores de campo se pintan en el formulario y las reglas de negocio llegan
 * como un mensaje, que es donde está casi todo lo interesante —el período que
 * no cabe, el saliente que no termina, los 60 días del año—.
 *
 * `setError` recibe solo los campos que el formulario enseña; si el error cae
 * en uno que no está, se muestra el mensaje en vez de dejar el botón mudo, que
 * es lo que convirtió un error de validación en un callejón sin salida (H4).
 *
 * Desde la fase 4.1d también sirve para lo que no es una acción de personal:
 * las vacaciones que le concede el Consejo son una solicitud de vacaciones
 * [TH N11] y van por su propia ruta. Lo que se comparte —avisar, refrescar y
 * no fallar mudo— es lo mismo, así que se le pasa `enviar` y las `claves` que
 * refresca.
 */
export function useRegistrarActoDeAutoridad<T extends FieldValues, D = ActoDeAutoridadData>({
  servidorId,
  campos,
  tituloDelError,
  exito,
  onListo,
  enviar = (id, datos) => movimientoService.crearActoDeAutoridad(id, datos as ActoDeAutoridadData),
  claves = CLAVES_DEL_ACTO,
}: {
  servidorId: () => number
  /** Los campos que este formulario pinta, para repartirles los errores. */
  campos: Array<Path<T>>
  tituloDelError: string
  exito: { titulo: string; detalle: string }
  onListo: () => void
  /** A dónde se manda. Por defecto, al endpoint de acciones de personal. */
  enviar?: (servidorId: number, datos: D) => Promise<unknown>
  /** Qué consultas quedan desactualizadas al registrarlo. */
  claves?: string[][]
}) {
  const qc = useQueryClient()
  const [enviando, setEnviando] = useState(false)

  const registrar = async (datos: D, setError: UseFormSetError<T>) => {
    setEnviando(true)
    try {
      await enviar(servidorId(), datos)
      notificar.exito(exito.titulo, exito.detalle)

      for (const key of claves) {
        qc.invalidateQueries({ queryKey: key })
      }
      onListo()
    } catch (error) {
      const fallos = erroresDeCampo(error)
      let pintado = false

      for (const campo of campos) {
        const mensaje = fallos?.[campo as string]
        if (mensaje) {
          setError(campo, { type: 'server', message: mensaje })
          pintado = true
        }
      }

      if (!pintado) {
        notificar.error(tituloDelError, getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'))
      }
    } finally {
      setEnviando(false)
    }
  }

  return { registrar, enviando }
}
