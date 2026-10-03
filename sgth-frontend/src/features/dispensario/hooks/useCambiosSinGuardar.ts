import { useEffect, useState } from 'react'

/**
 * Si la ficha tiene cambios que no se han guardado.
 *
 * Compara lo que se enviaría ahora con lo último guardado o cargado. Un FEMO
 * tiene más de sesenta campos y vivía solo en memoria: recargar la página o
 * pulsar «Volver» lo perdía entero sin avisar.
 *
 * `listo` dice cuándo la ficha terminó de rellenarse (con la solicitud o con
 * el borrador guardado): esa es la línea base, no los cambios del médico.
 */
export function useCambiosSinGuardar(actual: string, listo: boolean) {
  const [base, setBase] = useState<string | null>(null)

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (listo && base === null) setBase(actual)
  }, [listo, base, actual])

  const sucio = base !== null && base !== actual

  useEffect(() => {
    if (!sucio) return
    const avisar = (e: BeforeUnloadEvent) => { e.preventDefault() }
    window.addEventListener('beforeunload', avisar)
    return () => window.removeEventListener('beforeunload', avisar)
  }, [sucio])

  return {
    sucio,
    /** Fija lo que hay ahora como guardado. */
    marcarGuardado: () => setBase(actual),
  }
}
