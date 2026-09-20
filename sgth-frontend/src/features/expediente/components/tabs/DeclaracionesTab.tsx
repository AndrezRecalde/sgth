'use client'

import { useState } from 'react'
import { Button, Group, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconDownload, IconFileDescription, IconPlus } from '@tabler/icons-react'
import { DataState, SgthTable, notificar } from '@/components/ui'
import { abrirArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { useDeclaraciones } from '../../hooks/useDeclaraciones'
import { useDeclaracionMutations } from '../../hooks/useDeclaracionMutations'
import { declaracionService } from '../../services/declaracionService'
import { getDeclaracionesColumns } from '../declaraciones.columns'
import { DeclaracionModal } from '../DeclaracionModal'
import { ExportarDeclaracionesModal } from '../ExportarDeclaracionesModal'
import type { DeclaracionJuramentada } from '@/types/api'

interface Props { servidorId: number }

export function DeclaracionesTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [exportarOpened, { open: abrirExportar, close: cerrarExportar }] = useDisclosure(false)
  const [editItem, setEditItem] = useState<DeclaracionJuramentada | null>(null)
  const { data: declaraciones = [], isLoading, error } = useDeclaraciones(servidorId)
  const { eliminar } = useDeclaracionMutations(servidorId)

  const verDocumento = (id: number) =>
    declaracionService
      .documento(servidorId, id)
      .then(abrirArchivo)
      .catch((e) => notificar.error('No se pudo abrir el documento', getApiErrorMessage(e)))

  const columns = getDeclaracionesColumns({
    onVerDocumento: verDocumento,
    onEdit: (item) => { setEditItem(item); open() },
    onDelete: (id) => eliminar.mutate(id),
  })

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Button size="xs" variant="light"
          leftSection={<IconDownload size={14} />} onClick={abrirExportar}>
          Exportar
        </Button>
        <Button size="xs" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Nueva declaración
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        empty={declaraciones.length === 0}
        skeletonRows={3}
        emptyProps={{
          icon: IconFileDescription,
          title: 'Sin declaraciones juramentadas',
          description: 'Registra las declaraciones juramentadas del servidor.',
        }}
      >
        <SgthTable records={declaraciones} columns={columns} minHeight={100} />
      </DataState>

      <DeclaracionModal
        key={editItem?.id ?? 'nueva'}
        opened={opened}
        onClose={() => { setEditItem(null); close() }}
        servidorId={servidorId}
        initialValues={editItem}
      />
      <ExportarDeclaracionesModal
        opened={exportarOpened}
        onClose={cerrarExportar}
        servidorId={servidorId}
      />
    </Stack>
  )
}
