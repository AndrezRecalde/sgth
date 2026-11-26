import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { getApiErrorMessage } from '@/types/api'
import { movimientoService, type ActoDeAutoridadData } from '../services/movimientoService'

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
 */
export function useRegistrarActoDeAutoridad<T extends FieldValues>({
  servidorId,
  campos,
  tituloDelError,
  exito,
  onListo,
}: {
  servidorId: () => number
  /** Los campos que este formulario pinta, para repartirles los errores. */
  campos: Array<Path<T>>
  tituloDelError: string
  exito: { titulo: string; detalle: string }
  onListo: () => void
}) {
  const qc = useQueryClient()
  const [enviando, setEnviando] = useState(false)

  const registrar = async (datos: ActoDeAutoridadData, setError: UseFormSetError<T>) => {
    setEnviando(true)
    try {
      await movimientoService.crearActoDeAutoridad(servidorId(), datos)
      notificar.exito(exito.titulo, exito.detalle)

      for (const key of [['autoridades-electas'], ['movimientos'], ['bandeja-movimientos']]) {
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
