import { useQuery } from '@tanstack/react-query'
import { verificacionCertificadoService } from '../services/verificacionCertificadoService'

export function useVerificacionCertificado(codigo: string) {
  return useQuery({
    queryKey: ['certificado-verificado', codigo],
    queryFn: () => verificacionCertificadoService.verificar(codigo),
    // Un certificado emitido no cambia. Y sin reintentos: un 404 aquí es la
    // respuesta, no un fallo de red que merezca insistir.
    staleTime: Infinity,
    retry: false,
  })
}
