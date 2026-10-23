import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { misExamenesService, type EnvioDeResultado, type MiOrdenExamen } from '../services/misExamenesService'

const CLAVE = ['mis-examenes']

/** Los tipos que acepta el envío (`EnviarResultadoRequest`). */
const EXTENSION: Record<string, string> = {
  'application/pdf': '.pdf',
  'image/jpeg':      '.jpg',
  'image/png':       '.png',
}

/** Sus órdenes de exámenes, de la más reciente a la más antigua. */
export function useMisExamenes(page: number) {
  return useQuery({
    queryKey:        [...CLAVE, page],
    queryFn:         () => misExamenesService.listar(page),
    placeholderData: keepPreviousData,
  })
}

/** Enviar y retirar resultados, y abrir el impreso de la orden o el archivo enviado. */
export function useMisExamenesMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: CLAVE })

  const enviar = useMutation({
    mutationFn: ({ ordenId, envio }: { ordenId: number; envio: EnvioDeResultado }) =>
      misExamenesService.enviar(ordenId, envio),
    onSuccess: (respuesta) => {
      notificar.exito('Resultado enviado al Dispensario', respuesta.mensaje)
      invalidar()
    },
    // Lo que tiene campo lo pinta el modal; aquí, el resto.
    onError: notificar.alFallarSalvoCampos('No se pudo enviar el resultado al Dispensario'),
  })

  const retirar = useMutation({
    mutationFn: (envioId: number) => misExamenesService.retirar(envioId),
    onSuccess: () => {
      notificar.exito('Envío retirado', 'El Dispensario ya no lo revisará.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo retirar el envío'),
  })

  const verOrden = useMutation({
    mutationFn: async (orden: MiOrdenExamen) => {
      if (!abrirOGuardarArchivo(await misExamenesService.pdf(orden.id), `orden-examenes-${orden.folio}.pdf`)) {
        notificar.aviso('La orden se descargó', 'El navegador bloqueó la pestaña; la encontrará en Descargas.')
      }
    },
    onError: notificar.alFallar('No se pudo abrir la orden de exámenes'),
  })

  const verArchivo = useMutation({
    mutationFn: async (envioId: number) => {
      // PDF o foto: la extensión sale de lo que respondió el backend.
      const archivo = await misExamenesService.archivo(envioId)
      const extension = EXTENSION[archivo.type] ?? ''
      if (!abrirOGuardarArchivo(archivo, `resultado-enviado-${envioId}${extension}`)) {
        notificar.aviso('El archivo se descargó', 'El navegador bloqueó la pestaña; lo encontrará en Descargas.')
      }
    },
    onError: notificar.alFallar('No se pudo abrir el archivo enviado'),
  })

  return { enviar, retirar, verOrden, verArchivo }
}
