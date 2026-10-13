'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Alert, Button, Group, Skeleton, Tabs } from '@mantine/core'
import { IconDevices, IconEdit, IconHistory, IconId, IconPuzzle, IconQrcode, IconTrashX } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { BienComponentes } from '@/features/inventario-ti/components/BienComponentes'
import { BienDatos } from '@/features/inventario-ti/components/BienDatos'
import { BienHistorial } from '@/features/inventario-ti/components/BienHistorial'
import { ProponerBajaModal } from '@/features/inventario-ti/components/ProponerBajaModal'
import { TONO_ESTADO_BIEN } from '@/features/inventario-ti/constants/estados'
import { useEtiquetas } from '@/features/inventario-ti/hooks/useBajas'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'

export function EquipoDetalleView({ id }: { id: number }) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const { data: bien, isLoading } = useBien(id)
  const [pestana, setPestana] = useState<string | null>('datos')
  const [proponer, setProponer] = useState(false)
  const etiqueta = useEtiquetas()

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
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const puedeProponer = gestiona && !bien.baja && bien.estado_operativo !== 'dado_de_baja'

  return (
    <PageShell>
      <PageHeader
        title={bien.codigo_institucional}
        description={[bien.tipo?.nombre, bien.subtipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' · ')}
        estado={<StatusBadge tone={TONO_ESTADO_BIEN[bien.estado_operativo]}>{bien.estado_etiqueta}</StatusBadge>}
        // Se llega desde el listado y desde la ficha de otro equipo (sus
        // componentes): volver es volver a donde se estaba.
        onBack={() => router.back()}
        actions={(
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconQrcode size={16} />} loading={etiqueta.isPending}
              onClick={() => etiqueta.mutate({ id: bien.id, codigo: bien.codigo_institucional })}>
              Etiqueta QR
            </Button>
            {puedeProponer && (
              <Button variant="subtle" color="red" leftSection={<IconTrashX size={16} />} onClick={() => setProponer(true)}>
                Proponer baja
              </Button>
            )}
            {gestiona && (
              <Button variant="light" leftSection={<IconEdit size={16} />} onClick={() => router.push(ROUTES.TIC.EQUIPO_EDITAR(bien.id))}>
                Editar
              </Button>
            )}
          </Group>
        )}
      />

      {bien.baja && (
        <Alert color={SEMANTIC_COLOR[bien.baja.estado === 'propuesta' ? 'warning' : 'neutral']} variant="light">
          {bien.baja.estado === 'propuesta'
            ? <>Baja propuesta en el informe <b>{bien.baja.numero}</b> ({bien.baja.motivo}), esperando la aprobación del jefe de TIC.</>
            : <>Dado de baja el {formatFecha(bien.baja.fecha_baja)} con el informe <b>{bien.baja.numero}</b> ({bien.baja.motivo}).</>}
          {' '}Está en la pestaña Historial.
        </Alert>
      )}

      <Tabs value={pestana} onChange={setPestana} keepMounted={false}>
        <Tabs.List>
          <Tabs.Tab value="datos" leftSection={<IconId size={14} />}>Datos</Tabs.Tab>
          <Tabs.Tab value="historial" leftSection={<IconHistory size={14} />}>Historial</Tabs.Tab>
          <Tabs.Tab value="componentes" leftSection={<IconPuzzle size={14} />}>{piezas}</Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="datos" pt="lg"><BienDatos bien={bien} /></Tabs.Panel>
        <Tabs.Panel value="historial" pt="lg"><BienHistorial bien={bien} /></Tabs.Panel>
        <Tabs.Panel value="componentes" pt="lg"><BienComponentes bien={bien} /></Tabs.Panel>
      </Tabs>

      <ProponerBajaModal bien={proponer ? bien : null} onClose={() => setProponer(false)} />
    </PageShell>
  )
}
