'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconCubePlus } from '@tabler/icons-react'
import { PageHeader, PageShell, confirmar } from '@/components/ui'
import { DirectorioTable } from '@/features/estructura/components/DirectorioTable'
import { DirectorioToolbar } from '@/features/estructura/components/DirectorioToolbar'
import { ExtensionModal } from '@/features/estructura/components/ExtensionModal'
import { useDirectorio } from '@/features/estructura/hooks/useDirectorio'
import { useExtensionMutations } from '@/features/estructura/hooks/useExtensionMutations'
import type { ExtensionConRelaciones } from '@/types/api'

export function DirectorioView() {
  const [search, setSearch] = useState('')
  const [unidadId, setUnidadId] = useState<string | null>(null)

  const [modalAbierto, modal] = useDisclosure(false)
  const [editExtension, setEditExtension] = useState<ExtensionConRelaciones | null>(null)

  const { eliminar } = useExtensionMutations()

  const { data: directorio = [], isLoading } = useDirectorio({
    search: search || undefined,
    unidad_administrativa_id: unidadId ? Number(unidadId) : undefined,
  })

  const editar = (ext: ExtensionConRelaciones) => {
    setEditExtension(ext)
    modal.open()
  }

  const cerrar = () => {
    setEditExtension(null)
    modal.close()
  }

  return (
    <PageShell>
      <PageHeader
        title="Directorio telefónico"
        description="Extensiones por unidad administrativa"
        actions={
          <Button
            color="emerald"
            leftSection={<IconCubePlus size={16} />}
            variant="light"
            onClick={modal.open}
          >
            Nueva extensión
          </Button>
        }
      />

      <DirectorioToolbar
        onSearch={setSearch}
        onUnidadChange={setUnidadId}
        onClear={() => {
          setSearch('')
          setUnidadId(null)
        }}
      />

      <DirectorioTable
        data={directorio}
        isLoading={isLoading}
        onEdit={editar}
        onDelete={(ext) =>
          confirmar({
            title: 'Eliminar extensión',
            message: (
              <>
                Se eliminará la extensión <b>{ext.numero_extension ?? ''}</b>. No
                se puede deshacer.
              </>
            ),
            destructiva: true,
            onConfirm: () => eliminar.mutate(ext.id),
          })
        }
      />

      <ExtensionModal
        opened={modalAbierto}
        onClose={cerrar}
        extension={editExtension}
      />
    </PageShell>
  )
}
