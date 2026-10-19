import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { AVISO_CONDICIONES_DE_SALUD } from '../constants/misCargas'
import { misCargasService, type EnvioSolicitudCarga } from '../services/misCargasService'

const CLAVE = ['mis-cargas-familiares']

const TITULO_ENVIADA = {
  alta:       'Registro enviado a Talento Humano',
  correccion: 'Corrección enviada a Talento Humano',
  baja:       'Baja enviada a Talento Humano',
} as const

/** Sus cargas familiares y lo que pidió sobre ellas. */
export function useMisCargas() {
  return useQuery({
    queryKey: CLAVE,
    queryFn:  () => misCargasService.listar(),
  })
}

/** Presentar y retirar solicitudes, y ver el documento de una. */
export function useMisCargasMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: CLAVE })

  const presentar = useMutation({
    mutationFn: (envio: EnvioSolicitudCarga) => misCargasService.presentar(envio),
    onSuccess: (respuesta, envio) => {
      // Al registrar a un familiar, el recordatorio de sus condiciones de
      // salud (D12): el Portal no las pide. Se queda más tiempo en pantalla.
      notificar.exito(
        TITULO_ENVIADA[envio.tipo],
        envio.tipo === 'alta' ? `${respuesta.mensaje} ${AVISO_CONDICIONES_DE_SALUD}` : respuesta.mensaje,
        envio.tipo === 'alta' ? { autoClose: 12000 } : undefined,
      )
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo enviar la solicitud a Talento Humano'),
  })

  const retirar = useMutation({
    mutationFn: (id: number) => misCargasService.retirar(id),
    onSuccess: () => {
      notificar.exito('Solicitud retirada', 'Talento Humano ya no la revisará.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo retirar la solicitud'),
  })

  const verDocumento = useMutation({
    mutationFn: async ({ id, nombre }: { id: number; nombre: string | null }) => {
      if (!abrirOGuardarArchivo(await misCargasService.documento(id), nombre ?? 'documento.pdf')) {
        notificar.aviso('El navegador bloqueó la pestaña', 'El documento se descargó.')
      }
    },
    onError: notificar.alFallar('No se pudo abrir el documento'),
  })

  return { presentar, retirar, verDocumento }
}
