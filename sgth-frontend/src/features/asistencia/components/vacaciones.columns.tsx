'use client'

import { Stack, Text } from '@mantine/core'
import { IconBan, IconCheck, IconPrinter } from '@tabler/icons-react'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { formatFecha, hoyIso } from '@/lib/fecha'
import { ESTADO_LABELS, MOTIVO_LABELS, TONO_ESTADO } from './vacaciones.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { Vacacion } from '@/types/api'

interface ColumnActions {
  exportandoId:  number | null
  /** `aprobar-vacaciones`: sin él, Registrar y Anular no se ofrecen. */
  puedeResolver: boolean
  onExportar:    (id: number) => void
  onRegistrar:   (id: number) => void
  onAnular:      (vacacion: Vacacion) => void
}

/**
 * La misma regla que el backend: una autorizada siempre, una registrada solo
 * si todavía no comenzó.
 */
function sePuedeAnular(v: Vacacion): boolean {
  if (v.estado === 'autorizada') return true
  return v.estado === 'registrada' && v.fecha_inicio.substring(0, 10) >= hoyIso()
}

/**
 * El impreso es la hoja que el servidor hace firmar y baja a ventanilla: solo
 * tiene sentido mientras está autorizada. Una ya registrada la reimprime
 * Talento Humano, que es quien responde a una auditoría; es la misma regla de
 * `VacacionPolicy::exportar`, y sin ella el botón ofrecía una descarga que el
 * backend iba a rechazar.
 */
function sePuedeImprimir(v: Vacacion, puedeResolver: boolean): boolean {
  return v.estado === 'autorizada' || puedeResolver
}

export function getVacacionesColumns(
  actions: ColumnActions
): DataTableColumn<Vacacion>[] {
  return [
    {
      accessor: 'folio',
      title: 'Folio',
      width: 145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      // Como en permisos: la unidad más larga (55 letras) cabe en dos líneas.
      width: 210,
      render: ({ servidor, unidad_administrativa }) =>
        servidor ? (
          <Stack gap={0}>
            <Text size="sm">
              {[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}
            </Text>
            {/* La grabada en la solicitud: si cambia de unidad, su historial no cambia. */}
            {unidad_administrativa?.nombre && (
              <Text size="xs" c="dimmed" lineClamp={2}>{unidad_administrativa.nombre}</Text>
            )}
          </Stack>
        ) : (
          <Text size="sm" c="dimmed">—</Text>
        ),
    },
    {
      accessor: 'motivo',
      title: 'Motivo',
      render: ({ motivo }) => (
        <Text size="sm">{MOTIVO_LABELS[motivo] ?? motivo}</Text>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Desde',
      width: 110,
      render: ({ fecha_inicio }) => <Text size="sm">{formatFecha(fecha_inicio)}</Text>,
    },
    {
      accessor: 'fecha_fin',
      title: 'Hasta',
      width: 110,
      render: ({ fecha_fin }) => <Text size="sm">{formatFecha(fecha_fin)}</Text>,
    },
    {
      accessor: 'dias_solicitados',
      title: 'Días',
      width: 70,
      render: ({ dias_solicitados }) => (
        <Text size="sm" ta="center">{dias_solicitados}</Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      // «RECHAZADA» mide 83 px y la celda lleva 32 de relleno: con 110 se
      // salía 5, y APROBADA y PENDIENTE quedaban a uno o dos píxeles.
      width: 140,
      render: ({ estado }) => (
        <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
          {ESTADO_LABELS[estado] ?? estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (v) => (
        <TableActions
          actions={[
            {
              label: actions.exportandoId === v.id
                ? 'Exportando...'
                : 'Imprimir solicitud',
              icon: <IconPrinter size={14} />,
              onClick: () => actions.onExportar(v.id),
              hidden: !sePuedeImprimir(v, actions.puedeResolver),
            },
            {
              label: 'Registrar',
              icon: <IconCheck size={14} />,
              onClick: () =>
                confirmar({
                  title: 'Registrar solicitud',
                  message:
                    'Se registrará la solicitud y los días saldrán del saldo de vacaciones '
                    + 'del servidor. Hágalo con el impreso firmado a la vista.',
                  confirmLabel: 'Registrar',
                  onConfirm: () => actions.onRegistrar(v.id),
                }),
              hidden: !actions.puedeResolver || v.estado !== 'autorizada',
            },
            {
              label: 'Anular',
              icon: <IconBan size={14} />,
              color: 'red',
              onClick: () => actions.onAnular(v),
              hidden: !actions.puedeResolver || !sePuedeAnular(v),
            },
          ]}
        />
      ),
    },
  ]
}
