'use client'

import { Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconCalendarExclamation, IconId, IconIdOff, IconPrinter, IconRefresh, IconTransferIn } from '@tabler/icons-react'
import { SectionCard, StatCard, StatusBadge } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { formatFecha } from '@/lib/fecha'
import type { TableroCredenciales as Datos } from '../services/tableroService'
import { CifraEnlace } from './CifraEnlace'

/** Días que faltan hasta una fecha (YYYY-MM-DD), contando desde hoy. */
function diasHasta(fecha: string): number {
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  const [a, m, d] = fecha.split('-').map(Number)

  return Math.round((new Date(a, m - 1, d).getTime() - hoy.getTime()) / 86_400_000)
}

/**
 * Las credenciales en el Inicio: lo que hay que imprimir, reimprimir y
 * recibir, y las que vencen pronto con su contrato. Cada cifra abre
 * Credenciales ya filtrada.
 */
export function TableroCredenciales({ datos }: { datos: Datos }) {
  // Ámbar si a Comunicación le toca hacer algo; verde si no queda nada.
  const pendiente = (n: number): SemanticTone => (n ? 'warning' : 'success')

  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 2, lg: 3 }} spacing="md">
        <StatCard label="Credenciales vigentes" value={datos.vigentes} icon={IconId} />
        <CifraEnlace label="Por imprimir" value={datos.sin_imprimir} icon={IconPrinter} tone={pendiente(datos.sin_imprimir)}
          hint="Vigentes que nunca se imprimieron" href={ROUTES.COMUNICACION.CREDENCIALES_EN('sin_imprimir')} />
        <CifraEnlace label="Por reimprimir" value={datos.desactualizadas} icon={IconRefresh} tone={pendiente(datos.desactualizadas)}
          hint="La tarjeta ya no dice lo que dice el expediente" href={ROUTES.COMUNICACION.CREDENCIALES_EN('desactualizada')} />
        {/* Sin enlace: quiénes son está abajo, en «Vencen pronto». */}
        <StatCard label="Por vencer" value={datos.por_vencer} icon={IconCalendarExclamation} tone={datos.por_vencer ? 'warning' : undefined}
          hint={`Con el contrato, en los próximos ${datos.dias_por_vencer} días`} />
        <CifraEnlace label="Vencidas" value={datos.vencidas} icon={IconIdOff} tone={datos.vencidas ? 'danger' : undefined}
          hint="Terminó el contrato y el vínculo sigue abierto" href={ROUTES.COMUNICACION.CREDENCIALES_EN('vencida')} />
        <CifraEnlace label="Tarjetas por recibir" value={datos.por_recibir} icon={IconTransferIn} tone={pendiente(datos.por_recibir)}
          hint="De quienes ya salieron" href={ROUTES.COMUNICACION.CREDENCIALES_EN('por_recibir')} />
      </SimpleGrid>

      <SectionCard title="Vencen pronto" description={`Las que vencen con el contrato en los próximos ${datos.dias_por_vencer} días. Si el contrato se prorroga, la misma credencial sigue valiendo.`}>
        {datos.proximas.length === 0 ? (
          <Text size="sm" c="dimmed">Ninguna credencial vence en los próximos {datos.dias_por_vencer} días.</Text>
        ) : (
          <Stack gap="sm">
            {datos.proximas.map((p) => {
              const dias = diasHasta(p.valida_hasta)

              return (
                <Group key={p.servidor_id} justify="space-between" wrap="nowrap" gap="xs">
                  <Stack gap={0} style={{ minWidth: 0 }}>
                    <Text size="sm" fw={600} lineClamp={1}>{p.nombre}</Text>
                    <Text size="xs" c="dimmed" lineClamp={1}>
                      {p.unidad ?? 'Sin unidad'} · hasta el {formatFecha(p.valida_hasta)}
                    </Text>
                  </Stack>
                  <StatusBadge tone={dias <= 7 ? 'danger' : 'warning'}>{dias === 0 ? 'Hoy' : `En ${dias} días`}</StatusBadge>
                </Group>
              )
            })}
            {datos.por_vencer > datos.proximas.length && (
              <Text size="xs" c="dimmed">Y {datos.por_vencer - datos.proximas.length} más.</Text>
            )}
          </Stack>
        )}
      </SectionCard>
    </Stack>
  )
}
