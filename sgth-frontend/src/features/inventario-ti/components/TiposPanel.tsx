'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconCategory, IconEdit, IconListDetails, IconPlus, IconTrash } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, confirmar } from '@/components/ui'
import { useCatalogoMutations, useTiposBien } from '../hooks/useCatalogosInventario'
import { catalogosService } from '../services/catalogosService'
import type { TipoBien } from '../services/tipos'
import { columnasTipos } from './catalogos.columns'
import { SubtiposDrawer } from './SubtiposDrawer'
import { TipoModal } from './TipoModal'

/** Tipos de equipo, sus subtipos y los años de vida útil de cada uno. */
export function TiposPanel() {
  const { data = [], isLoading, error } = useTiposBien()
  const { eliminar } = useCatalogoMutations('tipo', catalogosService.tipo)
  const [edicion, setEdicion] = useState<{ abierto: boolean; tipo: TipoBien | null }>({ abierto: false, tipo: null })
  const [subtiposDe, setSubtiposDe] = useState<number | null>(null)

  return (
    <SectionCard
      title="Tipos de equipo"
      description="Los años de vida útil de cada tipo dan la fecha en que el equipo la cumple, contada desde su compra."
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierto: true, tipo: null })}>Nuevo tipo</Button>}
    >
      <DataState
        loading={isLoading}
        error={error}
        empty={!data.length}
        emptyProps={{ icon: IconCategory, title: 'Sin tipos de equipo', description: 'Registre el primero: computador, portátil, proyector…' }}
      >
        <SgthTable
          records={data}
          columns={columnasTipos((t) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierto: true, tipo: t }) },
            { label: 'Subtipos', icon: <IconListDetails size={14} />, onClick: () => setSubtiposDe(t.id) },
            {
              label: 'Eliminar',
              icon: <IconTrash size={14} />,
              color: 'red',
              disabled: (t.bienes_count ?? 0) > 0,
              onClick: () => confirmar({
                title: 'Eliminar tipo de equipo',
                message: <>Se eliminará el tipo <b>{t.nombre}</b>.</>,
                destructiva: true,
                onConfirm: () => eliminar.mutate(t.id),
              }),
            },
          ])}
          minHeight={160}
        />
      </DataState>
      <TipoModal opened={edicion.abierto} onClose={() => setEdicion({ abierto: false, tipo: null })} tipo={edicion.tipo} />
      <SubtiposDrawer tipoId={subtiposDe} onClose={() => setSubtiposDe(null)} />
    </SectionCard>
  )
}
