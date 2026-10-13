'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconBuilding, IconEdit, IconPlus, IconTag, IconTrash } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, confirmar } from '@/components/ui'
import { useCatalogoMutations, useMarcas, useUbicaciones } from '../hooks/useCatalogosInventario'
import { catalogosService } from '../services/catalogosService'
import type { Marca, Ubicacion } from '../services/tipos'
import { columnasMarcas, columnasUbicaciones } from './catalogos.columns'
import { NombreCatalogoModal } from './NombreCatalogoModal'
import { UbicacionModal } from './UbicacionModal'

/** Las marcas. Las del Excel las crea la carga inicial; aquí se corrigen y se agregan. */
export function MarcasPanel() {
  const { data = [], isLoading, error } = useMarcas()
  const { eliminar } = useCatalogoMutations('marca', catalogosService.marca)
  const [edicion, setEdicion] = useState<{ abierto: boolean; marca: Marca | null }>({ abierto: false, marca: null })

  return (
    <SectionCard
      title="Marcas"
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierto: true, marca: null })}>Nueva marca</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{ icon: IconTag, title: 'Sin marcas', description: 'Registre la primera: Dell, HP, Lenovo…' }}>
        <SgthTable
          records={data}
          columns={columnasMarcas((m) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierto: true, marca: m }) },
            {
              label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', disabled: (m.bienes_count ?? 0) > 0,
              onClick: () => confirmar({
                title: 'Eliminar marca', message: <>Se eliminará la marca <b>{m.nombre}</b>.</>,
                destructiva: true, onConfirm: () => eliminar.mutate(m.id),
              }),
            },
          ])}
          minHeight={160}
        />
      </DataState>
      <NombreCatalogoModal opened={edicion.abierto} onClose={() => setEdicion({ abierto: false, marca: null })}
        catalogo="marca" registro={edicion.marca} />
    </SectionCard>
  )
}

/** Sedes y pisos donde están los equipos. */
export function UbicacionesPanel() {
  const { data = [], isLoading, error } = useUbicaciones()
  const { eliminar } = useCatalogoMutations('ubicacion', catalogosService.ubicacion)
  const [edicion, setEdicion] = useState<{ abierto: boolean; ubicacion: Ubicacion | null }>({ abierto: false, ubicacion: null })

  return (
    <SectionCard
      title="Ubicaciones"
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierto: true, ubicacion: null })}>Nueva ubicación</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{ icon: IconBuilding, title: 'Sin ubicaciones', description: 'Registre la primera sede o piso.' }}>
        <SgthTable
          records={data}
          columns={columnasUbicaciones((u) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierto: true, ubicacion: u }) },
            {
              label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', disabled: (u.bienes_count ?? 0) > 0,
              onClick: () => confirmar({
                title: 'Eliminar ubicación', message: <>Se eliminará <b>{u.nombre}</b>.</>,
                destructiva: true, onConfirm: () => eliminar.mutate(u.id),
              }),
            },
          ])}
          minHeight={160}
        />
      </DataState>
      <UbicacionModal opened={edicion.abierto} onClose={() => setEdicion({ abierto: false, ubicacion: null })} ubicacion={edicion.ubicacion} />
    </SectionCard>
  )
}
