'use client'

import { Alert, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconClockExclamation, IconPill, IconTrashX } from '@tabler/icons-react'
import { SectionCard, StatusBadge } from '@/components/ui'
import type { KpisDispensario } from '../../services/kpisService'

interface Props {
  kpis?: KpisDispensario
}

/** Hasta cinco filas de un aviso, con «y N más» si hay más. */
function Filas({ filas }: { filas: string[] }) {
  return (
    <>
      {filas.slice(0, 5).map((f) => <Text key={f} size="xs">{f}</Text>)}
      {filas.length > 5 && <Text size="xs" c="dimmed">y {filas.length - 5} más</Text>}
    </>
  )
}

/** Farmacia: lo despachado, las recetas del período y los avisos de inventario. */
export function TableroFarmacia({ kpis }: Props) {
  const despachados = kpis?.medicamentos_mas_despachados ?? []
  const recetas     = kpis?.recetas_estado
  const alertas     = kpis?.alertas_inventario
  const bajoMinimo  = alertas?.medicamentos_bajo_stock ?? []
  const porCaducar  = alertas?.medicamentos_por_caducar ?? []
  const vencidos    = alertas?.medicamentos_vencidos ?? []
  const sinAvisos   = !bajoMinimo.length && !porCaducar.length && !vencidos.length

  return (
    <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
      <Stack gap="md">
        {/* Se calculaba y no se mostraba: las pendientes de despacho son el
            cuello de botella de Farmacia. */}
        <SectionCard title="Recetas del período" description="Por estado de despacho.">
          <Group gap="sm">
            <StatusBadge tone={recetas?.pendiente ? 'warning' : undefined}>
              {recetas?.pendiente ?? 0} pendientes
            </StatusBadge>
            <StatusBadge tone={recetas?.despachada_parcial ? 'info' : undefined}>
              {recetas?.despachada_parcial ?? 0} parciales
            </StatusBadge>
            <StatusBadge>{recetas?.despachada_completa ?? 0} despachadas</StatusBadge>
            <StatusBadge>{recetas?.anulada ?? 0} anuladas</StatusBadge>
          </Group>
        </SectionCard>

        <SectionCard title="Medicamentos más despachados" description="Unidades entregadas en el período.">
          {despachados.length === 0 ? (
            <Text size="sm" c="dimmed">No se despachó nada en el período.</Text>
          ) : (
            <Stack gap={6}>
              {despachados.map((m) => (
                <Group key={m.nombre} justify="space-between" gap="xs">
                  <Group gap={6} wrap="nowrap" miw={0}>
                    <IconPill size={13} color="var(--mantine-color-slate-6)" />
                    <Text size="xs" lineClamp={1}>{m.nombre}</Text>
                  </Group>
                  <Text size="sm" fw={600}>{m.total_despachado}</Text>
                </Group>
              ))}
            </Stack>
          )}
        </SectionCard>
      </Stack>

      {/* Tres avisos, porque piden tres cosas distintas: retirar, usar antes
          de que caduque y reponer. Lo vencido iba mezclado en «por caducar». */}
      <SectionCard title="Avisos de inventario" description="Hoy, sin importar el período.">
        <Stack gap="sm">
          {sinAvisos && (
            <Text size="sm" c="dimmed">Sin avisos: nada vencido, por caducar ni en el mínimo.</Text>
          )}
          {vencidos.length > 0 && (
            <Alert icon={<IconTrashX size={15} />} color="red" variant="light" p="xs"
              title={`Vencidos: retirar (${vencidos.length})`}>
              <Filas filas={vencidos.map((l) =>
                `${l.nombre} · lote ${l.lote} — ${l.stock} u., venció hace ${Math.abs(l.dias_restantes)} días`)} />
            </Alert>
          )}
          {porCaducar.length > 0 && (
            <Alert icon={<IconClockExclamation size={15} />} color="amber" variant="light" p="xs"
              title={`Por caducar en 60 días (${porCaducar.length})`}>
              <Filas filas={porCaducar.map((l) =>
                `${l.nombre} · lote ${l.lote} — ${l.stock} u., ${l.dias_restantes} días`)} />
            </Alert>
          )}
          {bajoMinimo.length > 0 && (
            <Alert icon={<IconAlertTriangle size={15} />} color="amber" variant="light" p="xs"
              title={`En el mínimo o por debajo (${bajoMinimo.length})`}>
              <Filas filas={bajoMinimo.map((m) => `${m.nombre} — quedan ${m.stock_actual} de ${m.stock_minimo}`)} />
            </Alert>
          )}
        </Stack>
      </SectionCard>
    </SimpleGrid>
  )
}
