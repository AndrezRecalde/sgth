'use client'

import { useState } from 'react'
import { Button, Select, TextInput } from '@mantine/core'
import { IconSearch, IconTruckDelivery } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUbicaciones } from '../hooks/useCatalogosInventario'
import { useUbicacionesMutations, useUbicacionesPorUnidad } from '../hooks/useUbicaciones'
import { MudanzaModal } from './MudanzaModal'

/**
 * Dónde suele estar cada unidad. Solo se propone al registrar o entregar un
 * equipo: la ubicación es del equipo. Si una unidad cambia de piso, la
 * mudanza mueve sus equipos en bloque.
 */
export function UbicacionesPorUnidadPanel({ puedeEditar }: { puedeEditar: boolean }) {
  const compacto = useContainedInput('sm')
  const { data = [], isLoading, error } = useUbicacionesPorUnidad()
  const { data: ubicaciones = [] } = useUbicaciones()
  const { fijar } = useUbicacionesMutations()
  const [busqueda, setBusqueda] = useState('')
  const [mudanza, setMudanza] = useState(false)

  const opciones = ubicaciones.map((u) => ({ value: String(u.id), label: u.nombre }))
  const filas = data.filter((u) => u.unidad.toLowerCase().includes(busqueda.trim().toLowerCase()))

  return (
    <SectionCard
      title="Ubicación habitual de cada unidad"
      description="Se propone al registrar o entregar un equipo. Cambiarla no mueve ningún equipo: para eso está la mudanza."
      actions={puedeEditar
        ? <Button variant="light" leftSection={<IconTruckDelivery size={16} />} onClick={() => setMudanza(true)}>Mudanza de una unidad</Button>
        : undefined}
    >
      <TextInput placeholder="Buscar unidad" leftSection={<IconSearch size={14} />} mb="sm" {...compacto}
        value={busqueda} onChange={(e) => setBusqueda(e.currentTarget.value)} />
      <DataState loading={isLoading} error={error}>
        <SgthTable
          idAccessor="unidad_id"
          records={filas}
          noRecordsText="Ninguna unidad coincide."
          columns={[
            { accessor: 'unidad', title: 'Unidad' },
            {
              accessor: 'ubicacion_id',
              title: 'Ubicación habitual',
              width: 300,
              render: (u) => (
                <Select aria-label={`Ubicación habitual de ${u.unidad}`} placeholder="Sin definir" clearable searchable size="xs"
                  disabled={!puedeEditar} data={opciones} value={u.ubicacion_id ? String(u.ubicacion_id) : null}
                  onChange={(v) => fijar.mutate({ unidadId: u.unidad_id, ubicacionId: v ? Number(v) : null })} />
              ),
            },
          ]}
        />
      </DataState>
      <MudanzaModal opened={mudanza} onClose={() => setMudanza(false)} unidades={data} ubicaciones={opciones} />
    </SectionCard>
  )
}
