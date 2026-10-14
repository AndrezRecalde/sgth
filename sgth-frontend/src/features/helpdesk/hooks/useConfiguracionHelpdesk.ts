import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesHelpdesk } from '../constants/claves'
import { configuracionHelpdeskService as servicio } from '../services/configuracionService'

export const useCategoriasTicket = () =>
  useQuery({ queryKey: clavesHelpdesk.config.categorias, queryFn: servicio.categorias })

export const useTablaSla = () =>
  useQuery({ queryKey: clavesHelpdesk.config.slas, queryFn: servicio.slas })

export const useTecnicosHelpdesk = () =>
  useQuery({ queryKey: clavesHelpdesk.config.tecnicos, queryFn: servicio.tecnicos })

type Recurso = 'categoria' | 'sla' | 'tecnico'

type Servicio<D> = {
  crear: (data: D) => Promise<unknown>
  actualizar: (id: number, data: D) => Promise<unknown>
  eliminar: (id: number) => Promise<unknown>
}

const TEXTOS: Record<Recurso, { el: string; guardado: string; detalle: string; eliminado: string }> = {
  categoria: { el: 'la categoría', guardado: 'Categoría guardada', detalle: 'Las activas se ofrecen al abrir un ticket.', eliminado: '' },
  sla:       { el: 'el SLA', guardado: 'SLA guardado', detalle: 'Rige para los tickets nuevos y los que cambian de prioridad.', eliminado: 'SLA eliminado' },
  tecnico:   { el: 'el técnico', guardado: 'Técnico guardado', detalle: 'Su subunidad y nivel se ven al asignar.', eliminado: 'Técnico retirado' },
}

/**
 * Guardar y eliminar en la configuración de la mesa de ayuda:
 * `useConfiguracionMutations('sla', configuracionHelpdeskService.sla)`. Todo
 * invalida la raíz del helpdesk, porque cambia los catálogos del formulario.
 */
export function useConfiguracionMutations<D>(recurso: Recurso, srv: Servicio<D>) {
  const qc = useQueryClient()
  const textos = TEXTOS[recurso]
  const invalidar = () => qc.invalidateQueries({ queryKey: clavesHelpdesk.raiz })

  const guardar = useMutation({
    mutationFn: ({ id, data }: { id?: number; data: D }) => (id ? srv.actualizar(id, data) : srv.crear(data)),
    onSuccess: () => {
      notificar.exito(textos.guardado, textos.detalle)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos(`No se pudo guardar ${textos.el}`),
  })

  const eliminar = useMutation({
    mutationFn: (id: number) => srv.eliminar(id),
    onSuccess: () => {
      notificar.exito(textos.eliminado, recurso === 'sla' ? 'Rige el plazo general de esa prioridad.' : 'Ya no aparece al asignar.')
      invalidar()
    },
    onError: notificar.alFallar(`No se pudo eliminar ${textos.el}`),
  })

  return { guardar, eliminar }
}
