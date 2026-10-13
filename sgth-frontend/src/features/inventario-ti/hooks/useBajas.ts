import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesInventario } from '../constants/claves'
import { bajasService } from '../services/bajasService'
import { bienesService } from '../services/bienesService'
import type { FiltrosBienes } from '../services/tipos'
import type { AprobarBajaData, FiltrosBajas, ProponerBajaData } from '../services/tiposBaja'

export function useBajas(filtros: FiltrosBajas, page: number) {
  return useQuery({
    queryKey: clavesInventario.bajas.lista(filtros, page),
    queryFn: () => bajasService.listar(filtros, page),
    placeholderData: keepPreviousData,
  })
}

/**
 * Proponer, aprobar, rechazar, el memorando y la entrega a Activos Fijos.
 * Aprobar saca al equipo del servicio y devuelve su custodia, así que todas
 * invalidan también los equipos (la ficha, el historial y el listado).
 */
export function useBajaMutations() {
  const qc = useQueryClient()
  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesInventario.bajas.todos })
    qc.invalidateQueries({ queryKey: clavesInventario.bienes.todos })
  }

  const proponer = useMutation({
    mutationFn: (data: ProponerBajaData) => bajasService.proponer(data),
    onSuccess: (b) => {
      notificar.exito('Baja propuesta', `Informe ${b.numero}: queda esperando la aprobación del jefe de TIC.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo proponer la baja'),
  })

  const aprobar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AprobarBajaData }) => bajasService.aprobar(id, data),
    onSuccess: (b) => {
      notificar.exito('Baja aprobada', `El equipo ${b.bien?.codigo_institucional ?? ''} salió del servicio.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo aprobar la baja'),
  })

  const rechazar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => bajasService.rechazar(id, motivo),
    onSuccess: () => {
      notificar.exito('Baja rechazada', 'El equipo sigue en el inventario.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo rechazar la baja'),
  })

  const memorando = useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) => bajasService.adjuntarMemorando(id, archivo),
    onSuccess: () => {
      notificar.exito('Memorando adjuntado', 'El PDF queda junto a la baja.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo adjuntar el memorando'),
  })

  const entrega = useMutation({
    mutationFn: ({ id, fecha }: { id: number; fecha: string }) => bajasService.entregaActivos(id, fecha),
    onSuccess: () => {
      notificar.exito('Entrega registrada', 'El equipo queda entregado a Activos Fijos y Bodega.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la entrega'),
  })

  return { proponer, aprobar, rechazar, memorando, entrega }
}

/** Abrir el informe técnico o el memorando de una baja. */
export function useDocumentoBaja() {
  return useMutation({
    mutationFn: async ({ id, numero, que }: { id: number; numero: string; que: 'informe' | 'memorando' }) => {
      const blob = que === 'informe' ? await bajasService.informe(id) : await bajasService.memorando(id)
      abrirOGuardarArchivo(blob, que === 'informe' ? `informe-baja-${numero}.pdf` : `memorando-baja-${numero}.pdf`)
    },
    onError: notificar.alFallar('No se pudo abrir el documento de la baja'),
  })
}

/** La etiqueta con QR de un equipo, o las del filtro del listado. */
export function useEtiquetas() {
  return useMutation({
    mutationFn: async (de: { id: number; codigo: string } | { filtros: FiltrosBienes }) => {
      if ('id' in de) {
        abrirOGuardarArchivo(await bienesService.etiqueta(de.id), `etiqueta-${de.codigo}.pdf`)
      } else {
        abrirOGuardarArchivo(await bienesService.etiquetas(de.filtros), 'etiquetas-equipos.pdf')
      }
    },
    onError: notificar.alFallar('No se pudieron generar las etiquetas'),
  })
}
