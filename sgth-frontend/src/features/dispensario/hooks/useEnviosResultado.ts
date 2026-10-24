import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { envioResultadoService, type FiltrosPorValidar, type ValidarEnvioData } from '../services/envioResultadoService'

/** Los tipos que admite el envío del Portal. */
const EXTENSION: Record<string, string> = {
  'application/pdf': '.pdf',
  'image/jpeg':      '.jpg',
  'image/png':       '.png',
}

/** Lo enviado desde el Portal que espera validación; cuelga de `['examenes']`. */
export function useEnviosPorValidar(filtros: FiltrosPorValidar) {
  return useQuery({
    queryKey: ['examenes', 'por-validar', filtros],
    queryFn:  () => envioResultadoService.porValidar(filtros),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

/** Validar, rechazar y mirar el archivo de un envío del Portal. */
export function useEnviosResultado() {
  const qc = useQueryClient()
  // Validar cambia la orden, los resultados del paciente y la bandeja entera.
  const refrescar = () => {
    qc.invalidateQueries({ queryKey: ['examenes'] })
    qc.invalidateQueries({ queryKey: ['resultados'] })
  }

  const validar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ValidarEnvioData }) => envioResultadoService.validar(id, data),
    onSuccess: (respuesta) => {
      notificar.exito('Resultado validado', respuesta.mensaje)
      refrescar()
    },
    // Lo que tiene campo lo pinta el modal; aquí, el resto.
    onError: notificar.alFallarSalvoCampos('No se pudo validar el resultado del Portal'),
  })

  const rechazar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => envioResultadoService.rechazar(id, motivo),
    onSuccess: (respuesta) => {
      notificar.exito('Envío rechazado', respuesta.mensaje)
      refrescar()
    },
    onError: notificar.alFallar('No se pudo rechazar el envío del Portal'),
  })

  const verArchivo = useMutation({
    mutationFn: async (id: number) => {
      const archivo = await envioResultadoService.archivo(id)
      if (!abrirOGuardarArchivo(archivo, `envio-portal-${id}${EXTENSION[archivo.type] ?? ''}`)) {
        notificar.aviso('El archivo se descargó', 'El navegador bloqueó la pestaña; lo encontrará en Descargas.')
      }
    },
    onError: notificar.alFallar('No se pudo abrir el archivo enviado'),
  })

  return { validar, rechazar, verArchivo }
}
