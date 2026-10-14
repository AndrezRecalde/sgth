'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@mantine/core'
import { IconCalendarCheck, IconPlus } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { CampaniaModal } from '@/features/inventario-ti/components/CampaniaModal'
import { columnasCampanias } from '@/features/inventario-ti/components/campanias.columns'
import { useCampanias } from '@/features/inventario-ti/hooks/useCampanias'

/**
 * El plan de mantenimiento preventivo (fase 5.1), como el «Planificador de
 * proyectos» del Excel de TIC: cada campaña es un piso o una sede, con su
 * técnico, su período y su avance. Primero las abiertas.
 */
export function CampaniasView() {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const [nueva, setNueva] = useState(false)
  const { data = [], isLoading, error } = useCampanias()

  const crear = gestiona
    ? <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setNueva(true)}>Nueva campaña</Button>
    : undefined

  return (
    <PageShell>
      <PageHeader
        title="Plan preventivo"
        description="Campañas de mantenimiento preventivo por piso o sede: los equipos, el técnico y cuánto falta"
        actions={crear}
      />
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{
          icon: IconCalendarCheck,
          title: 'Todavía no hay campañas',
          description: 'Cree una para un piso o una sede: entran los equipos que el sistema tiene ahí.',
          action: crear,
        }}>
        <SgthTable records={data} columns={columnasCampanias}
          onRowClick={({ record }) => router.push(ROUTES.TIC.CAMPANIA(record.id))} />
      </DataState>
      <CampaniaModal opened={nueva} onClose={() => setNueva(false)} onCreada={(c) => router.push(ROUTES.TIC.CAMPANIA(c.id))} />
    </PageShell>
  )
}
