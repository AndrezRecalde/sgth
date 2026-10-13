'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { IconEdit, IconPlus, IconTrash } from '@tabler/icons-react'
import { SgthDrawer, SgthTable, confirmar } from '@/components/ui'
import { useCatalogoMutations, useTiposBien } from '../hooks/useCatalogosInventario'
import { catalogosService } from '../services/catalogosService'
import type { TipoBien } from '../services/tipos'
import { columnasSubtipos } from './catalogos.columns'
import { NombreCatalogoModal } from './NombreCatalogoModal'

type Subtipo = NonNullable<TipoBien['subtipos']>[number]

interface Props {
  /** El tipo abierto. Se lee de la consulta y no de una copia: así se ve el subtipo recién creado. */
  tipoId: number | null
  onClose: () => void
}

/** Los subtipos de un tipo: «Todo en uno», «CPU»… del computador. */
export function SubtiposDrawer({ tipoId, onClose }: Props) {
  const { data: tipos } = useTiposBien()
  const tipo = tipos?.find((t) => t.id === tipoId) ?? null
  const { eliminar } = useCatalogoMutations('subtipo', catalogosService.subtipo)
  const [edicion, setEdicion] = useState<{ abierto: boolean; subtipo: Subtipo | null }>({ abierto: false, subtipo: null })

  return (
    <SgthDrawer opened={tipoId !== null} onClose={onClose} title="Subtipos" description={tipo?.nombre} ancho="sm">
      <Stack gap="md">
        <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierto: true, subtipo: null })}>
          Nuevo subtipo
        </Button>
        <SgthTable
          records={tipo?.subtipos ?? []}
          columns={columnasSubtipos((s) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierto: true, subtipo: s }) },
            {
              label: 'Eliminar',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title: 'Eliminar subtipo',
                message: <>Se eliminará el subtipo <b>{s.nombre}</b>. Si algún equipo lo usa, no se podrá.</>,
                destructiva: true,
                onConfirm: () => eliminar.mutate(s.id),
              }),
            },
          ])}
          minHeight={120}
          noRecordsText="Este tipo no tiene subtipos."
        />
      </Stack>
      <NombreCatalogoModal
        opened={edicion.abierto}
        onClose={() => setEdicion({ abierto: false, subtipo: null })}
        catalogo="subtipo"
        registro={edicion.subtipo}
        tipoBienId={tipo?.id}
      />
    </SgthDrawer>
  )
}
