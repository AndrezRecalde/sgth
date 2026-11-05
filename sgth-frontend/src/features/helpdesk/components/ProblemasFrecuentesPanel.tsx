'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconArrowDown, IconArrowUp, IconBulb, IconEdit, IconEye, IconEyeOff, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { plural } from '@/lib/plural'
import { useProblemaFrecuenteMutations, useProblemasFrecuentes } from '../hooks/useConfiguracionHelpdesk'
import type { ProblemaFrecuenteConfig } from '../services/tipos'
import { columnasProblemas } from './configuracion.columns'
import { ProblemaFrecuenteModal } from './ProblemaFrecuenteModal'

/**
 * Los problemas frecuentes de «Pedir ayuda a TIC»: el servidor toca uno y el
 * ticket sale con lo que TIC definió aquí. Los activos se ven en este orden;
 * los inactivos van al final y no se ordenan. No se eliminan: se desactivan.
 */
export function ProblemasFrecuentesPanel() {
  const { data = [], isLoading, error } = useProblemasFrecuentes()
  const { guardar, ordenar } = useProblemaFrecuenteMutations()
  const [edicion, setEdicion] = useState<{ abierta: boolean; problema: ProblemaFrecuenteConfig | null }>({ abierta: false, problema: null })
  const activos = data.filter((p) => p.activo).length

  // La lista llega con los activos primero y en su orden: mover es cambiar
  // dos vecinos de lugar y mandar la lista completa.
  const mover = (indice: number, paso: -1 | 1) => {
    const ids = data.map((p) => p.id)
    const vecino = ids[indice + paso]
    ids[indice + paso] = ids[indice]
    ids[indice] = vecino
    ordenar.mutate(ids)
  }

  return (
    <SectionCard
      title="Problemas frecuentes"
      description={`Los botones de «Pedir ayuda a TIC», en el orden en que los ve el servidor. ${
        isLoading ? 'Se' : `${plural(activos, 'activo', 'activos')}; se`} recomiendan entre 8 y 12 activos.`}
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true, problema: null })}>Nuevo problema</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{ icon: IconBulb, title: 'Sin problemas frecuentes', description: 'Registre los pedidos que más recibe TIC para que el servidor los elija con un toque.' }}>
        <SgthTable
          records={data}
          columns={columnasProblemas((p) => {
            const i = data.indexOf(p)
            return [
              { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierta: true, problema: p }) },
              { label: 'Subir', icon: <IconArrowUp size={14} />, disabled: !p.activo || i === 0 || ordenar.isPending, onClick: () => mover(i, -1) },
              { label: 'Bajar', icon: <IconArrowDown size={14} />, disabled: !p.activo || i >= activos - 1 || ordenar.isPending, onClick: () => mover(i, 1) },
              {
                label: p.activo ? 'Desactivar' : 'Activar',
                icon: p.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
                onClick: () => guardar.mutate({ id: p.id, data: { activo: !p.activo } }),
              },
            ]
          })}
        />
      </DataState>
      <ProblemaFrecuenteModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false, problema: null })} problema={edicion.problema} />
    </SectionCard>
  )
}
