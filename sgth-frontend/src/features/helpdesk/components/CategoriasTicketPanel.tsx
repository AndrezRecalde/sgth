'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconCategory, IconEdit, IconEye, IconEyeOff, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useCategoriasTicket, useConfiguracionMutations } from '../hooks/useConfiguracionHelpdesk'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { CategoriaTicketConfig } from '../services/tipos'
import { CategoriaTicketModal } from './CategoriaTicketModal'
import { columnasCategorias } from './configuracion.columns'

/**
 * Las categorías de los tickets: de qué se trata, si el usuario indica el
 * equipo y qué subunidad de TIC la atiende. No se eliminan: una que ya no se
 * usa se desactiva y deja de ofrecerse.
 */
export function CategoriasTicketPanel() {
  const { data = [], isLoading, error } = useCategoriasTicket()
  const { guardar } = useConfiguracionMutations('categoria', configuracionHelpdeskService.categoria)
  const [edicion, setEdicion] = useState<{ abierta: boolean; categoria: CategoriaTicketConfig | null }>({ abierta: false, categoria: null })

  return (
    <SectionCard
      title="Categorías de los tickets"
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true, categoria: null })}>Nueva categoría</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{ icon: IconCategory, title: 'Sin categorías', description: 'Registre de qué tipo pueden ser los pedidos de ayuda.' }}>
        <SgthTable
          records={data}
          columns={columnasCategorias((c) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierta: true, categoria: c }) },
            {
              label: c.activa ? 'Desactivar' : 'Activar',
              icon: c.activa ? <IconEyeOff size={14} /> : <IconEye size={14} />,
              onClick: () => guardar.mutate({ id: c.id, data: { activa: !c.activa } }),
            },
          ])}
        />
      </DataState>
      <CategoriaTicketModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false, categoria: null })} categoria={edicion.categoria} />
    </SectionCard>
  )
}
