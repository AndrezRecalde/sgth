import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { novedadesNominaService } from '../services/novedadesNominaService'

/** Las novedades del mes: derivadas si está abierto, o como se entregaron (fase 7.3a). */
export function useNovedadesNomina(periodo: string) {
  return useQuery({
    queryKey: ['novedades-nomina', periodo],
    queryFn: () => novedadesNominaService.delMes(periodo),
    staleTime: 1000 * 30,
    placeholderData: keepPreviousData,
  })
}

/**
 * Entrega el mes a Financiero: lo congela y le envía el Excel al jefe de
 * Gestión Financiera. Si el correo no salió, la entrega queda hecha y se dice.
 */
export function useEntregarNovedades() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (periodo: string) => novedadesNominaService.entregar(periodo),
    onSuccess: (r) => {
      const aviso = r.meta?.aviso_financiero
      if (aviso === 'enviado') {
        notificar.exito('Novedades entregadas', r.mensaje)
      } else {
        notificar.aviso(
          'Novedades entregadas, sin el correo',
          aviso === 'sin_destinatario'
            ? 'La unidad financiera no tiene un jefe con usuario: descargue el Excel y envíeselo a mano.'
            : 'El correo a Financiero no salió: descargue el Excel y envíeselo a mano.',
        )
      }
      qc.invalidateQueries({ queryKey: ['novedades-nomina'] })
    },
    onError: notificar.alFallar('No se pudieron entregar las novedades'),
  })
}
