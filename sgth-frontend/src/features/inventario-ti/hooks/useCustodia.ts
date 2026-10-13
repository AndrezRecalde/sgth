import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesInventario } from '../constants/claves'
import { custodiaService, misEquiposService } from '../services/custodiaService'
import type { DevolucionData, EntregaData } from '../services/tipos'

/**
 * Entregar, traspasar, devolver y adjuntar el memorando. Todas cambian la
 * ficha, el historial y el listado (el custodio y el estado del equipo), así
 * que invalidan los equipos enteros.
 */
export function useCustodiaMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: clavesInventario.bienes.todos })

  const entregar = useMutation({
    mutationFn: (data: EntregaData) => custodiaService.entregar(data),
    onSuccess: (a) => {
      notificar.exito('Equipo entregado', `Queda a cargo de ${a.custodio?.nombre ?? 'su custodio'}.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo entregar el equipo'),
  })

  const traspasar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: EntregaData }) => custodiaService.traspasar(id, data),
    onSuccess: (a) => {
      notificar.exito('Custodia traspasada', `Ahora está a cargo de ${a.custodio?.nombre ?? 'su nuevo custodio'}.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo traspasar la custodia'),
  })

  const devolver = useMutation({
    mutationFn: ({ id, data }: { id: number; data: DevolucionData }) => custodiaService.devolver(id, data),
    onSuccess: () => {
      notificar.exito('Devolución registrada', 'El equipo volvió a TIC y queda sin custodio.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la devolución'),
  })

  const memorando = useMutation({
    mutationFn: ({ id, archivo }: { id: number; archivo: File }) => custodiaService.adjuntarMemorando(id, archivo),
    onSuccess: () => {
      notificar.exito('Memorando adjuntado', 'El PDF queda junto a la custodia.')
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo adjuntar el memorando'),
  })

  return { entregar, traspasar, devolver, memorando }
}

/** Abrir el acta o el memorando de una custodia, con aviso si falla. */
export function useDocumentoCustodia() {
  return useMutation({
    mutationFn: async ({ id, que }: { id: number; que: 'acta' | 'memorando' }) => {
      const blob = que === 'acta' ? await custodiaService.acta(id) : await custodiaService.memorando(id)
      abrirOGuardarArchivo(blob, que === 'acta' ? `acta-entrega-${id}.pdf` : `memorando-${id}.pdf`)
    },
    onError: notificar.alFallar('No se pudo abrir el documento de la custodia'),
  })
}

export function useMisEquipos() {
  return useQuery({ queryKey: ['mis-equipos'], queryFn: misEquiposService.listar })
}

export function useActaMia() {
  return useMutation({
    mutationFn: async (id: number) => abrirOGuardarArchivo(await misEquiposService.acta(id), `acta-entrega-${id}.pdf`),
    onError: notificar.alFallar('No se pudo abrir el acta del equipo'),
  })
}
