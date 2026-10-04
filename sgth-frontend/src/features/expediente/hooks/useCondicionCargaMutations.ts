import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { condicionCargaService } from '../services/condicionCargaService'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'

/**
 * Las condiciones de salud de una carga familiar. Se invalida la lista de
 * cargas porque trae los registros anidados y la marca que el backend deriva
 * de ellos.
 */
export function useCondicionCargaMutations(servidorId: number, cargaId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })

  const crearDiscapacidad = useMutation({
    mutationFn: (data: DiscapacidadFormData) =>
      condicionCargaService.crearDiscapacidad(cargaId, data),
    onSuccess: () => {
      notificar.exito('Discapacidad registrada', 'La discapacidad consta ahora en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la discapacidad'),
  })

  const editarDiscapacidad = useMutation({
    mutationFn: ({ id, data }: { id: number; data: DiscapacidadFormData }) =>
      condicionCargaService.editarDiscapacidad(cargaId, id, data),
    onSuccess: () => {
      notificar.exito('Discapacidad actualizada', 'El registro fue actualizado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la discapacidad'),
  })

  const eliminarDiscapacidad = useMutation({
    mutationFn: (id: number) => condicionCargaService.eliminarDiscapacidad(cargaId, id),
    onSuccess: () => {
      notificar.exito('Discapacidad eliminada', 'La discapacidad dejó de constar en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la discapacidad'),
  })

  const crearEnfermedad = useMutation({
    mutationFn: (data: EnfermedadFormData) =>
      condicionCargaService.crearEnfermedad(cargaId, data),
    onSuccess: () => {
      notificar.exito('Enfermedad registrada', 'La enfermedad consta ahora en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la enfermedad'),
  })

  const editarEnfermedad = useMutation({
    mutationFn: ({ id, data }: { id: number; data: EnfermedadFormData }) =>
      condicionCargaService.editarEnfermedad(cargaId, id, data),
    onSuccess: () => {
      notificar.exito('Enfermedad actualizada', 'El registro fue actualizado correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la enfermedad'),
  })

  const eliminarEnfermedad = useMutation({
    mutationFn: (id: number) => condicionCargaService.eliminarEnfermedad(cargaId, id),
    onSuccess: () => {
      notificar.exito('Enfermedad eliminada', 'La enfermedad dejó de constar en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la enfermedad'),
  })

  return {
    crearDiscapacidad, editarDiscapacidad, eliminarDiscapacidad,
    crearEnfermedad, editarEnfermedad, eliminarEnfermedad,
  }
}
