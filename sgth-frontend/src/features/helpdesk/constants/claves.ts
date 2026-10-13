/**
 * Claves de consulta de la mesa de ayuda (regla 08). Cualquier paso del
 * trámite cambia el ticket, su conversación y los listados: se invalida `raiz`.
 */
export const clavesHelpdesk = {
  raiz: ['helpdesk'] as const,
  lista: (filtros: unknown, page: number) => ['helpdesk', 'lista', filtros, page] as const,
  uno: (id: number) => ['helpdesk', 'uno', id] as const,
  catalogos: ['helpdesk', 'catalogos'] as const,
  resumen: ['helpdesk', 'resumen'] as const,
}
