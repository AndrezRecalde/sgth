import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  disciplinarioService,
  type AvanzarSumarioData,
  type ResolverSumarioData,
  type TransicionarVistoBuenoData,
} from '../services/disciplinarioService'
import type { SumarioFormData, VistoBuenoFormData } from '@/types/api'
import { notificar } from '@/components/ui'

export function useDisciplinarioMutations() {
  const qc = useQueryClient()

  const invalidarSumarios = () => qc.invalidateQueries({ queryKey: ['sumarios'] })

  const invalidarVistosBuenos = () => qc.invalidateQueries({ queryKey: ['vistos-buenos'] })

  /**
   * Las acciones de personal solo cambian cuando el acto genera una cesación:
   * resolver un sumario con destitución y conceder un visto bueno. Abrir un
   * sumario o avanzarlo de hito no toca ninguna, y antes las invalidaba igual.
   */
  const invalidarMovimientos = () => qc.invalidateQueries({ queryKey: ['movimientos'] })

  const crearSumario = useMutation({
    mutationFn: (data: SumarioFormData) => disciplinarioService.crearSumario(data),
    onSuccess: () => {
      notificar.exito('Sumario abierto', 'El sumario administrativo fue registrado.')
      invalidarSumarios()
    },
    onError: notificar.alFallar('No se pudo abrir el sumario'),
  })

  const avanzarSumario = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AvanzarSumarioData }) =>
      disciplinarioService.avanzarSumario(id, data),
    onSuccess: () => {
      notificar.exito('Sumario actualizado', 'Se registró el avance procesal.')
      invalidarSumarios()
    },
    // Una fecha fuera de orden la pone AvanzarHitoModal bajo el campo.
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el avance del sumario'),
  })

  const resolverSumario = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ResolverSumarioData }) =>
      disciplinarioService.resolverSumario(id, data),
    onSuccess: (_data, variables) => {
      const destituye = variables.data.tipo_sancion === 'destitucion'

      notificar.exito(
        'Sumario resuelto',
        destituye
          ? 'Se impuso la destitución y se generó la Cesación de Funciones en borrador para revisión de Talento Humano.'
          : 'Se impuso la sanción y quedó registrada en el expediente.',
      )
      invalidarSumarios()
      if (destituye) invalidarMovimientos()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo resolver el sumario'),
  })

  const crearVistoBueno = useMutation({
    mutationFn: (data: VistoBuenoFormData) => disciplinarioService.crearVistoBueno(data),
    onSuccess: () => {
      notificar.exito('Visto bueno solicitado', 'El trámite quedó registrado.')
      invalidarVistosBuenos()
    },
    onError: notificar.alFallar('No se pudo solicitar el visto bueno'),
  })

  const transicionarVistoBueno = useMutation({
    mutationFn: ({ id, data }: { id: number; data: TransicionarVistoBuenoData }) =>
      disciplinarioService.transicionarVistoBueno(id, data),
    onSuccess: (_data, variables) => {
      const concede = variables.data.estado === 'concedido'

      notificar.exito(
        'Trámite actualizado',
        concede
          ? 'Se generó la Cesación de Funciones en borrador para revisión de Talento Humano.'
          : 'Se registró el avance del trámite.',
      )
      invalidarVistosBuenos()
      // Impugnar también la toca: le deja el aviso en la descripción.
      if (concede || variables.data.estado === 'impugnado') invalidarMovimientos()
    },
    // Los errores con campo los pone el modal bajo cada uno.
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar el trámite de visto bueno'),
  })

  const adjuntarResolucion = useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) =>
      disciplinarioService.adjuntarResolucion(id, archivo),
    onSuccess: () => {
      notificar.exito('Resolución adjuntada', 'El PDF de la resolución del Inspector quedó en el trámite.')
      invalidarVistosBuenos()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo adjuntar la resolución'),
  })

  return {
    crearSumario,
    avanzarSumario,
    resolverSumario,
    crearVistoBueno,
    transicionarVistoBueno,
    adjuntarResolucion,
  }
}
