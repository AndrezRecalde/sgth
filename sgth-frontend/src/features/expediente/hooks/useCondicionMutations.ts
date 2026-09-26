import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { condicionService } from '../services/condicionService'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'

/**
 * Las condiciones de salud del servidor.
 *
 * Al invalidar también `['servidor', id]`: la ficha muestra si tiene
 * discapacidad o enfermedad, y esa marca la deriva el backend de estos
 * registros. Sin invalidarla, la pestaña Personal seguía diciendo «sin
 * condiciones registradas» después de registrar la primera.
 */
export function useCondicionMutations(servidorId: number) {
  const qc = useQueryClient()

  const invalidar = (lista: 'discapacidades' | 'enfermedades') => () => {
    qc.invalidateQueries({ queryKey: [lista, servidorId] })
    qc.invalidateQueries({ queryKey: ['servidor', servidorId] })
    qc.invalidateQueries({ queryKey: ['servidores'] })
  }

  const crearDiscapacidad = useMutation({
    mutationFn: (data: DiscapacidadFormData) =>
      condicionService.crearDiscapacidad(servidorId, data),
    onSuccess: () => {
      notificar.exito('Discapacidad registrada', 'La discapacidad fue registrada correctamente.')
      invalidar('discapacidades')()
    },
    onError: notificar.alFallar('No se pudo registrar la discapacidad'),
  })

  const editarDiscapacidad = useMutation({
    mutationFn: ({ id, data }: { id: number; data: DiscapacidadFormData }) =>
      condicionService.editarDiscapacidad(servidorId, id, data),
    onSuccess: () => {
      notificar.exito('Discapacidad actualizada', 'El registro fue actualizado correctamente.')
      invalidar('discapacidades')()
    },
    onError: notificar.alFallar('No se pudo actualizar la discapacidad'),
  })

  const eliminarDiscapacidad = useMutation({
    mutationFn: (id: number) => condicionService.eliminarDiscapacidad(servidorId, id),
    onSuccess: () => {
      notificar.exito('Registro eliminado', 'La discapacidad fue eliminada del expediente.')
      invalidar('discapacidades')()
    },
    onError: notificar.alFallar('No se pudo eliminar la discapacidad'),
  })

  const crearEnfermedad = useMutation({
    mutationFn: (data: EnfermedadFormData) =>
      condicionService.crearEnfermedad(servidorId, data),
    onSuccess: () => {
      notificar.exito('Enfermedad registrada', 'La enfermedad catastrófica fue registrada.')
      invalidar('enfermedades')()
    },
    onError: notificar.alFallar('No se pudo registrar la enfermedad'),
  })

  const editarEnfermedad = useMutation({
    mutationFn: ({ id, data }: { id: number; data: EnfermedadFormData }) =>
      condicionService.editarEnfermedad(servidorId, id, data),
    onSuccess: () => {
      notificar.exito('Enfermedad actualizada', 'El registro fue actualizado correctamente.')
      invalidar('enfermedades')()
    },
    onError: notificar.alFallar('No se pudo actualizar la enfermedad'),
  })

  const eliminarEnfermedad = useMutation({
    mutationFn: (id: number) => condicionService.eliminarEnfermedad(servidorId, id),
    onSuccess: () => {
      notificar.exito(
        'Registro eliminado',
        'La enfermedad catastrófica fue eliminada del expediente.',
      )
      invalidar('enfermedades')()
    },
    onError: notificar.alFallar('No se pudo eliminar la enfermedad'),
  })

  return {
    crearDiscapacidad,
    editarDiscapacidad,
    eliminarDiscapacidad,
    crearEnfermedad,
    editarEnfermedad,
    eliminarEnfermedad,
  }
}
