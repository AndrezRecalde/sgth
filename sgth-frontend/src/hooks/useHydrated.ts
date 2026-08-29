import { useSyncExternalStore } from 'react'
import { useAuthStore } from '@/store/auth.store'

/** El servidor nunca tiene el store rehidratado. */
const enServidor = () => false

const suscribir = (alCambiar: () => void) =>
  useAuthStore.persist.onFinishHydration(alCambiar)

const leer = () => useAuthStore.persist.hasHydrated()

/**
 * Indica si el store persistido de Zustand ya se rehidrató desde localStorage.
 *
 * Importa porque en el primer render del cliente `isAuthenticated` es `false`
 * aunque haya sesión: la rehidratación es asíncrona. Sin esperarla, el guardián
 * de rutas manda a /login a quien sí tenía sesión iniciada.
 *
 * Se lee con `useSyncExternalStore` y no con `useState` + `useEffect` porque
 * eso es exactamente lo que es: un valor que vive fuera de React y del que
 * queremos enterarnos cuando cambie. Además evita el render extra que provoca
 * llamar a setState dentro de un efecto.
 */
export function useHydrated(): boolean {
  return useSyncExternalStore(suscribir, leer, enServidor)
}
