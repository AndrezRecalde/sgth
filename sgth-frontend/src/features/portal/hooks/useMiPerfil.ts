import { useMutation, useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { miPerfilService } from '../services/miPerfilService'

/** La formación académica propia, tal como consta en el expediente. */
export function useMiFormacion() {
  return useQuery({
    queryKey: ['mi-formacion-academica'],
    queryFn:  () => miPerfilService.formacion(),
  })
}

/** La hoja de vida en PDF, armada en el backend con los datos del expediente. */
export function useDescargarHojaDeVida() {
  return useMutation({
    mutationFn: async () => guardarArchivo(await miPerfilService.hojaDeVida(), 'hoja-de-vida.pdf'),
    onError: notificar.alFallar('No se pudo descargar su hoja de vida'),
  })
}
