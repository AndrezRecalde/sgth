'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group, SimpleGrid, Skeleton, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconAdjustments, IconCalendarStats, IconDroplet, IconEdit, IconFileSpreadsheet, IconPackageImport, IconPlus, IconStack2, IconTruckDelivery,
} from '@tabler/icons-react'
import { EmptyState, MotivoModal, PageHeader, PageShell, SectionCard, SgthTable, StatCard, StatusBadge, confirmar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { formatFecha, fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { AgregarModeloModal } from '@/features/consumibles-ti/components/AgregarModeloModal'
import { AjusteModal } from '@/features/consumibles-ti/components/AjusteModal'
import { AperturaModal } from '@/features/consumibles-ti/components/AperturaModal'
import { ConsumibleModal } from '@/features/consumibles-ti/components/ConsumibleModal'
import { detalleConsumible } from '@/features/consumibles-ti/components/consumibles.columns'
import { IngresoModal } from '@/features/consumibles-ti/components/IngresoModal'
import { TIPO_MOVIMIENTO } from '@/features/consumibles-ti/constants/consumibles'
import { columnasKardex } from '@/features/consumibles-ti/components/kardex.columns'
import { columnasModelosCompatibles } from '@/features/consumibles-ti/components/modelos.columns'
import { useConsumible, useConsumibleMutations, useDescargaKardex, useKardex, useMovimientoMutations } from '@/features/consumibles-ti/hooks/useConsumibles'
import type { FilaKardex } from '@/features/consumibles-ti/services/consumiblesService'

/**
 * Un consumible: su saldo, los modelos de equipo a los que sirve (con los
 * equipos del inventario que coinciden) y el kárdex, con el saldo que dejó
 * cada movimiento. Un movimiento mal hecho se anula con su motivo y sigue a
 * la vista.
 */
export function ConsumibleView({ id }: { id: number }) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const compacto = useContainedInput('sm')
  const { data: c, isLoading } = useConsumible(id)
  const [desde, setDesde] = useState<string | null>(null)
  const [hasta, setHasta] = useState<string | null>(null)
  const { data: kardex, isLoading: cargandoKardex } = useKardex(id, desde, hasta)
  const { quitarModelo } = useConsumibleMutations(id)
  const { anular } = useMovimientoMutations(id)
  const excel = useDescargaKardex()
  const [editando, setEditando] = useState(false)
  const [ingreso, setIngreso] = useState(false)
  const [apertura, setApertura] = useState(false)
  const [ajuste, setAjuste] = useState(false)
  const [agregarModelo, setAgregarModelo] = useState(false)
  const [anulando, setAnulando] = useState<FilaKardex | null>(null)

  if (isLoading) return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  if (!c) {
    return (
      <PageShell>
        <PageHeader title="Consumible" backHref={ROUTES.TIC.CONSUMIBLES} />
        <EmptyState icon={IconDroplet} title="Consumible no encontrado" description="Vuelva al catálogo y búsquelo allí." />
      </PageShell>
    )
  }

  const gestiona = hasPermiso('gestionar-inventario-ti')
  const operable = gestiona && c.activo
  const filas = kardex?.filas ?? []
  const vivas = filas.filter((f) => !f.anulado_en)
  // Sin filtro de fechas el kárdex está completo: se sabe si ya tiene apertura.
  const tieneApertura = vivas.some((f) => f.tipo === 'apertura')
  const anio = new Date().getFullYear().toString()
  const entregadoEnElAnio = vivas.filter((f) => f.tipo === 'entrega' && f.fecha.startsWith(anio)).reduce((s, f) => s - f.cantidad, 0)
  const ultimaEntrega = [...vivas].reverse().find((f) => f.tipo === 'entrega')?.fecha ?? null

  const preguntarQuitar = (m: { id: number; marca: string | null; modelo: string }) => confirmar({
    title: 'Quitar de los compatibles',
    message: <>«{c.descripcion}» deja de ofrecerse para <b>{[m.marca, m.modelo].filter(Boolean).join(' ')}</b>. Lo ya entregado no cambia.</>,
    confirmLabel: 'Quitar',
    destructiva: true,
    onConfirm: () => quitarModelo.mutate(m.id),
  })

  return (
    <PageShell>
      <PageHeader
        title={c.descripcion}
        description={`${detalleConsumible(c)}${c.codigo_bodega ? ` · Código de bodega ${c.codigo_bodega}` : ''}`}
        estado={(
          <Group gap={6}>
            <StatusBadge>{c.guarda_tic ? 'Lo guarda TIC' : 'Lo despacha Bodega'}</StatusBadge>
            {!c.activo && <StatusBadge>Inactivo</StatusBadge>}
          </Group>
        )}
        backHref={ROUTES.TIC.CONSUMIBLES}
        actions={gestiona ? (
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconEdit size={16} />} onClick={() => setEditando(true)}>Editar</Button>
            {operable && c.guarda_tic && !tieneApertura && !desde && !hasta && (
              <Button variant="subtle" leftSection={<IconStack2 size={16} />} onClick={() => setApertura(true)}>Apertura</Button>
            )}
            {operable && c.guarda_tic && (
              <>
                <Button variant="subtle" leftSection={<IconAdjustments size={16} />} onClick={() => setAjuste(true)}>Ajuste</Button>
                <Button variant="subtle" leftSection={<IconPackageImport size={16} />} onClick={() => setIngreso(true)}>Ingreso</Button>
              </>
            )}
            {operable && (
              <Button variant="light" leftSection={<IconTruckDelivery size={16} />} onClick={() => router.push(ROUTES.TIC.CONSUMIBLES_ENTREGAR())}>
                Entregar
              </Button>
            )}
          </Group>
        ) : undefined}
      />

      <SimpleGrid cols={{ base: 1, xs: 2, md: c.guarda_tic ? 4 : 2 }}>
        {c.guarda_tic && (
          <StatCard label="Saldo" value={c.saldo ?? 0} icon={IconStack2} tone={c.bajo_minimo ? 'warning' : undefined}
            hint={c.bajo_minimo ? 'Bajo el mínimo' : undefined} />
        )}
        {c.guarda_tic && (
          <StatCard label="Stock mínimo" value={c.stock_minimo || '—'} icon={IconAdjustments} hint={c.stock_minimo ? undefined : 'Sin aviso'} />
        )}
        <StatCard label={`Entregado en ${anio}`} value={entregadoEnElAnio} icon={IconTruckDelivery} />
        <StatCard label="Última entrega" value={ultimaEntrega ? formatFecha(ultimaEntrega) : '—'} icon={IconCalendarStats} />
      </SimpleGrid>

      <SectionCard
        title="Modelos compatibles"
        description={c.de_impresion ? 'Solo se entrega a los equipos de estos modelos.' : 'Un repuesto se puede entregar a cualquier equipo.'}
        actions={gestiona ? <Button variant="subtle" size="xs" leftSection={<IconPlus size={14} />} onClick={() => setAgregarModelo(true)}>Agregar modelo</Button> : undefined}
      >
        <SgthTable records={c.modelos} minHeight={0}
          columns={columnasModelosCompatibles({ onQuitar: gestiona ? preguntarQuitar : undefined })}
          noRecordsText={c.de_impresion ? 'Todavía no tiene modelos: agregue los de las impresoras a las que sirve.' : 'Sin modelos: sirve a cualquier equipo.'} />
      </SectionCard>

      <SectionCard
        title="Kárdex"
        description={c.guarda_tic
          ? 'Cada movimiento con el saldo que dejó. Lo anulado sigue a la vista, pero no cuenta.'
          : 'Lo despacha Bodega: aquí quedan las entregas, sin saldo en TIC.'}
        actions={(
          <Group gap="xs" align="flex-end">
            <DatePickerInput label="Desde" placeholder="El inicio" valueFormat="DD/MM/YYYY" clearable w={150} {...compacto}
              value={toDateValue(desde)} onChange={(d) => setDesde(fromDateValueOrNull(d))} />
            <DatePickerInput label="Hasta" placeholder="Hoy" valueFormat="DD/MM/YYYY" clearable w={150} {...compacto}
              minDate={desde ?? undefined} value={toDateValue(hasta)} onChange={(d) => setHasta(fromDateValueOrNull(d))} />
            <Button variant="light" leftSection={<IconFileSpreadsheet size={16} />} h={40} disabled={!filas.length} loading={excel.isPending}
              onClick={() => excel.mutate({ id: c.id, desde, hasta, descripcion: c.descripcion })}>
              Excel
            </Button>
          </Group>
        )}
      >
        {desde && kardex?.saldo_inicial !== null && kardex?.saldo_inicial !== undefined && (
          <Text size="sm" mb="xs">Saldo al {formatFecha(desde)}, antes de los movimientos del día: <b>{kardex.saldo_inicial}</b></Text>
        )}
        <SgthTable records={filas} fetching={cargandoKardex} minHeight={filas.length ? 0 : 150}
          columns={columnasKardex({ onAnular: gestiona ? setAnulando : undefined })}
          rowStyle={(f) => (f.anulado_en ? { opacity: 0.6 } : undefined)}
          noRecordsText={desde || hasta ? 'Sin movimientos en esas fechas.' : 'Todavía no tiene movimientos.'} />
      </SectionCard>

      <ConsumibleModal opened={editando} consumible={c} onClose={() => setEditando(false)} />
      <IngresoModal opened={ingreso} consumibleId={c.id} onClose={() => setIngreso(false)} />
      <AperturaModal consumible={apertura ? c : null} onClose={() => setApertura(false)} />
      <AjusteModal consumible={ajuste ? c : null} onClose={() => setAjuste(false)} />
      <AgregarModeloModal consumible={agregarModelo ? c : null} onClose={() => setAgregarModelo(false)} />
      <MotivoModal
        opened={!!anulando}
        onClose={() => setAnulando(null)}
        title="Anular movimiento"
        descripcion={anulando && (
          <><b>{TIPO_MOVIMIENTO[anulando.tipo].etiqueta}</b> del {formatFecha(anulando.fecha)} ({anulando.cantidad > 0 ? `+${anulando.cantidad}` : anulando.cantidad}): deja de contar, pero sigue en el kárdex con su motivo.</>
        )}
        confirmLabel="Anular"
        destructiva
        placeholder="Ej.: Se registró dos veces"
        cargando={anular.isPending}
        onConfirm={(motivo) => anulando && anular.mutate({ movimientoId: anulando.id, motivo }, { onSuccess: () => setAnulando(null) })}
      />
    </PageShell>
  )
}
