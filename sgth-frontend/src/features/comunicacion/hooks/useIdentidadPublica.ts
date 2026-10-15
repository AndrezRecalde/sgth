import { useQuery } from '@tanstack/react-query'
import { ENV } from '@/config/env'
import { clavesComunicacion } from '../constants/claves'
import { identidadService } from '../services/identidadService'
import type { VarianteIdentidad } from '../services/tiposIdentidad'

/**
 * Los logos vigentes y los datos de la institución, sin sesión (fase 2.3). Lo
 * piden el sidebar y el login. La URL de cada archivo lleva su versión, así
 * que el navegador lo guarda y un logo nuevo tiene otra.
 */
export function useIdentidadPublica() {
  return useQuery({
    queryKey: clavesComunicacion.identidad.publica,
    queryFn: identidadService.manifiesto,
    staleTime: 1000 * 60 * 5,
    // Si el backend no responde, cada pantalla usa su logo de respaldo.
    retry: 1,
  })
}

/** La URL de una ruta del manifiesto (`identidad/isotipo?v=3`), sobre la base del API. */
export const urlIdentidad = (ruta: string) => `${ENV.API_URL}/${ruta}`

/** La URL del archivo vigente de una variante, o null si no hay (ni respaldo). */
export function useUrlVariante(variante: VarianteIdentidad): string | null {
  const { data } = useIdentidadPublica()
  const recurso = data?.variantes[variante]

  return recurso ? urlIdentidad(recurso.ruta) : null
}
