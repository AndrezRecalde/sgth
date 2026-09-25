import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

export function useNormativas(params?: { tipo?: string; solo_activas?: boolean }) {
  return useQuery({
    queryKey: clavesSso.normativas.lista(params),
    queryFn: () => ssoService.listarNormativas(params),
    staleTime: 1000 * 60 * 10,
  })
}

export function useNormativaMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.normativas.todas })
    // Cada normativa activa es una fila de la lista de verificación, y de sus
    // totales sale el porcentaje de cumplimiento del tablero.
    qc.invalidateQueries({ queryKey: clavesSso.cumplimiento.todo })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: { nombre: string; tipo: string; fecha_vigencia?: string; descripcion?: string }) =>
      ssoService.crearNormativa(data),
    onSuccess: () => {
      notificar.exito('Normativa registrada', 'La normativa fue agregada al catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la normativa'),
  })

  // Retirar una normativa del catálogo sin borrar su historial: es lo que la
  // guarda del backend pide cuando ya tiene cumplimiento registrado, y hasta
  // ahora no existía en la pantalla —se podía desactivar por API y ningún
  // listado volvía a mostrarla—.
  const cambiarActivo = useMutation({
    mutationFn: ({ id, activo }: { id: number; activo: boolean }) =>
      ssoService.actualizarNormativa(id, { activo }),
    onSuccess: (_datos, { activo }) => {
      notificar.exito(
        activo ? 'Normativa reactivada' : 'Normativa desactivada',
        activo
          ? 'Vuelve a la lista de verificación de los próximos períodos.'
          : 'Sale de la lista de verificación y conserva el cumplimiento ya registrado.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cambiar el estado de la normativa'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarNormativa(id),
    onSuccess: () => {
      notificar.exito('Normativa eliminada', 'La normativa fue eliminada del catálogo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la normativa'),
  })

  return { crear, cambiarActivo, eliminar }
}
