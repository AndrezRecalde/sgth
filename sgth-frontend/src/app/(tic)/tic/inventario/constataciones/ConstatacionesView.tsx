'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@mantine/core'
import { IconPlus, IconScan } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { columnasConstataciones } from '@/features/inventario-ti/components/constataciones.columns'
import { NuevaConstatacionModal } from '@/features/inventario-ti/components/NuevaConstatacionModal'
import { useConstataciones } from '@/features/inventario-ti/hooks/useConstataciones'

/**
 * Las constataciones físicas (fase 5.2): cada una es el recorrido de TIC por
 * una ubicación, escaneando las etiquetas con el celular. La hace solo TIC;
 * el auditor las consulta y descarga el acta.
 */
export function ConstatacionesView() {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const [nueva, setNueva] = useState(false)
  const { data = [], isLoading, error } = useConstataciones()

  const abrir = gestiona
    ? <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setNueva(true)}>Nueva constatación</Button>
    : undefined

  return (
    <PageShell>
      <PageHeader
        title="Constatación física"
        description="Recorra una ubicación con el celular y lea las etiquetas: lo que está, lo que estaba en otra parte y lo que no aparece"
        actions={abrir}
      />
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{
          icon: IconScan,
          title: 'Todavía no hay constataciones',
          description: 'Abra una para un piso o una sede y escanee las etiquetas QR de los equipos.',
          action: abrir,
        }}>
        <SgthTable records={data} columns={columnasConstataciones}
          onRowClick={({ record }) => router.push(ROUTES.TIC.CONSTATACION(record.id))} />
      </DataState>
      <NuevaConstatacionModal opened={nueva} onClose={() => setNueva(false)} onCreada={(c) => router.push(ROUTES.TIC.CONSTATACION(c.id))} />
    </PageShell>
  )
}
