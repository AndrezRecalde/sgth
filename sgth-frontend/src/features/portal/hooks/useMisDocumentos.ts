import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { misDocumentosService } from '../services/misDocumentosService'

/** «Mis documentos laborales» (fase 6.3a). */
export function useMisDocumentos() {
  return useQuery({
    queryKey: ['mis-documentos-laborales'],
    queryFn: misDocumentosService.listar,
    staleTime: 1000 * 60,
  })
}

/** El acuse de recibo: una sola vez, con la fecha y la hora. */
export function useAcusarRecibo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => misDocumentosService.acusar(id),
    onSuccess: (documento) => {
      notificar.exito('Acusó recibo', `Del documento ${documento.codigo_registro}.`)
      qc.invalidateQueries({ queryKey: ['mis-documentos-laborales'] })
    },
    onError: notificar.alFallar('No se pudo dejar el acuse'),
  })
}
