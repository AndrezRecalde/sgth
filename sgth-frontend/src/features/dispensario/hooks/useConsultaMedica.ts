import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { consultaMedicaService } from '../services/consultaMedicaService'
import type { CrearConsultaData } from '../services/consultaMedicaService'
import { notificar } from '@/components/ui'

export function useRegistrarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearConsultaData) =>
      consultaMedicaService.crear(data),
    onSuccess: () => {
      notificar.exito(
        'Consulta registrada',
        'La consulta médica fue guardada correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['consultas'] })
      qc.invalidateQueries({ queryKey: ['agenda'] })
    },
    onError: notificar.alFallar('No se pudo registrar la consulta'),
  })
}

export function useConsultaMedicaDetalle(id: number | null) {
  return useQuery({
    queryKey: ['consulta-detalle-panel', id],
    queryFn:  () => consultaMedicaService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 60,
  })
}

/**
 * Las versiones anteriores de una consulta. Vacío mientras nadie la corrija,
 * que es el caso normal.
 */
export function useVersionesConsulta(id: number | null) {
  return useQuery({
    queryKey: ['consulta-versiones', id],
    queryFn:  () => consultaMedicaService.versiones(id!),
    enabled:  !!id,
    staleTime: 1000 * 30,
  })
}

export function useActualizarConsulta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id: number
      data: Partial<CrearConsultaData>
    }) => consultaMedicaService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Consulta actualizada', 'Los cambios fueron guardados correctamente.')
      qc.invalidateQueries({ queryKey: ['consultas'] })
      // El panel lee la consulta por su propia clave: sin invalidarla, tras
      // corregir seguía enseñando el texto anterior como si fuera el vigente.
      qc.invalidateQueries({ queryKey: ['consulta-detalle-panel'] })
      // Corregir archiva la versión anterior: el historial cambió.
      qc.invalidateQueries({ queryKey: ['consulta-versiones'] })
    },
    onError: notificar.alFallar('No se pudo actualizar la consulta'),
  })
}
