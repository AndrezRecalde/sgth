'use client'

import { useState } from 'react'
import { Button, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
import { IconBriefcase, IconPlus } from '@tabler/icons-react'
import { DataState, SgthTable, Toolbar, confirmar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import type { Cargo } from '@/types/api'
import { useCargos } from '../hooks/useCargos'
import { useCargoMutations } from '../hooks/useCargoMutations'
import { getCargoColumns } from './cargo.columns'
import { CargoModal } from './CargoModal'

// El texto escrito entraba directo en la clave de consulta, así que cada tecla
// pedía la lista de cargos entera y solo importaba la última.
const RETARDO_BUSQUEDA_MS = 300

export function CargosTab() {
  // El catálogo se consulta con sesión; crearlo o cambiarlo pide
  // `gestionar-puestos`, igual que en el backend.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-puestos')
  const filtro = useContainedInput('sm')

  const [busqueda, setBusqueda] = useState('')
  const [busquedaConRetardo] = useDebouncedValue(busqueda, RETARDO_BUSQUEDA_MS)
  const [enEdicion, setEnEdicion] = useState<Cargo | null>(null)
  const [modalAbierto, modal] = useDisclosure(false)

  const { eliminar } = useCargoMutations()
  const { data: cargos = [], isLoading, error } = useCargos(
    busquedaConRetardo ? { search: busquedaConRetardo } : undefined,
  )

  const abrir = (cargo: Cargo | null) => {
    setEnEdicion(cargo)
    modal.open()
  }

  const nuevoCargo = puedeGestionar && (
    <Button leftSection={<IconPlus size={16} />} variant="light" onClick={() => abrir(null)}>
      Nuevo cargo
    </Button>
  )

  const columnas = getCargoColumns({
    puedeGestionar,
    alEditar: abrir,
    alEliminar: (cargo) => confirmar({
      title: 'Eliminar cargo',
      message: <>Se eliminará el cargo <b>{cargo.nombre}</b>. No se puede deshacer.</>,
      destructiva: true,
      onConfirm: () => eliminar.mutate(cargo.id),
    }),
  })

  return (
    <Stack gap="md">
      <Toolbar actions={nuevoCargo}>
        <TextInput
          label="Cargo"
          placeholder="Buscar cargo…"
          {...filtro}
          value={busqueda}
          onChange={(e) => setBusqueda(e.currentTarget.value)}
          style={{ minWidth: 280 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!cargos.length}
        emptyProps={{
          icon: IconBriefcase,
          title: 'Sin cargos',
          description: busqueda
            ? 'Ningún cargo coincide con la búsqueda. Pruebe con otra palabra.'
            : 'Aún no hay cargos en el catálogo. Cree el primero con «Nuevo cargo».',
          action: busqueda ? undefined : nuevoCargo || undefined,
        }}
      >
        <SgthTable records={cargos} columns={columnas} minHeight={200} />
      </DataState>

      <CargoModal
        opened={modalAbierto}
        onClose={() => { setEnEdicion(null); modal.close() }}
        cargo={enEdicion}
      />
    </Stack>
  )
}
