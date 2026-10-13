import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesPrestamos } from '../constants/claves'
import { prestamosTicService } from '../services/prestamosTicService'
import type { DirectoData, EntregaData, FiltrosBandeja, RecepcionData, ReglasPrestamo } from '../services/tipos'

export function useBandejaPrestamos(filtros: FiltrosBandeja, page: number) {
  return useQuery({
    queryKey: clavesPrestamos.tic.lista(filtros, page),
    queryFn: () => prestamosTicService.listar(filtros, page),
    placeholderData: keepPreviousData,
    refetchInterval: 60_000,
  })
}

export function useResumenPrestamos() {
  return useQuery({ queryKey: clavesPrestamos.tic.resumen, queryFn: prestamosTicService.resumen, refetchInterval: 60_000 })
}

export function usePrestamoTic(id: number | null) {
  return useQuery({
    queryKey: clavesPrestamos.tic.uno(id ?? 0),
    queryFn: () => prestamosTicService.obtener(id ?? 0),
    enabled: id !== null && id > 0,
  })
}

/** La agenda de TIC: con el folio y quién tiene cada equipo. */
export function useAgendaTic(desde: string, hasta: string) {
  return useQuery({
    queryKey: clavesPrestamos.tic.agenda(desde, hasta),
    queryFn: () => prestamosTicService.disponibilidad(desde, hasta),
    placeholderData: keepPreviousData,
    refetchInterval: 60_000,
  })
}

export function useReglasPrestamoTic() {
  return useQuery({ queryKey: clavesPrestamos.reglas, queryFn: prestamosTicService.reglas, staleTime: 1000 * 60 * 10 })
}

/**
 * El trámite. Cada paso cambia la bandeja, la agenda, la disponibilidad del
 * Portal y, al recibir, la condición del equipo: se invalida todo el préstamo
 * y la ficha de los equipos.
 */
export function usePrestamoTicMutations() {
  const qc = useQueryClient()
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesPrestamos.raiz })
    qc.invalidateQueries({ queryKey: ['inventario-ti', 'bienes'] })
  }
  const avisar = (titulo: string, detalle: string) => {
    notificar.exito(titulo, detalle)
    invalidar()
  }

  const aprobar = useMutation({
    mutationFn: (id: number) => prestamosTicService.aprobar(id),
    onSuccess: (p) => avisar('Préstamo aprobado', `${p.folio}: el horario queda reservado.`),
    onError: notificar.alFallar('No se pudo aprobar el préstamo'),
  })
  const rechazar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => prestamosTicService.rechazar(id, motivo),
    onSuccess: (p) => avisar('Préstamo rechazado', `${p.folio}: el servidor verá el motivo.`),
    onError: notificar.alFallar('No se pudo rechazar el préstamo'),
  })
  const cancelar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => prestamosTicService.cancelar(id, motivo),
    onSuccess: (p) => avisar('Préstamo cancelado', `${p.folio}: el horario queda libre.`),
    onError: notificar.alFallar('No se pudo cancelar el préstamo'),
  })
  const entregar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: EntregaData }) => prestamosTicService.entregar(id, data),
    onSuccess: (p) => avisar('Entrega registrada', `${p.folio} queda en curso.`),
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la entrega'),
  })
  const recibir = useMutation({
    mutationFn: ({ id, data }: { id: number; data: RecepcionData }) => prestamosTicService.recibir(id, data),
    onSuccess: (p) => avisar('Devolución registrada', `${p.folio} queda devuelto y los equipos, en TIC.`),
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la devolución'),
  })
  const directo = useMutation({
    mutationFn: (data: DirectoData) => prestamosTicService.directo(data),
    onSuccess: (p) => avisar('Préstamo entregado', `${p.folio} queda en curso.`),
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el préstamo directo'),
  })

  return { aprobar, rechazar, cancelar, entregar, recibir, directo }
}

export function useGuardarReglasPrestamo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Omit<ReglasPrestamo, 'actualizado_por' | 'updated_at'>) => prestamosTicService.guardarReglas(data),
    onSuccess: () => {
      notificar.exito('Reglas guardadas', 'Las solicitudes nuevas ya se validan con ellas.')
      qc.invalidateQueries({ queryKey: clavesPrestamos.reglas })
    },
    onError: notificar.alFallarSalvoCampos('No se pudieron guardar las reglas del préstamo'),
  })
}

export function useActaPrestamo() {
  return useMutation({
    mutationFn: async ({ id, folio }: { id: number; folio: string }) =>
      abrirOGuardarArchivo(await prestamosTicService.acta(id), `acta-prestamo-${folio}.pdf`),
    onError: notificar.alFallar('No se pudo abrir el acta del préstamo'),
  })
}
