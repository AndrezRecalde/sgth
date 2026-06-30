'use client'

import { useState } from 'react'
import Link from 'next/link'
import {
  Stack, Group, Button, TextInput,
  ActionIcon,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPill, IconPlus, IconSearch, IconX, IconShoppingCart,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useInventarioMedicinas, useInventarioMutations,
} from '@/features/dispensario/hooks/useInventarioMedicina'
import { MedicinaModal } from
  '@/features/dispensario/components/MedicinaModal'
import { IngresarStockModal } from
  '@/features/dispensario/components/IngresarStockModal'
import { AjustarInventarioModal } from
  '@/features/dispensario/components/AjustarInventarioModal'
import { KardexDrawer } from
  '@/features/dispensario/components/KardexDrawer'
import { getMedicinasColumns } from
  '@/features/dispensario/components/medicinas.columns'
import type { InventarioMedicina } from
  '@/features/dispensario/services/inventarioMedicinaService'

export default function FarmaciaPage() {
  const contained = useContainedInput()
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [query, setQuery]   = useState('')

  const [medicinaSel, setMedicinaSel] =
    useState<InventarioMedicina | null>(null)

  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const [stockOpened,
    { open: abrirStock, close: cerrarStock }] = useDisclosure(false)
  const [ajustarOpened,
    { open: abrirAjustar, close: cerrarAjustar }] = useDisclosure(false)
  const [kardexOpened,
    { open: abrirKardex, close: cerrarKardex }] = useDisclosure(false)

  const { data, isLoading } = useInventarioMedicinas({
    page, per_page: 15, search: query || undefined,
  })
  const { toggleEstado } = useInventarioMutations()

  const medicinas = data?.data ?? []

  const handleBuscar = () => {
    setQuery(search.trim())
    setPage(1)
  }

  const columns = getMedicinasColumns({
    onEditar: (m) => { setMedicinaSel(m); abrirModal() },
    onIngresarStock: (m) => { setMedicinaSel(m); abrirStock() },
    onAjustar: (m) => { setMedicinaSel(m); abrirAjustar() },
    onVerKardex: (m) => { setMedicinaSel(m); abrirKardex() },
    onToggleEstado: (m) => {
      const accion = m.estado ? 'dar de baja' : 'reactivar'
      if (confirm(`¿Deseas ${accion} esta medicina?`)) {
        toggleEstado.mutate(m.id)
      }
    },
  })

  return (
    <Stack gap="md">
      <PageHeader
        title="Farmacia"
        subtitle="Gestión del inventario de medicinas"
        icon={<IconPill size={24} />}
        actions={
          <Button
            component={Link}
            href="/salud/farmacia/adquisiciones"
            variant="light"
            color="blue"
            leftSection={<IconShoppingCart size={14} />}
          >
            Adquisiciones
          </Button>
        }
      />

      <Group justify="space-between">
        <Group gap="xs">
          <TextInput
            placeholder="Buscar por nombre, código o principio activo"
            leftSection={<IconSearch size={14} />}
            {...contained}
            value={search}
            onChange={(e) => setSearch(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') handleBuscar()
            }}
            style={{ width: 320 }}
            rightSection={
              search ? (
                <ActionIcon
                  size="sm" variant="subtle" color="gray"
                  onClick={() => {
                    setSearch(''); setQuery(''); setPage(1)
                  }}
                >
                  <IconX size={12} />
                </ActionIcon>
              ) : null
            }
          />
          <Button
            variant="light"
            color="blue"
            leftSection={<IconSearch size={14} />}
            onClick={handleBuscar}
          >
            Buscar
          </Button>
        </Group>

        <Button
          color="emerald"
          leftSection={<IconPlus size={14} />}
          onClick={() => { setMedicinaSel(null); abrirModal() }}
        >
          Nueva medicina
        </Button>
      </Group>

      <SgthTable
        records={medicinas}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
        totalRecords={data?.total ?? 0}
        recordsPerPage={15}
        page={page}
        onPageChange={setPage}
      />

      <MedicinaModal
        opened={modalOpened}
        onClose={() => { setMedicinaSel(null); cerrarModal() }}
        initialValues={medicinaSel}
      />

      <IngresarStockModal
        opened={stockOpened}
        onClose={() => { setMedicinaSel(null); cerrarStock() }}
        medicina={medicinaSel}
      />

      <AjustarInventarioModal
        opened={ajustarOpened}
        onClose={() => { setMedicinaSel(null); cerrarAjustar() }}
        medicina={medicinaSel}
      />

      <KardexDrawer
        opened={kardexOpened}
        onClose={() => { setMedicinaSel(null); cerrarKardex() }}
        medicina={medicinaSel}
      />
    </Stack>
  )
}
