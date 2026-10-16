import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo, guardarArchivo } from '@/lib/archivo'
import { clavesComunicacion } from '../constants/claves'
import { credencialesService, miCredencialService, verificacionCredencialService } from '../services/credencialesService'
import type { FiltrosCredenciales, MotivoRevocacion, PedidoImpresion } from '../services/tiposCredencial'

/** Los servidores con el estado de su credencial, filtrados y paginados en el servidor. */
export function useCredenciales(filtros: FiltrosCredenciales, page: number) {
  return useQuery({
    queryKey: clavesComunicacion.credenciales.lista(filtros, page),
    queryFn: () => credencialesService.listar(filtros, page),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

/** A dónde lleva el QR: no cambia mientras la aplicación corre. */
export function useDireccionVerificacion() {
  return useQuery({
    queryKey: clavesComunicacion.credenciales.direccion,
    queryFn: credencialesService.direccionVerificacion,
    staleTime: Infinity,
  })
}

/** Todas las credenciales de un servidor con sus impresiones. */
export function useHistorialCredenciales(servidorId: number | null) {
  return useQuery({
    queryKey: clavesComunicacion.credenciales.historial(servidorId ?? 0),
    queryFn: () => credencialesService.historial(servidorId ?? 0),
    enabled: !!servidorId,
  })
}

/**
 * Imprimir, revocar y recibir la tarjeta cambian la situación de cada
 * servidor: el listado y el historial quedan viejos.
 */
export function useMutacionesCredencial() {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesComunicacion.credenciales.todas })

  const imprimir = useMutation({
    mutationFn: async (pedido: PedidoImpresion) => {
      const pdf = await credencialesService.imprimir(pedido)
      const nombre = `credenciales-${pedido.formato}-${new Date().toISOString().slice(0, 10)}.pdf`
      // Se abre para imprimirlo; si el navegador bloquea la pestaña, se descarga.
      abrirOGuardarArchivo(pdf, nombre)
    },
    onSuccess: () => {
      refrescar()
      notificar.exito('Credenciales listas para imprimir', 'Quedó registrado lo que lleva cada tarjeta.')
    },
    onError: notificar.alFallar('No se pudieron preparar las credenciales'),
  })

  const revocar = useMutation({
    mutationFn: ({ credencialId, motivo, detalle }: { credencialId: number; motivo: MotivoRevocacion; detalle: string | null }) =>
      credencialesService.revocar(credencialId, motivo, detalle),
    onSuccess: (respuesta) => {
      refrescar()
      notificar.exito('Credencial revocada', respuesta.datos.repuesta
        ? 'Se repuso con otro código: imprima la nueva tarjeta.'
        : 'Se repondrá sola cuando el servidor tenga foto.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo revocar la credencial'),
  })

  const registrarDevolucion = useMutation({
    mutationFn: ({ credencialId, fecha, nota }: { credencialId: number; fecha: string; nota: string | null }) =>
      credencialesService.registrarDevolucion(credencialId, fecha, nota),
    onSuccess: () => {
      refrescar()
      notificar.exito('Devolución registrada', 'La tarjeta volvió a Comunicación Social.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la devolución de la tarjeta'),
  })

  return { imprimir, revocar, registrarDevolucion }
}

/** «Mi credencial» del Portal. */
export function useMiCredencial() {
  return useQuery({
    queryKey: clavesComunicacion.credenciales.mia,
    queryFn: miCredencialService.ver,
    staleTime: 1000 * 60,
  })
}

export function useDescargarMiCredencial() {
  return useMutation({
    mutationFn: async () => guardarArchivo(await miCredencialService.pdf(), 'mi-credencial.pdf'),
    onError: notificar.alFallar('No se pudo descargar su credencial'),
  })
}

export function useVerificacionCredencial(codigo: string) {
  return useQuery({
    queryKey: clavesComunicacion.credenciales.verificada(codigo),
    queryFn: () => verificacionCredencialService.verificar(codigo),
    // Sin reintentos: un 404 aquí es la respuesta, no un fallo de red.
    retry: false,
    staleTime: 1000 * 60,
  })
}
