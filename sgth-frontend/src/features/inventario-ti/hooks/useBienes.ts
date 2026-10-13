import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesInventario } from '../constants/claves'
import { bienesService } from '../services/bienesService'
import type { FiltrosBienes, GuardarBienData } from '../services/tipos'

/** El inventario, filtrado y paginado en el servidor. */
export function useBienes(filtros: FiltrosBienes, page: number) {
  return useQuery({
    queryKey: clavesInventario.bienes.lista(filtros, page),
    queryFn: () => bienesService.listar(filtros, page),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

export function useBien(id: number) {
  return useQuery({
    queryKey: clavesInventario.bienes.uno(id),
    queryFn: () => bienesService.obtener(id),
    enabled: id > 0,
  })
}

/** Quién lo ha tenido y qué se le ha hecho. */
export function useHistorialBien(id: number, enabled = true) {
  return useQuery({
    queryKey: clavesInventario.bienes.historial(id),
    queryFn: () => bienesService.historial(id),
    enabled: enabled && id > 0,
  })
}

export function useGuardarBien() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: GuardarBienData }) =>
      id ? bienesService.actualizar(id, data) : bienesService.crear(data),
    onSuccess: (bien, { id }) => {
      notificar.exito(id ? 'Equipo actualizado' : 'Equipo registrado', `Código ${bien.codigo_institucional}.`)
      qc.invalidateQueries({ queryKey: clavesInventario.bienes.todos })
      // Los catálogos cuentan cuántos equipos usan cada registro.
      qc.invalidateQueries({ queryKey: clavesInventario.catalogos.todos })
    },
    // Los errores de campo los pinta el formulario; lo demás llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el equipo'),
  })
}

export function useRetirarBien() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => bienesService.retirar(id),
    onSuccess: () => {
      notificar.exito('Ficha retirada', 'El equipo registrado por error ya no aparece en el inventario.')
      qc.invalidateQueries({ queryKey: clavesInventario.bienes.todos })
      qc.invalidateQueries({ queryKey: clavesInventario.catalogos.todos })
    },
    onError: notificar.alFallar('No se pudo retirar la ficha del equipo'),
  })
}
