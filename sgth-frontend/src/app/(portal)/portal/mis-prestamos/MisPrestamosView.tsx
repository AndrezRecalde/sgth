'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Select } from '@mantine/core'
import { IconBan, IconEye, IconPresentation } from '@tabler/icons-react'
import { AvisoSinFicha, DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, confirmar, type TableAction } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTieneFicha } from '@/hooks/useTieneFicha'
import { PrestamoDetalle } from '@/features/prestamos-ti/components/PrestamoDetalle'
import { columnasMisPrestamos } from '@/features/prestamos-ti/components/prestamos.columns'
import { ESTADOS_PRESTAMO } from '@/features/prestamos-ti/constants/estados'
import { useMiPrestamo, useMisPrestamoMutations, useMisPrestamos } from '@/features/prestamos-ti/hooks/useMisPrestamos'
import type { EstadoPrestamo, PrestamoEquipo } from '@/features/prestamos-ti/services/tipos'

/**
 * Las solicitudes y préstamos del servidor: en qué van, las horas previstas y
 * reales, y cancelar mientras TIC no haya entregado.
 */
export function MisPrestamosView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const tieneFicha = useTieneFicha()
  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState<EstadoPrestamo | null>(null)
  const [abierto, setAbierto] = useState<number | null>(null)
  const { data, isLoading, error } = useMisPrestamos(estado, page)
  const detalle = useMiPrestamo(abierto)
  const { cancelar } = useMisPrestamoMutations()

  const accionesDe = (p: PrestamoEquipo): TableAction[] => [
    { label: 'Ver detalle', icon: <IconEye size={14} />, onClick: () => setAbierto(p.id) },
    {
      label: 'Cancelar', icon: <IconBan size={14} />, color: 'red', hidden: !p.cancelable,
      onClick: () => confirmar({
        title: `Cancelar el préstamo ${p.folio}`,
        message: 'El horario queda libre para otra persona. Si todavía necesita los equipos, tendrá que pedirlos de nuevo.',
        confirmLabel: 'Cancelar el préstamo',
        cancelLabel: 'Volver',
        destructiva: true,
        onConfirm: () => cancelar.mutate(p.id),
      }),
    },
  ]

  const pedir = <Button variant="light" leftSection={<IconPresentation size={16} />} onClick={() => router.push(ROUTES.PORTAL.PRESTAMOS)}>Pedir equipos</Button>

  // Sin ficha el vacío decía «Todavía no ha pedido equipos» y ofrecía pedirlos,
  // para que el API rechazara el préstamo al enviar (PR 15).
  if (!tieneFicha) {
    return (
      <PageShell>
        <PageHeader
          title="Mis préstamos"
          description="Sus solicitudes de equipos a TIC: si están aprobadas, cuándo los retiró y cuándo los devolvió."
        />
        <AvisoSinFicha motivo="Los préstamos de equipos se piden a nombre del servidor." />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title="Mis préstamos"
        description="Sus solicitudes de equipos a TIC: si están aprobadas, cuándo los retiró y cuándo los devolvió."
        actions={pedir}
      />
      <Toolbar>
        <Select label="Estado" placeholder="Todos" data={ESTADOS_PRESTAMO} clearable {...compacto}
          value={estado} onChange={(v) => { setEstado(ESTADOS_PRESTAMO.find((e) => e.value === v)?.value ?? null); setPage(1) }} />
      </Toolbar>
      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={estado ? {
          icon: IconPresentation,
          title: 'Ningún préstamo en ese estado',
          action: <Button variant="subtle" onClick={() => setEstado(null)}>Ver todos</Button>,
        } : {
          icon: IconPresentation,
          title: 'Todavía no ha pedido equipos',
          description: 'Proyectores, parlantes y otros equipos de TIC: mire cuáles están libres y pídalos.',
          action: pedir,
        }}
      >
        <SgthTable {...PAGINACION_ES} records={data?.data ?? []} columns={columnasMisPrestamos(accionesDe)}
          totalRecords={data?.total ?? 0} recordsPerPage={15} page={page} onPageChange={setPage} minHeight={200}
          onRowClick={({ record }) => setAbierto(record.id)} />
      </DataState>

      <PrestamoDetalle opened={abierto !== null} onClose={() => setAbierto(null)}
        prestamo={detalle.data} cargando={detalle.isLoading} />
    </PageShell>
  )
}
