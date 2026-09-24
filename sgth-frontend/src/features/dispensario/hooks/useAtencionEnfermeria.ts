import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { atencionEnfermeriaService } from '../services/atencionEnfermeriaService'
import type { CrearAtencionEnfermeriaData } from '../services/atencionEnfermeriaService'
import { notificar } from '@/components/ui'

export function useCatalogoServicios() {
  return useQuery({
    queryKey: ['catalogo-servicios-enfermeria'],
    queryFn:  atencionEnfermeriaService.catalogo,
    staleTime: 1000 * 60 * 30,
  })
}

export function useAtencionesEnfermeria(filtros?: {
  fecha?: string
  enfermera_id?: number
  /** 1 para dejar fuera las anuladas; por defecto vienen todas, marcadas. */
  solo_vigentes?: 1
  /** El endpoint pagina siempre; sin estos dos solo llegaba la primera página. */
  page?: number
  per_page?: number
}) {
  return useQuery({
    queryKey: ['atenciones-enfermeria', filtros],
    queryFn:  () => atencionEnfermeriaService.listar(filtros),
    staleTime: 1000 * 15,
  })
}

export function useAnularAtencionEnfermeria() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      atencionEnfermeriaService.anular(id, motivo),
    onSuccess: (data) => {
      notificar.exito('Atención anulada', `${data.folio} quedó anulada con su motivo.`)
      qc.invalidateQueries({ queryKey: ['atenciones-enfermeria'] })
    },
    onError: notificar.alFallar('No se pudo anular la atención'),
  })
}

export function useRegistrarAtencionEnfermeria() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAtencionEnfermeriaData) =>
      atencionEnfermeriaService.crear(data),
    onSuccess: (data) => {
      notificar.exito('Atención registrada', `Folio ${data.folio} registrado correctamente.`)
      qc.invalidateQueries({ queryKey: ['atenciones-enfermeria'] })
    },
    onError: notificar.alFallar('No se pudo registrar la atención'),
  })
}
