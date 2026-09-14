import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { solicitudCertificacionService } from '../services/solicitudCertificacionService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useSolicitudesCertificacion(params?: {
  page?:        number
  estado?:      string
  tipo_evento?: string
  servidor_id?: number
  origen?:      string
  unidad_administrativa_id?: number
  anio?:        number
  per_page?:    number
}) {
  return useQuery({
    queryKey: ['solicitudes-certificacion', params],
    queryFn:  () => solicitudCertificacionService.listar(params),
    staleTime: 1000 * 30,
    refetchInterval: 1000 * 60,
  })
}

export function useSolicitudDetalle(id: number | null) {
  return useQuery({
    queryKey: ['solicitudes-certificacion', 'detalle', id],
    queryFn:  () => solicitudCertificacionService.obtener(id!),
    enabled:  !!id,
    staleTime: 1000 * 15,
  })
}

export function useIniciarProceso() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      solicitudCertificacionService.iniciarProceso(id),
    onSuccess: () => {
      notificar.exito('Proceso iniciado', 'La solicitud está en proceso de atención.')
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
      // La incorporación se dispara también desde Reclutamiento Express, y
      // allí el aspirante pasa a «Incorporado».
      qc.invalidateQueries({ queryKey: ['express-aspirantes'] })
      qc.invalidateQueries({ queryKey: ['express-resumen'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useCompletarSolicitud() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: {
        dictamen:           'apto' | 'apto_con_restricciones' | 'no_apto'
        observacion_medica?: string | null
        ficha_femo_id?:     number | null
      }
    }) => solicitudCertificacionService.completar(id, data),
    onSuccess: () => {
      notificar.exito('Solicitud completada', 'La certificación médica fue emitida.')
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useCrearSolicitudLote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Parameters<typeof solicitudCertificacionService.crearLote>[0]) =>
      solicitudCertificacionService.crearLote(data),
    onSuccess: (resultado) => {
      const nCreadas  = resultado?.creadas?.length ?? 0
      const nOmitidas = resultado?.omitidas?.length ?? 0
      // Sin ninguna creada no se completó nada: todas tenían una solicitud activa.
      const avisar = nCreadas > 0 ? notificar.exito : notificar.aviso
      avisar(
        'Solicitudes generadas',
        nOmitidas > 0
          ? `${nCreadas} solicitud(es) creada(s), ${nOmitidas} omitida(s) por tener una solicitud activa.`
          : `${nCreadas} solicitud(es) creada(s) correctamente.`,
        { autoClose: 6000 },
      )
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useConfirmarIncorporacion() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      solicitudCertificacionService.confirmarIncorporacion(id),
    onSuccess: () => {
      notificar.exito(
        'Identidad creada, ingreso pendiente de aprobación',
        'Se creó el expediente del servidor. El ingreso quedó registrado en borrador y requiere revisión y aprobación de Talento Humano en Expediente / Movimientos antes de quedar vinculado formalmente.',
        { autoClose: 8000 },
      )
      qc.invalidateQueries({
        queryKey: ['solicitudes-certificacion'],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
