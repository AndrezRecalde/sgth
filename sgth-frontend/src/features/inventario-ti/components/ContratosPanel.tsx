'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconEdit, IconFileCertificate, IconPlus, IconTrash } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, confirmar } from '@/components/ui'
import { useCatalogoMutations, useContratosVigencia } from '../hooks/useCatalogosInventario'
import { catalogosService } from '../services/catalogosService'
import type { ContratoVigencia } from '../services/tipos'
import { columnasContratos } from './catalogos.columns'
import { ContratoModal } from './ContratoModal'

/**
 * Los contratos de vigencia tecnológica: el proveedor mantiene los equipos de
 * un proceso de compra durante unos años, con un cronograma.
 */
export function ContratosPanel() {
  const { data = [], isLoading, error } = useContratosVigencia()
  const { eliminar } = useCatalogoMutations('contrato', catalogosService.contrato)
  const [edicion, setEdicion] = useState<{ abierto: boolean; contrato: ContratoVigencia | null }>({ abierto: false, contrato: null })

  return (
    <SectionCard
      title="Contratos de vigencia tecnológica"
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierto: true, contrato: null })}>Nuevo contrato</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{
          icon: IconFileCertificate,
          title: 'Sin contratos de vigencia',
          description: 'Registre el proceso de compra, el proveedor y el cronograma de mantenimientos.',
        }}>
        <SgthTable
          records={data}
          columns={columnasContratos((c) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierto: true, contrato: c }) },
            {
              label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', disabled: (c.bienes_count ?? 0) > 0,
              onClick: () => confirmar({
                title: 'Eliminar contrato', message: <>Se eliminará el contrato <b>{c.proceso}</b> y su cronograma.</>,
                destructiva: true, onConfirm: () => eliminar.mutate(c.id),
              }),
            },
          ])}
          minHeight={160}
        />
      </DataState>
      <ContratoModal opened={edicion.abierto} onClose={() => setEdicion({ abierto: false, contrato: null })} contrato={edicion.contrato} />
    </SectionCard>
  )
}
