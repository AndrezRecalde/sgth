import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import type { RiesgoLaboral } from '../services/ssoService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  puesto_id?: number
  estado?: boolean
}

export function useRiesgosLaborales(params?: Params) {
  return useQuery({
    queryKey: clavesSso.riesgos.lista(params),
    queryFn: () => ssoService.listarRiesgos(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useRiesgoLaboralMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.riesgos.todos })
    // El tablero cuenta los riesgos activos por nivel de intervención: sin
    // esto, identificar un riesgo no cambiaba la cifra de la pantalla de al
    // lado hasta que la consulta caducara sola.
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: Partial<RiesgoLaboral>) => ssoService.crearRiesgo(data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral registrado', 'El riesgo fue registrado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el riesgo laboral'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<RiesgoLaboral> }) =>
      ssoService.actualizarRiesgo(id, data),
    onSuccess: () => {
      notificar.exito('Riesgo laboral actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el riesgo laboral'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarRiesgo(id),
    onSuccess: () => {
      notificar.exito('Riesgo laboral eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el riesgo laboral'),
  })

  return { crear, editar, eliminar }
}
