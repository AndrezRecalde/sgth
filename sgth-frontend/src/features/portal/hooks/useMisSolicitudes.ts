import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { misSolicitudesService, type PresentarSolicitudData } from '../services/misSolicitudesService'

/** Sus solicitudes de renuncia, licencia sin remuneración y jubilación (fase 6.3b). */
export function useMisSolicitudes() {
  return useQuery({
    queryKey: ['mis-solicitudes-accion'],
    queryFn: misSolicitudesService.listar,
    staleTime: 1000 * 30,
  })
}

export function usePresentarSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: PresentarSolicitudData) => misSolicitudesService.presentar(data),
    onSuccess: (r) => {
      notificar.exito('Solicitud presentada', r.mensaje)
      qc.invalidateQueries({ queryKey: ['mis-solicitudes-accion'] })
    },
    // Los errores de campo los pinta el formulario; lo demás llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo presentar la solicitud'),
  })
}

export function useRetirarSolicitud() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => misSolicitudesService.retirar(id),
    onSuccess: (s) => {
      notificar.exito('Solicitud retirada', s.folio)
      qc.invalidateQueries({ queryKey: ['mis-solicitudes-accion'] })
    },
    onError: notificar.alFallar('No se pudo retirar la solicitud'),
  })
}
