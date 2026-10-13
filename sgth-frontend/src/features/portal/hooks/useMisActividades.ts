import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import {
  misActividadesService, type FiltrosActividades, type GuardarActividadData,
} from '../services/misActividadesService'

const CLAVE = 'mis-actividades'

/** Sus actividades, filtradas y paginadas. */
export function useMisActividades(filtros: FiltrosActividades, page: number) {
  return useQuery({
    queryKey: [CLAVE, filtros, page],
    queryFn: () => misActividadesService.listar(filtros, page),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

export function useGuardarActividad() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: GuardarActividadData }) =>
      id ? misActividadesService.actualizar(id, data) : misActividadesService.crear(data),
    onSuccess: (a, { id }) => {
      notificar.exito(id ? 'Actividad corregida' : 'Actividad anotada', `Del ${formatFecha(a.fecha)}.`)
      qc.invalidateQueries({ queryKey: [CLAVE] })
    },
    // Los errores de campo los pinta el formulario; lo demás llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar la actividad'),
  })
}

export function useEliminarActividad() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => misActividadesService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Actividad eliminada', 'Ya no aparece en sus actividades ni en el informe.')
      qc.invalidateQueries({ queryKey: [CLAVE] })
    },
    onError: notificar.alFallar('No se pudo eliminar la actividad'),
  })
}
