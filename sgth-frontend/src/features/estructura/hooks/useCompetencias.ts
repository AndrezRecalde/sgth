import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import type { NivelCompetencia } from '@/types/api'
import {
  competenciaService,
  puestoCompetenciaService,
  type CompetenciaDatos,
  type CompetenciaParams,
} from '../services/competenciaService'

/** El diccionario de competencias. Cambia poco: se guarda cinco minutos. */
export function useCompetencias(params?: CompetenciaParams, enabled = true) {
  return useQuery({
    queryKey: ['competencias', params ?? {}],
    queryFn:  () => competenciaService.listar(params),
    enabled,
    staleTime: 1000 * 60 * 5,
  })
}

export function useCompetenciaMutations() {
  const qc = useQueryClient()
  // Cambiar el diccionario cambia lo que muestran las competencias de los puestos.
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['competencias'] })
    qc.invalidateQueries({ queryKey: ['puesto-competencias'] })
  }

  const crear = useMutation({
    mutationFn: (data: CompetenciaDatos) => competenciaService.crear(data),
    onSuccess: (c) => {
      notificar.exito('Competencia agregada', `«${c.nombre}» quedó en el diccionario con el número ${c.numero}.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo agregar la competencia'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof competenciaService.actualizar>[1] }) =>
      competenciaService.actualizar(id, data),
    onSuccess: (c) => {
      notificar.exito('Competencia actualizada', c.activo ? undefined : `«${c.nombre}» ya no se ofrece para los puestos.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar la competencia'),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => competenciaService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Competencia eliminada', 'Se quitó del diccionario.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la competencia'),
  })

  return { crear, editar, eliminar }
}

export function usePuestoCompetencias(puestoId: number | null) {
  return useQuery({
    queryKey: ['puesto-competencias', puestoId],
    queryFn:  () => puestoCompetenciaService.listar(puestoId!),
    enabled:  !!puestoId,
    staleTime: 1000 * 60,
  })
}

export function usePuestoCompetenciaMutations(puestoId: number) {
  const qc = useQueryClient()
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['puesto-competencias', puestoId] })
    // El diccionario cuenta en cuántos puestos se exige cada competencia.
    qc.invalidateQueries({ queryKey: ['competencias'] })
  }

  const agregar = useMutation({
    mutationFn: (data: { competencia_id: number; nivel: NivelCompetencia }) =>
      puestoCompetenciaService.agregar(puestoId, data),
    onSuccess: (c) => {
      notificar.exito('Competencia agregada', `«${c.nombre}», nivel ${c.nivel_label.toLowerCase()}.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo agregar la competencia al puesto'),
  })

  const cambiarNivel = useMutation({
    mutationFn: ({ id, nivel }: { id: number; nivel: NivelCompetencia }) =>
      puestoCompetenciaService.cambiarNivel(puestoId, id, nivel),
    onSuccess: (c) => {
      notificar.exito('Nivel actualizado', `«${c.nombre}» queda en nivel ${c.nivel_label.toLowerCase()}.`)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cambiar el nivel de la competencia'),
  })

  const quitar = useMutation({
    mutationFn: (id: number) => puestoCompetenciaService.quitar(puestoId, id),
    onSuccess: () => {
      notificar.exito('Competencia quitada', 'El puesto ya no la exige.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo quitar la competencia del puesto'),
  })

  return { agregar, cambiarNivel, quitar }
}
