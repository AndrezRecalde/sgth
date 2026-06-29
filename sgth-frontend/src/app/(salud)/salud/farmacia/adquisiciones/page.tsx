'use client'

import { useState } from 'react'
import { Stack, Tabs, Card, Alert, Text } from '@mantine/core'
import {
  IconShoppingCart, IconList, IconCheck,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { AdquisicionForm } from
  '@/features/dispensario/components/AdquisicionForm'
import { SubirDocumentoModal } from
  '@/features/dispensario/components/SubirDocumentoModal'
import { getAdquisicionesColumns } from
  '@/features/dispensario/components/adquisiciones.columns'
import { useAdquisiciones } from
  '@/features/dispensario/hooks/useAdquisicion'
import { useDisclosure } from '@mantine/hooks'
import type { Adquisicion } from
  '@/features/dispensario/services/adquisicionService'

function NuevaAdquisicion({
  onCreada,
}: {
  onCreada: (a: Adquisicion) => void
}) {
  const [creada, setCreada] = useState<Adquisicion | null>(null)

  if (creada) {
    return (
      <Card withBorder radius="lg" p="lg">
        <Stack gap="md" align="center">
          <Alert
            icon={<IconCheck size={16} />}
            color="emerald"
            variant="light"
            w="100%"
          >
            <Text size="sm" fw={600} ta="center">
              Adquisición {creada.folio} registrada exitosamente
            </Text>
          </Alert>
          <Card.Section
            p="sm"
            onClick={() => setCreada(null)}
            style={{ cursor: 'pointer', textAlign: 'center' }}
          >
            <Text size="sm" c="emerald" fw={600}>
              Registrar otra adquisición
            </Text>
          </Card.Section>
        </Stack>
      </Card>
    )
  }

  return (
    <AdquisicionForm
      onCreada={(a) => { setCreada(a); onCreada(a) }}
    />
  )
}

function HistorialAdquisiciones() {
  const [page, setPage] = useState(1)
  const [adquisicionSel, setAdquisicionSel] =
    useState<Adquisicion | null>(null)
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  const { data, isLoading } = useAdquisiciones({
    page, per_page: 15,
  })

  const adquisiciones = data?.data ?? []

  const columns = getAdquisicionesColumns({
    onVerDetalle: () => {},
    onSubirDocumento: (a) => {
      setAdquisicionSel(a)
      abrirModal()
    },
  })

  return (
    <Stack gap="md">
      <SgthTable
        records={adquisiciones}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
        totalRecords={data?.total ?? 0}
        recordsPerPage={15}
        page={page}
        onPageChange={setPage}
      />

      <SubirDocumentoModal
        opened={modalOpened}
        onClose={() => { setAdquisicionSel(null); cerrarModal() }}
        adquisicion={adquisicionSel}
      />
    </Stack>
  )
}

export default function AdquisicionesPage() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Adquisiciones de Medicamentos"
        subtitle="Registro de compras y donaciones con respaldo documental"
        icon={<IconShoppingCart size={24} />}
      />

      <Tabs defaultValue="nueva">
        <Tabs.List>
          <Tabs.Tab
            value="nueva"
            leftSection={<IconShoppingCart size={14} />}
          >
            Nueva adquisición
          </Tabs.Tab>
          <Tabs.Tab
            value="historial"
            leftSection={<IconList size={14} />}
          >
            Historial
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="nueva" pt="md">
          <NuevaAdquisicion onCreada={() => {}} />
        </Tabs.Panel>

        <Tabs.Panel value="historial" pt="md">
          <HistorialAdquisiciones />
        </Tabs.Panel>
      </Tabs>
    </Stack>
  )
}
