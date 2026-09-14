import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  disciplinarioService,
  type AvanzarSumarioData,
  type TransicionarVistoBuenoData,
} from '../services/disciplinarioService'
import type { SumarioFormData, VistoBuenoFormData } from '@/types/api'
import { notificar } from '@/components/ui'

function exito(title: string, message: string) {
  notificar.exito(title, message)
}

export function useDisciplinarioMutations() {
  const qc = useQueryClient()

  const invalidarSumarios = () => {
    qc.invalidateQueries({ queryKey: ['sumarios'] })
    qc.invalidateQueries({ queryKey: ['movimientos'] })
  }

  const invalidarVistosBuenos = () => {
    qc.invalidateQueries({ queryKey: ['vistos-buenos'] })
    qc.invalidateQueries({ queryKey: ['movimientos'] })
  }

  const crearSumario = useMutation({
    mutationFn: (data: SumarioFormData) => disciplinarioService.crearSumario(data),
    onSuccess: () => {
      exito('Sumario abierto', 'El sumario administrativo fue registrado.')
      invalidarSumarios()
    },
    onError: notificar.alFallar('No se pudo abrir el sumario'),
  })

  const avanzarSumario = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AvanzarSumarioData }) =>
      disciplinarioService.avanzarSumario(id, data),
    onSuccess: () => {
      exito('Sumario actualizado', 'Se registró el avance procesal.')
      invalidarSumarios()
    },
    onError: notificar.alFallar('No se pudo registrar el avance del sumario'),
  })

  const crearVistoBueno = useMutation({
    mutationFn: (data: VistoBuenoFormData) => disciplinarioService.crearVistoBueno(data),
    onSuccess: () => {
      exito('Visto bueno solicitado', 'El trámite quedó registrado.')
      invalidarVistosBuenos()
    },
    onError: notificar.alFallar('No se pudo solicitar el visto bueno'),
  })

  const transicionarVistoBueno = useMutation({
    mutationFn: ({ id, data }: { id: number; data: TransicionarVistoBuenoData }) =>
      disciplinarioService.transicionarVistoBueno(id, data),
    onSuccess: (_data, variables) => {
      exito(
        'Trámite actualizado',
        variables.data.estado === 'concedido'
          ? 'Se generó la Cesación de Funciones en borrador para revisión de Talento Humano.'
          : 'Se registró el avance del trámite.',
      )
      invalidarVistosBuenos()
    },
    onError: notificar.alFallar('No se pudo actualizar el trámite de visto bueno'),
  })

  return { crearSumario, avanzarSumario, crearVistoBueno, transicionarVistoBueno }
}
