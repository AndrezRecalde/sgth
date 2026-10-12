import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  contratoColectivoService,
  loteAccionService,
  type ClausulaDeLicenciaData,
  type ContratoColectivoData,
  type FiltrosLotes,
  type IncrementoEnLoteData,
  type SalarioBasicoData,
} from '../services/contratoColectivoService'

/** El contrato colectivo vigente, los registrados y los salarios básicos (fase 4.6). */
export function useContratoColectivo() {
  return useQuery({
    queryKey: ['contrato-colectivo'],
    queryFn: contratoColectivoService.resumen,
    staleTime: 1000 * 60,
  })
}

export function useGuardarContratoColectivo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ data, id }: { data: ContratoColectivoData; id?: number }) =>
      contratoColectivoService.guardar(data, id),
    onSuccess: (contrato) => {
      notificar.exito(
        'Contrato colectivo guardado',
        contrato.tiene_dictamen
          ? 'Sus cláusulas se aplican mientras rija.'
          : 'Sin el dictamen del Ministerio de Finanzas, sus cláusulas no se aplican.',
      )
      qc.invalidateQueries({ queryKey: ['contrato-colectivo'] })
    },
    // Los errores de campo los pinta el formulario; lo demás —una vigencia que
    // se cruza con otra— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el contrato colectivo'),
  })
}

export function useGuardarSalarioBasico() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: SalarioBasicoData) => contratoColectivoService.guardarSalarioBasico(data),
    onSuccess: (salario) => {
      notificar.exito('Salario básico guardado', `El de ${salario.anio}.`)
      qc.invalidateQueries({ queryKey: ['contrato-colectivo'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el salario básico'),
  })
}

/**
 * Las cláusulas de licencia del contrato (fase 5.3). Cambian las causas que el
 * formulario de licencias ofrece a los obreros: se refrescan las dos cosas.
 */
export function useGuardarClausulaDeLicencia() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ contratoId, data, id }: { contratoId: number; data: ClausulaDeLicenciaData; id?: number }) =>
      contratoColectivoService.guardarClausulaDeLicencia(contratoId, data, id),
    onSuccess: (clausula) => {
      notificar.exito('Cláusula de licencia guardada', clausula.clausula)
      qc.invalidateQueries({ queryKey: ['contrato-colectivo'] })
      qc.invalidateQueries({ queryKey: ['licencias-causas'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar la cláusula'),
  })
}

export function useEliminarClausulaDeLicencia() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ contratoId, id }: { contratoId: number; id: number }) =>
      contratoColectivoService.eliminarClausulaDeLicencia(contratoId, id),
    onSuccess: () => {
      notificar.exito('Cláusula de licencia eliminada')
      qc.invalidateQueries({ queryKey: ['contrato-colectivo'] })
      qc.invalidateQueries({ queryKey: ['licencias-causas'] })
    },
    onError: notificar.alFallar('No se pudo eliminar la cláusula'),
  })
}

/** Los lotes de acciones: hoy, el incremento de los obreros (fase 4.6). */
/** Por páginas y con filtros (fase 7.2). */
export function useLotesAccion(filtros: FiltrosLotes = {}) {
  return useQuery({
    queryKey: ['lotes-accion', 'listado', filtros],
    queryFn: () => loteAccionService.listar(filtros),
    staleTime: 1000 * 30,
    placeholderData: keepPreviousData,
  })
}

export function useLoteAccion(id: number | null) {
  return useQuery({
    queryKey: ['lotes-accion', id],
    queryFn: () => loteAccionService.detalle(id!),
    enabled: id != null,
  })
}

/** Lo que cambia un lote lo ven también la bandeja y los expedientes. */
function invalidarTodo(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['lotes-accion'] })
  qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
  qc.invalidateQueries({ queryKey: ['movimientos'] })
  // El traspaso mueve el puesto de unidad (fase 7.1a).
  qc.invalidateQueries({ queryKey: ['puestos'] })
}

export function usePrevisualizarIncremento() {
  return useMutation({
    mutationFn: (data: IncrementoEnLoteData) => loteAccionService.previsualizar(data),
    onError: notificar.alFallarSalvoCampos('No se pudo calcular el incremento'),
  })
}

export function usePrepararIncremento() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: IncrementoEnLoteData) => loteAccionService.preparar(data),
    onSuccess: (lote) => {
      notificar.exito('Incremento en lote preparado', `${lote.total} borrador(es), uno por obrero.`)
      invalidarTodo(qc)
    },
    onError: notificar.alFallarSalvoCampos('No se pudo preparar el incremento'),
  })
}

export function useRegistrarLote() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => loteAccionService.registrar(id),
    onSuccess: (r) => {
      if (r.datos.fallidos.length) {
        notificar.aviso('Lote registrado en parte', r.mensaje)
      } else {
        notificar.exito('Lote registrado', r.mensaje)
      }
      invalidarTodo(qc)
    },
    onError: notificar.alFallar('No se pudo registrar el lote'),
  })
}

export function useDescartarLote() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => loteAccionService.descartar(id, motivo),
    onSuccess: (r) => {
      notificar.exito('Lote descartado', r.mensaje)
      invalidarTodo(qc)
    },
    onError: notificar.alFallar('No se pudo descartar el lote'),
  })
}

/**
 * Notifica por el portal lo registrado del lote: cada acto con su correo. Lo
 * que no se pudo, se dice (fase 7.2).
 */
export function useNotificarLote() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => loteAccionService.notificar(id),
    onSuccess: (r) => {
      if (r.datos.omitidos.length) {
        notificar.aviso('Lote notificado en parte', r.mensaje)
      } else {
        notificar.exito('Lote notificado', r.mensaje)
      }
      invalidarTodo(qc)
    },
    onError: notificar.alFallar('No se pudo notificar el lote'),
  })
}

/** El traspaso registrado, entero: el puesto y sus ocupantes vuelven (fase 7.1a). */
export function useRevertirLote() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => loteAccionService.revertir(id, motivo),
    onSuccess: (r) => {
      notificar.exito('Traspaso revertido', r.mensaje)
      invalidarTodo(qc)
    },
    onError: notificar.alFallar('No se pudo revertir el traspaso'),
  })
}
