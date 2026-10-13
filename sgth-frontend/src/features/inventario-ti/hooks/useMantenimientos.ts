import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesInventario } from '../constants/claves'
import { mantenimientosService } from '../services/mantenimientosService'
import type { FiltrosMantenimientos, RegistrarMantenimientoData } from '../services/tipos'

export function useMantenimientos(filtros: FiltrosMantenimientos, page: number) {
  return useQuery({
    queryKey: clavesInventario.mantenimientos.lista(filtros, page),
    queryFn: () => mantenimientosService.listar(filtros, page),
    placeholderData: keepPreviousData,
  })
}

/** Quiénes pueden figurar como técnico: los que registran mantenimientos. */
export function useTecnicosTic(enabled = true) {
  return useQuery({
    queryKey: clavesInventario.mantenimientos.tecnicos,
    queryFn: mantenimientosService.tecnicos,
    staleTime: 1000 * 60 * 10,
    enabled,
  })
}

/**
 * Registrar y anular. Un mantenimiento puede cambiar el estado del equipo,
 * así que invalida también los equipos (la ficha, el historial y el listado).
 */
export function useMantenimientoMutations() {
  const qc = useQueryClient()
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesInventario.mantenimientos.todos })
    qc.invalidateQueries({ queryKey: clavesInventario.bienes.todos })
  }

  const registrar = useMutation({
    mutationFn: (data: RegistrarMantenimientoData) => mantenimientosService.registrar(data),
    onSuccess: (m) => {
      notificar.exito('Mantenimiento registrado', `${m.tipo_etiqueta} del equipo ${m.bien?.codigo_institucional ?? ''}.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el mantenimiento'),
  })

  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => mantenimientosService.anular(id, motivo),
    onSuccess: () => {
      notificar.exito('Mantenimiento anulado', 'Queda en el historial del equipo con su motivo.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo anular el mantenimiento'),
  })

  return { registrar, anular }
}
