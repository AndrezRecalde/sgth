'use client'

import { Stack, Text } from '@mantine/core'
import { IconCheck, IconX } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { TableActions } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import type { AutorizacionVuelo } from '@/types/api'

type Tramo = NonNullable<AutorizacionVuelo['tramo']>

/**
 * Un extremo del vuelo: la ciudad, con la provincia o el país.
 *
 * Antes se armaba solo con la provincia y el cantón: un tramo que no los
 * tenía —solo la ciudad— salía «— → —».
 */
function lugar(t: Tramo, extremo: 'origen' | 'destino'): string {
  const ciudad = extremo === 'origen' ? t.origen_ciudad : t.destino_ciudad
  const nacional = (extremo === 'origen' ? t.origen_tipo : t.destino_tipo) === 'nacional'
  const region = nacional
    ? (extremo === 'origen' ? t.origenProvincia : t.destinoProvincia)?.nombre
    : extremo === 'origen' ? t.origen_pais : t.destino_pais

  return [ciudad, region].filter(Boolean).join(', ') || '—'
}

interface Acciones {
  /** Si quien mira puede decidir: nunca sobre el vuelo de un viático en el que viaja. */
  decide:    (v: AutorizacionVuelo) => boolean
  onAprobar: (v: AutorizacionVuelo) => void
  onRechazar: (v: AutorizacionVuelo) => void
}

/*
| Las autorizaciones de vuelo pendientes.
|
| Sin columna de estado: el endpoint solo devuelve las pendientes, y la
| columna decía «PENDIENTE» en todas las filas.
*/
export function getVuelosColumns({ decide, onAprobar, onRechazar }: Acciones): DataTableColumn<AutorizacionVuelo>[] {
  return [
    {
      accessor: 'viatico.codigo_viatico',
      title:    'Viático',
      width:    185,
      noWrap:   true,
      render:   (v) => <Text size="sm" fw={500}>{v.viatico?.codigo_viatico ?? '—'}</Text>,
    },
    {
      accessor: 'viatico.servidor',
      title:    'Servidor',
      render:   (v) => {
        const s = v.viatico?.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Stack gap={0}>
            <Text size="sm">{[s.apellido, s.nombre].filter(Boolean).join(' ')}</Text>
            <Text size="xs" c="dimmed">{s.puesto?.cargo?.nombre ?? ''}</Text>
          </Stack>
        )
      },
    },
    {
      accessor: 'tramo.ruta',
      title:    'Ruta',
      render:   (v) =>
        v.tramo ? (
          <Text size="sm">{lugar(v.tramo, 'origen')} → {lugar(v.tramo, 'destino')}</Text>
        ) : (
          <Text size="sm" c="dimmed">—</Text>
        ),
    },
    {
      accessor: 'tramo.datetime_salida',
      title:    'Salida del vuelo',
      width:    170,
      noWrap:   true,
      // En la hora de Ecuador: con `timeZone: 'UTC'` un vuelo de las 08:00
      // se leía a las 13:00.
      render:   (v) => <Text size="sm">{formatFechaHora(v.tramo?.datetime_salida)}</Text>,
    },
    {
      accessor: 'tramo.empresa',
      title:    'Aerolínea',
      width:    150,
      render:   (v) => <Text size="sm">{v.tramo?.empresa?.nombre ?? '—'}</Text>,
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render:   (v) => (
        <TableActions
          actions={[
            { label: 'Autorizar', icon: <IconCheck size={14} />, onClick: () => onAprobar(v), hidden: !decide(v) },
            { label: 'Rechazar', icon: <IconX size={14} />, color: 'red', onClick: () => onRechazar(v), hidden: !decide(v) },
          ]}
        />
      ),
    },
  ]
}
