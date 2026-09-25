import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ssoService } from '../services/ssoService'
import type { AccidenteTrabajo } from '../services/ssoService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

interface Params {
  page?: number
  servidor_id?: number
  estado?: boolean
}

export function useAccidentesTrabajo(params?: Params) {
  return useQuery({
    queryKey: clavesSso.accidentes.lista(params),
    queryFn: () => ssoService.listarAccidentes(params),
    staleTime: 1000 * 60 * 5,
  })
}

export function useAccidenteTrabajoMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.accidentes.todos })
    // Los índices reactivos del CD 513 —frecuencia, gravedad, tasa de
    // riesgo— se calculan sobre estos accidentes. Solo las horas
    // trabajadas los invalidaban, así que registrar un accidente dejaba
    // los tres índices en la cifra anterior.
    qc.invalidateQueries({ queryKey: clavesSso.indicadores.todos })
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crear = useMutation({
    mutationFn: (data: Partial<AccidenteTrabajo>) => ssoService.crearAccidente(data),
    onSuccess: () => {
      notificar.exito(
        'Accidente registrado',
        'El accidente de trabajo fue registrado correctamente.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar el accidente'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<AccidenteTrabajo> }) =>
      ssoService.actualizarAccidente(id, data),
    onSuccess: () => {
      notificar.exito('Accidente actualizado', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar el accidente'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => ssoService.eliminarAccidente(id),
    onSuccess: () => {
      notificar.exito('Accidente eliminado', 'El registro fue eliminado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar el accidente'),
  })

  return { crear, editar, eliminar }
}
