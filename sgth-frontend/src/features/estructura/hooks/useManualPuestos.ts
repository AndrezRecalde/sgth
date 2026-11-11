import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { manualPuestosService } from '../services/manualPuestosService'

const FICHAS = ['manual-de-puestos', 'fichas']
const EQUIVALENCIAS = ['manual-de-puestos', 'equivalencias']

/**
 * Las fichas del Manual de Puestos con su estado (P4, PR 12). `habilitado`
 * para quien no gestiona puestos: el backend le negaría la lista.
 */
export function useFichasManual(habilitado = true) {
  return useQuery({
    queryKey: FICHAS,
    queryFn: manualPuestosService.fichas,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}

export function useFichaManual(id: number | null) {
  return useQuery({
    queryKey: [...FICHAS, id],
    queryFn: () => manualPuestosService.ficha(id ?? 0),
    enabled: id !== null,
  })
}

export function useEquivalenciasManual() {
  return useQuery({
    queryKey: EQUIVALENCIAS,
    queryFn: manualPuestosService.equivalencias,
    staleTime: 1000 * 60,
  })
}

export function useManualPuestosMutations() {
  const qc = useQueryClient()

  // Cargar cambia el perfil, las actividades y las competencias del puesto:
  // los leen su drawer, el FEMO y la ficha impresa.
  const alCargar = () => {
    for (const queryKey of [['manual-de-puestos'], ['puestos'], ['puesto-descripcion'], ['puesto-actividades'], ['puesto-competencias']]) {
      qc.invalidateQueries({ queryKey })
    }
  }

  const cargar = useMutation({
    mutationFn: ({ fichaId, puestoId }: { fichaId: number; puestoId: number }) => manualPuestosService.cargar(fichaId, puestoId),
    onSuccess: (respuesta) => {
      notificar.exito('Perfil cargado en el puesto', respuesta.mensaje)
      alCargar()
    },
    onError: notificar.alFallar('No se pudo cargar la ficha en el puesto'),
  })

  const desvincular = useMutation({
    mutationFn: (fichaId: number) => manualPuestosService.desvincular(fichaId),
    onSuccess: () => {
      notificar.exito('Ficha sin puesto', 'El perfil que se cargó sigue en el puesto: edítelo desde su perfil.')
      qc.invalidateQueries({ queryKey: ['manual-de-puestos'] })
    },
    onError: notificar.alFallar('No se pudo quitar el puesto de la ficha'),
  })

  // Una equivalencia cambia la sugerencia de todas las fichas de esa gestión.
  const guardarEquivalencia = useMutation({
    mutationFn: ({ gestion, unidadId }: { gestion: string; unidadId: number | null }) =>
      manualPuestosService.guardarEquivalencia(gestion, unidadId),
    onSuccess: (gestiones, { unidadId }) => {
      qc.setQueryData(EQUIVALENCIAS, gestiones)
      qc.invalidateQueries({ queryKey: FICHAS })
      notificar.exito(unidadId === null ? 'Vuelve a la unidad sugerida' : 'Unidad guardada', 'Las sugerencias de sus fichas se recalcularon.')
    },
    onError: notificar.alFallar('No se pudo guardar la unidad de la gestión'),
  })

  return { cargar, desvincular, guardarEquivalencia }
}
