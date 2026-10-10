import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { movimientoService } from '../services/movimientoService'
import type {
  TransicionarData, ActualizarBorradorData, FiltrosBandeja, AvisoFinanciero,
} from '../services/movimientoService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

/**
 * Una multa o una suspensión registrada sale por correo al jefe de Gestión
 * Financiera, y su anulación también (2026-10-04). Si el correo no salió, la
 * acción quedó igual registrada: se avisa para que la envíen a mano.
 */
function avisarFinanciero(aviso: AvisoFinanciero | null, anulada: boolean) {
  if (aviso === 'enviado') {
    notificar.exito(
      'Enviada a Gestión Financiera',
      anulada
        ? 'Se avisó al jefe de Gestión Financiera de la anulación, por si el descuento ya se aplicó.'
        : 'El jefe de Gestión Financiera la recibió por correo para aplicar el descuento.',
    )
  } else if (aviso === 'sin_destinatario') {
    notificar.aviso(
      'No se envió a Gestión Financiera',
      'La unidad financiera no tiene un jefe con correo institucional. Descargue el PDF y envíelo a mano.',
    )
  } else if (aviso === 'fallo_envio') {
    notificar.aviso(
      'No salió el correo a Gestión Financiera',
      'El servidor de correo no respondió. Descargue el PDF y envíelo a mano.',
    )
  }
}

/**
 * El `servidorId` que recibía se retiró el 2026-09-27: lo único que hacía era
 * invalidar ['movimientos', servidorId], que ya cae dentro de ['movimientos'].
 */
export function useMovimientoMutations() {
  const qc = useQueryClient()

  const actualizarBorrador = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ActualizarBorradorData }) =>
      movimientoService.actualizarBorrador(id, data),
    onSuccess: () => {
      notificar.exito(
        'Borrador actualizado',
        'Los cambios quedaron guardados en la acción de personal.',
      )
      // ['movimientos'] es prefijo de ['movimientos', servidorId], así que
      // invalida también el historial de cada servidor.
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      // El detalle abierto en el drawer vive bajo otra clave.
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo guardar el borrador de la acción de personal',
        getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'),
      )
    },
  })

  const transicionar = useMutation({
    mutationFn: ({ id, ...datos }: { id: number } & TransicionarData) =>
      movimientoService.transicionar(id, datos),
    onSuccess: ({ avisoFinanciero }, { estado }) => {
      notificar.exito('Acción actualizada', 'La acción de personal avanzó de estado.')
      avisarFinanciero(avisoFinanciero, estado === 'anulada')
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      // Registrar una acción materializa o cierra el vínculo laboral.
      qc.invalidateQueries({ queryKey: ['servidores'] })
      // La ficha abierta vive bajo otra clave —['servidor', id], que no es
      // prefijo de ['servidores']— y useServidor la da por fresca cinco
      // minutos. Desde que las acciones de personal se aprueban dentro del
      // expediente, sin esto el encabezado seguía diciendo «Sin vínculo»
      // después de aprobar el Ingreso de esa misma persona.
      qc.invalidateQueries({ queryKey: ['servidor'] })
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
      // Una comisión de servicios o una licencia sin remuneración abren una
      // ausencia, y el panel que las lista no se enteraba.
      qc.invalidateQueries({ queryKey: ['ausencias-temporales'] })
      // Registrar la acción de una subrogación es lo que la activa, y anularla
      // la cancela: la pantalla de subrogaciones seguía diciendo «Pendiente»
      // después de aprobarla, hasta que venciera su minuto de staleTime.
      qc.invalidateQueries({ queryKey: ['subrogaciones-vigentes'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo cambiar el estado de la acción de personal',
        getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'),
      )
    },
  })

  const anotar = useMutation({
    mutationFn: ({ id, texto }: { id: number; texto: string }) =>
      movimientoService.anotar(id, texto),
    onSuccess: () => {
      notificar.exito('Anotación registrada', 'Quedó junto a la acción, sin cambiar el documento.')
      qc.invalidateQueries({ queryKey: ['movimiento'] })
    },
    // Todo error a la notificación: el modal valida lo mismo que el backend, y
    // lo que este rechaza —una acción propia, un borrador— no es de un campo.
    onError: (error) => {
      notificar.error(
        'No se pudo registrar la anotación',
        getApiErrorMessage(error, 'Inténtelo de nuevo en unos segundos.'),
      )
    },
  })

  return { actualizarBorrador, transicionar, anotar }
}

export function useMovimiento(id: number | null) {
  return useQuery({
    queryKey: ['movimiento', id],
    queryFn: () => movimientoService.obtener(id!),
    enabled: id !== null,
    staleTime: 1000 * 30,
  })
}

/**
 * Paginación del lado del servidor, 15 por página como el resto del sistema.
 * La clave lleva los filtros y la página: todo lo que cambia el resultado.
 */
export const POR_PAGINA_BANDEJA = 15

export function useBandejaMovimientos(filtros: FiltrosBandeja = {}) {
  const params = { ...filtros, per_page: POR_PAGINA_BANDEJA }

  return useQuery({
    queryKey: ['bandeja-movimientos', params],
    queryFn: () => movimientoService.listarBandeja(params),
    staleTime: 1000 * 60,
  })
}
