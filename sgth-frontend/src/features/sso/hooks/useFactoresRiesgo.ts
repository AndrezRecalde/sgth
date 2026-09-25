import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { factoresRiesgoService } from '../services/riesgosService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

export function useFactoresRiesgo(params?: { categoria?: string; search?: string; solo_activos?: boolean }) {
  return useQuery({
    queryKey: clavesSso.factoresRiesgo.lista(params),
    queryFn: () => factoresRiesgoService.listar(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useFactorRiesgoMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.factoresRiesgo.todos })
    // El listado de riesgos muestra el nombre y la categoría del factor:
    // al renombrarlo o retirarlo del catálogo, la tabla los traía viejos.
    qc.invalidateQueries({ queryKey: clavesSso.riesgos.todos })
  }

  const crear = useMutation({
    mutationFn: (data: { nombre: string; categoria: string }) => factoresRiesgoService.crear(data),
    onSuccess: () => {
      notificar.exito('Factor de riesgo registrado', 'El factor fue agregado al catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el factor de riesgo'),
  })

  // Retirar un factor del catálogo sin borrarlo: el borrado está bloqueado en
  // cuanto algún riesgo lo usa —incluido uno en la papelera—, así que esta era
  // la salida que faltaba en la pantalla.
  const cambiarActivo = useMutation({
    mutationFn: ({ id, activo }: { id: number; activo: boolean }) =>
      factoresRiesgoService.actualizar(id, { activo }),
    onSuccess: (_datos, { activo }) => {
      notificar.exito(
        activo ? 'Factor reactivado' : 'Factor desactivado',
        activo
          ? 'Vuelve a ofrecerse al identificar un riesgo.'
          : 'Deja de ofrecerse al identificar un riesgo; los ya valorados lo conservan.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cambiar el estado del factor'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => factoresRiesgoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Factor eliminado', 'El factor fue eliminado del catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el factor de riesgo'),
  })

  return { crear, cambiarActivo, eliminar }
}
