'use client'

import Link from 'next/link'
import { Anchor, Button, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconCalendarCheck, IconClipboardCheck, IconClockExclamation, IconPresentation } from '@tabler/icons-react'
import { SectionCard, StatCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { formatFechaHora } from '@/lib/fecha'
import type { TableroPrestamos as Datos } from '../services/tableroService'

type Fila = Datos['lista_vencidos'][number]

/** Las entregas son de hoy: basta la hora. */
const soloHora = (iso: string) => new Date(iso).toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })

function ListaPrestamos({ filas, vacio, cuando }: { filas: Fila[]; vacio: string; cuando: (iso: string) => string }) {
  if (filas.length === 0) return <Text size="sm" c="dimmed">{vacio}</Text>

  return (
    <Stack gap="sm">
      {filas.map((p) => (
        <Group key={p.id} justify="space-between" wrap="nowrap" gap="xs">
          <Stack gap={0} style={{ minWidth: 0 }}>
            <Anchor size="sm" fw={600} component={Link} href={ROUTES.TIC.PRESTAMO(p.id)}>{p.folio}</Anchor>
            <Text size="xs" c="dimmed" lineClamp={1}>{p.servidor ?? '—'}</Text>
          </Stack>
          {p.hora && <Text size="xs" c="dimmed" ta="right">{cuando(p.hora)}</Text>}
        </Group>
      ))}
    </Stack>
  )
}

/** Los préstamos de equipos en el tablero: lo que hay que aprobar, entregar y recuperar. */
export function TableroPrestamos({ datos }: { datos: Datos }) {
  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }} spacing="md">
        <StatCard label="Por aprobar" value={datos.por_aprobar} icon={IconClipboardCheck} tone={datos.por_aprobar ? 'warning' : undefined}
          hint="Solicitudes del Portal" />
        <StatCard label="Por entregar hoy" value={datos.por_entregar_hoy} icon={IconCalendarCheck}
          hint={datos.reservados === 1 ? '1 reservado en total' : `${datos.reservados} reservados en total`} />
        <StatCard label="En curso" value={datos.en_curso} icon={IconPresentation} hint="Equipos fuera de TIC" />
        <StatCard label="Vencidos" value={datos.vencidos} icon={IconClockExclamation} tone={datos.vencidos ? 'danger' : undefined}
          hint="Pasó la hora de devolución" />
      </SimpleGrid>

      <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
        <SectionCard title="Sin devolver" description="Los que pasaron la hora de devolución, del más atrasado."
          actions={<Button variant="subtle" size="xs" component={Link} href={ROUTES.TIC.PRESTAMOS}>Ir a la bandeja</Button>}>
          <ListaPrestamos filas={datos.lista_vencidos} vacio="Todos los préstamos en curso están en plazo." cuando={(h) => `Debía volver el ${formatFechaHora(h)}`} />
        </SectionCard>
        <SectionCard title="Entregas de hoy" description="Reservas que se retiran hoy."
          actions={<Button variant="subtle" size="xs" component={Link} href={ROUTES.TIC.PRESTAMOS_AGENDA}>Ver la agenda</Button>}>
          <ListaPrestamos filas={datos.entregas_hoy} vacio="No hay entregas previstas para hoy." cuando={(h) => `A las ${soloHora(h)}`} />
        </SectionCard>
      </SimpleGrid>
    </Stack>
  )
}
