'use client'

import { useState } from 'react'
import { Box } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFolder } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { ServidorToolbar } from '@/features/expediente/components/ServidorToolbar'
import { ServidorTable } from '@/features/expediente/components/ServidorTable'
import { ServidorModal } from '@/features/expediente/components/ServidorModal'
import { ServidorDetail } from '@/features/expediente/components/ServidorDetail'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import type { ServidorConRelaciones, EstadoContrato } from '@/types/api'

export default function ExpedientePage() {
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [estado, setEstado] = useState<string | null>(null)

  const [modalOpened, { open: openModal, close: closeModal }] =
    useDisclosure(false)
  const [detailOpened, { open: openDetail, close: closeDetail }] =
    useDisclosure(false)

  const [editServidor, setEditServidor] =
    useState<ServidorConRelaciones | null>(null)
  const [viewServidor, setViewServidor] =
    useState<ServidorConRelaciones | null>(null)

  const { data, isLoading } = useServidores({
    page,
    per_page: 15,
    search: search || undefined,
    estado: (estado as EstadoContrato) || undefined,
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

  const handleCloseModal = () => {
    setEditServidor(null)
    closeModal()
  }

  return (
    <Box>
      <PageHeader
        title="Expediente Digital"
        subtitle="Gestión de servidores públicos"
        icon={<IconFolder size={28} />}
      />
      <ServidorToolbar
        onSearch={setSearch}
        onEstadoChange={setEstado}
        onNuevo={handleNuevo}
      />
      <ServidorTable
        data={servidores}
        isLoading={isLoading}
        total={data?.total ?? 0}
        page={page}
        onPageChange={setPage}
        onView={handleView}
        onEdit={handleEdit}
      />
      <ServidorModal
        opened={modalOpened}
        onClose={handleCloseModal}
        servidor={editServidor}
      />
      <ServidorDetail
        opened={detailOpened}
        onClose={closeDetail}
        servidor={viewServidor}
      />
    </Box>
  )
}
