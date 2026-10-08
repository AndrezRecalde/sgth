import { useState } from 'react'

/**
 * El último valor que tuvo `valor` con el diálogo abierto.
 *
 * Un diálogo se anima al cerrarse, y para entonces lo que pintaba ya es
 * `null`: sin esto, durante la animación se vacía o cambia de título. Visto el
 * 2026-10-08 en «Aprobar en Sirha7». Úsese con lo que el diálogo recibe por
 * props o con lo que trae una consulta, no para guardar datos del servidor.
 */
export function useUltimoAbierto<T>(valor: T | null | undefined, abierto: boolean): T | null {
  const [ultimo, setUltimo] = useState<T | null>(valor ?? null)

  if (abierto && valor != null && valor !== ultimo) setUltimo(valor)

  return abierto ? (valor ?? null) : ultimo
}
