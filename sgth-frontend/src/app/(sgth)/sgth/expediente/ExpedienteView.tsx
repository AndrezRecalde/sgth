'use client'

import { useState } from 'react'
import { Box, Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFolder, IconUserPlus } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { ServidorToolbar } from '@/features/expediente/components/ServidorToolbar'
import { ServidorTable } from '@/features/expediente/components/ServidorTable'
import { ServidorModal } from '@/features/expediente/components/ServidorModal'
import { ServidorDetail } from '@/features/expediente/components/ServidorDetail'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import type { ServidorConRelaciones, EstadoContrato } from '@/types/api'

export function ExpedienteView() {
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [estado, setEstado] = useState<string | null>(null)

  const [modalOpened,  { open: openModal,  close: closeModal  }] = useDisclosure(false)
  const [detailOpened, { open: openDetail, close: closeDetail }] = useDisclosure(false)

  const [editServidor, setEditServidor] =
    useState<ServidorConRelaciones | null>(null)
  const [viewServidor, setViewServidor] =
    useState<ServidorConRelaciones | null>(null)

  const { data, isLoading } = useServidores({
    page,
    per_page: 15,
    search:   search || undefined,
    estado:   (estado as EstadoContrato) || undefined,
  })

  const servidores = (data?.data ?? []) as ServidorConRelaciones[]

  const handleView = (s: ServidorConRelaciones) => {
    setViewServidor(s)
    openDetail()
  }

  const handleEdit = (s: ServidorConRelaciones) => {
    setEditServidor(s)
    openModal()
  }

  const handleNuevo = () => {
    setEditServidor(null)
    openModal()
  }

  return (
    <Box>
      <PageHeader
        title="Expediente Digital"
        subtitle="Gestión de servidores públicos del GAD Provincial de Esmeraldas"
        icon={<IconFolder size={28} />}
      />

      <Group justify="flex-end" mb="md">
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconUserPlus size={16} />}
          onClick={handleNuevo}
        >
          Nuevo servidor
        </Button>
      </Group>

      <ServidorToolbar
        onSearch={setSearch}
        onEstadoChange={setEstado}
      />

      {!isLoading && servidores.length === 0 ? (
        <EmptyState
          icon={IconFolder}
          title="No hay servidores registrados"
          description="Comienza registrando el primer servidor del GAD."
          action={
            <Button color="emerald" variant="light"
              leftSection={<IconUserPlus size={14} />}
              onClick={handleNuevo}>
              Nuevo servidor
            </Button>
          }
        />
      ) : (
        <ServidorTable
          data={servidores}
          isLoading={isLoading}
          total={data?.total ?? 0}
          page={page}
          onPageChange={setPage}
          onView={handleView}
          onEdit={handleEdit}
        />
      )}

      <ServidorModal
        opened={modalOpened}
        onClose={() => { setEditServidor(null); closeModal() }}
        servidor={editServidor}
      />
      <ServidorDetail
        opened={detailOpened}
        onClose={closeDetail}
        servidor={viewServidor}
        onEdit={handleEdit}
      />
    </Box>
  )
}
