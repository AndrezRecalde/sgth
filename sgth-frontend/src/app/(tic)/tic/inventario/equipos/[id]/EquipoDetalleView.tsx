'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Skeleton, Tabs } from '@mantine/core'
import { IconDevices, IconEdit, IconHistory, IconId, IconPuzzle } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { BienComponentes } from '@/features/inventario-ti/components/BienComponentes'
import { BienDatos } from '@/features/inventario-ti/components/BienDatos'
import { BienHistorial } from '@/features/inventario-ti/components/BienHistorial'
import { TONO_ESTADO_BIEN } from '@/features/inventario-ti/constants/estados'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'

export function EquipoDetalleView({ id }: { id: number }) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const { data: bien, isLoading } = useBien(id)
  const [pestana, setPestana] = useState<string | null>('datos')

  if (isLoading) {
    return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  }

  if (!bien) {
    return (
      <PageShell>
        <PageHeader title="Ficha del equipo" backHref={ROUTES.TIC.EQUIPOS} />
        <EmptyState
          icon={IconDevices}
          title="Equipo no encontrado"
          description="No existe o se retiró del inventario. Vuelva al listado y búsquelo allí."
        />
      </PageShell>
    )
  }

  const piezas = bien.padre ? 'Equipo principal' : `Componentes (${bien.componentes_count})`

  return (
    <PageShell>
      <PageHeader
        title={bien.codigo_institucional}
        description={[bien.tipo?.nombre, bien.subtipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' · ')}
        estado={<StatusBadge tone={TONO_ESTADO_BIEN[bien.estado_operativo]}>{bien.estado_etiqueta}</StatusBadge>}
        // Se llega desde el listado y desde la ficha de otro equipo (sus
        // componentes): volver es volver a donde se estaba.
        onBack={() => router.back()}
        actions={hasPermiso('gestionar-inventario-ti') ? (
          <Button variant="light" leftSection={<IconEdit size={16} />} onClick={() => router.push(ROUTES.TIC.EQUIPO_EDITAR(bien.id))}>
            Editar
          </Button>
        ) : undefined}
      />

      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="datos" leftSection={<IconId size={14} />}>Datos</Tabs.Tab>
          <Tabs.Tab value="historial" leftSection={<IconHistory size={14} />}>Custodia y mantenimientos</Tabs.Tab>
          <Tabs.Tab value="componentes" leftSection={<IconPuzzle size={14} />}>{piezas}</Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="datos" pt="lg"><BienDatos bien={bien} /></Tabs.Panel>
        <Tabs.Panel value="historial" pt="lg"><BienHistorial bienId={bien.id} /></Tabs.Panel>
        <Tabs.Panel value="componentes" pt="lg"><BienComponentes bien={bien} /></Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
