'use client'

import { useRouter } from 'next/navigation'
import { Anchor, Stack, Text } from '@mantine/core'
import { SectionCard, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import type { BienInformatico } from '../services/tipos'
import { columnasComponentes } from './historial.columns'

/**
 * Las piezas con código propio (el monitor de un equipo «completo») o el
 * equipo del que este es pieza. Se dan de baja por separado.
 */
export function BienComponentes({ bien }: { bien: BienInformatico }) {
  const router = useRouter()
  const padre = bien.padre

  if (padre) {
    return (
      <SectionCard title="Es componente de">
        <Stack gap={4}>
          <Anchor onClick={() => router.push(ROUTES.TIC.EQUIPO(padre.id))}>{padre.codigo_institucional}</Anchor>
          <Text size="sm" c="dimmed">{[padre.tipo, padre.modelo].filter(Boolean).join(' · ')}</Text>
        </Stack>
      </SectionCard>
    )
  }

  return (
    <SectionCard title="Componentes">
      <SgthTable
        records={bien.componentes ?? []}
        columns={columnasComponentes}
        minHeight={120}
        noRecordsText="Este equipo no tiene componentes con código propio."
        onRowClick={({ record }) => router.push(ROUTES.TIC.EQUIPO(record.id))}
      />
    </SectionCard>
  )
}
