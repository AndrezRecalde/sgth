'use client'

import { Skeleton, Stack, Text, Timeline } from '@mantine/core'
import { DetailList, SectionHeading, SgthDrawer, StatusBadge, type DetailItem } from '@/components/ui'
import { CONDICION_LABELS, TONO_ESTADO_PRESTAMO } from '../constants/estados'
import type { PrestamoEquipo } from '../services/tipos'
import { momentoCorto } from '../utils/agenda'

/** El estado, o «Vencido» si ya debía volver. */
export function EstadoPrestamoBadge({ prestamo: p }: { prestamo: PrestamoEquipo }) {
  return <StatusBadge tone={p.vencido ? 'danger' : TONO_ESTADO_PRESTAMO[p.estado]}>{p.vencido ? 'Vencido' : p.estado_etiqueta}</StatusBadge>
}

/** Quién hizo cada paso, solo en TIC. */
function responsables(p: PrestamoEquipo): DetailItem[] {
  return [
    { label: 'Solicitante', value: p.servidor ? `${p.servidor.nombre} (C.C. ${p.servidor.cedula})` : '—' },
    { label: 'Unidad', value: p.unidad?.nombre ?? '—' },
    { label: 'Registrado por', value: p.registrado_por ?? '—' },
    { label: p.rechazado_por ? 'Rechazado por' : 'Aprobado por', value: p.rechazado_por ?? p.aprobado_por ?? '—' },
    { label: 'Entregó', value: p.entregado_por ?? '—' },
    { label: 'Recibió', value: p.recibido_por ?? '—' },
  ]
}

/**
 * El detalle de un préstamo: para qué y dónde, las horas previstas y reales,
 * cada equipo con sus accesorios y su condición, y la línea de tiempo. Con
 * `paraTic`, también quién pidió y quién hizo cada paso.
 */
export function PrestamoDetalleCuerpo({ prestamo: p, paraTic = false }: { prestamo: PrestamoEquipo; paraTic?: boolean }) {
  return (
    <Stack gap="lg">
      <DetailList columnas={2} items={[
        ...(paraTic ? responsables(p) : []),
        { label: 'Lo retira', value: momentoCorto(p.inicio_previsto) },
        { label: 'Lo devuelve', value: momentoCorto(p.fin_previsto) },
        { label: 'Retirado', value: momentoCorto(p.entregado_en) },
        { label: 'Devuelto', value: momentoCorto(p.devuelto_en) },
        { label: 'Para qué', value: p.motivo, ancho: true },
        { label: 'Dónde', value: `${p.lugar_uso}${p.fuera_del_edificio ? ' (fuera del edificio)' : ''}`, ancho: true },
        ...(p.motivo_rechazo ? [{ label: 'Motivo del rechazo', value: p.motivo_rechazo, ancho: true }] : []),
        ...(p.motivo_cancelacion ? [{ label: 'Motivo de la cancelación', value: p.motivo_cancelacion, ancho: true }] : []),
        ...(p.observaciones ? [{ label: 'Observaciones', value: p.observaciones, ancho: true }] : []),
      ]} />

      <Stack gap="xs">
        <SectionHeading title="Equipos" />
        {(p.items ?? []).map((i) => (
          <div key={i.id}>
            <Text size="sm" fw={600}>{i.bien?.codigo_institucional} · {[i.bien?.tipo, i.bien?.marca, i.bien?.modelo].filter(Boolean).join(' ')}</Text>
            <Text size="xs" c="dimmed">
              {[
                i.accesorios && `Con ${i.accesorios}`,
                i.condicion_entrega && `Salió ${CONDICION_LABELS[i.condicion_entrega].toLowerCase()}`,
                i.condicion_devolucion && `volvió ${CONDICION_LABELS[i.condicion_devolucion].toLowerCase()}`,
                i.novedad,
              ].filter(Boolean).join(' · ') || 'Todavía no se entrega.'}
            </Text>
          </div>
        ))}
      </Stack>

      {(p.historial ?? []).length > 0 && (
        <Stack gap="xs">
          <SectionHeading title="Línea de tiempo" />
          <Timeline bulletSize={12} lineWidth={2} active={(p.historial ?? []).length - 1}>
            {(p.historial ?? []).map((h, n) => (
              <Timeline.Item key={n} title={h.etiqueta}>
                <Text size="xs" c="dimmed">{momentoCorto(h.fecha)}{h.usuario ? ` · ${h.usuario}` : ''}</Text>
                {h.detalle && <Text size="xs">{h.detalle}</Text>}
              </Timeline.Item>
            ))}
          </Timeline>
        </Stack>
      )}
    </Stack>
  )
}

interface Props {
  prestamo: PrestamoEquipo | undefined
  cargando: boolean
  opened: boolean
  onClose: () => void
}

/** El detalle en un panel lateral, para «Mis préstamos». */
export function PrestamoDetalle({ prestamo: p, cargando, opened, onClose }: Props) {
  return (
    <SgthDrawer opened={opened} onClose={onClose} title={p ? `Préstamo ${p.folio}` : 'Préstamo'}
      description={p ? <EstadoPrestamoBadge prestamo={p} /> : undefined}>
      {cargando || !p ? <Skeleton height={300} /> : <PrestamoDetalleCuerpo prestamo={p} />}
    </SgthDrawer>
  )
}
