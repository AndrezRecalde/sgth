import type { ApiError } from '@/types/api'

/**
 * Los errores de validación de una respuesta 422, uno por campo.
 *
 * La regla 07 pide que el error del backend caiga en su campo: una
 * notificación deja a la persona buscando cuál de los campos está mal. Sin
 * errores de campo (otro código, o un 422 con solo un mensaje), `null`, y
 * quien llama muestra la notificación de siempre.
 *
 *   onError: (error) => {
 *     const campos = erroresDeCampo(error)
 *     if (!campos) return notificar.alFallar('No se pudo guardar')(error)
 *     for (const [campo, mensaje] of Object.entries(campos)) setError(campo, { message: mensaje })
 *   }
 */
export function erroresDeCampo(error: unknown): Record<string, string> | null {
  const respuesta = (error as ApiError)?.response
  const errores = respuesta?.status === 422 ? respuesta.data?.errores : undefined
  if (!errores) return null

  const campos = Object.fromEntries(
    Object.entries(errores)
      .filter(([, mensajes]) => Array.isArray(mensajes) && mensajes.length > 0)
      .map(([campo, mensajes]) => [campo, mensajes[0]]),
  )

  return Object.keys(campos).length > 0 ? campos : null
}
