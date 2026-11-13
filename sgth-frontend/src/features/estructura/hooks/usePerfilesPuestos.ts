import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { perfilesPuestosService } from '../services/perfilesPuestosService'

export const COMPLETITUD = ['perfiles', 'completitud']

/** Lo que traería la copia desde `origenId`, sin tocar nada (P6, PR 15). */
export function useVistaPreviaCopia(puestoId: number, origenId: number | null) {
  return useQuery({
    queryKey: ['perfiles', 'copia', puestoId, origenId],
    queryFn: () => perfilesPuestosService.vistaPrevia(puestoId, origenId ?? 0),
    enabled: origenId !== null,
  })
}

export function useCopiarPerfil(puestoId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (origenId: number) => perfilesPuestosService.copiar(puestoId, origenId),
    onSuccess: (respuesta) => {
      notificar.exito('Perfil copiado', respuesta.mensaje)
      // Cambian el perfil, las actividades y las competencias del puesto: los
      // leen su cajón, el tablero, el FEMO, la ficha impresa y lo que el
      // manual dice que se reemplazaría.
      for (const queryKey of [['perfiles'], ['puestos'], ['puesto-descripcion'], ['puesto-actividades'], ['puesto-competencias'], ['manual-de-puestos']]) {
        qc.invalidateQueries({ queryKey })
      }
    },
    // El impedimento llega como error del campo: lo muestra el modal.
    onError: notificar.alFallarSalvoCampos('No se pudo copiar el perfil'),
  })
}

/** El tablero; `habilitado` para quien no gestiona puestos, a quien el backend se lo negaría. */
export function useCompletitudPerfiles(habilitado = true) {
  return useQuery({
    queryKey: COMPLETITUD,
    queryFn: perfilesPuestosService.completitud,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}
