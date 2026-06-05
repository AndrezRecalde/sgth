'use client'

import { useState } from 'react'
import {
  Stack, Group, Button, Text, Badge,
  Tabs, Chip,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPlane, IconPlus, IconCheck,
  IconCurrencyDollar,
} from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { TableActions } from '@/components/ui/TableActions'
import { useViaticos } from '../hooks/useViaticos'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { ViaticoModal } from './ViaticoModal'
import { ViaticoDetalle } from './ViaticoDetalle'
import { VuelosTab } from './VuelosTab'
import type { Viatico, EstadoViatico, ViaticoConRelaciones } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ESTADO_COLORS: Record<EstadoViatico, string> = {
  solicitado:           'orange',
  aprobado:             'blue',
  con_anticipo:         'cyan',
  en_comision:          'violet',
  pendiente_liquidacion:'yellow',
  liquidado:            'emerald',
  contabilizado:        'gray',
}

const ESTADO_LABELS: Record<EstadoViatico, string> = {
  solicitado:           'Solicitado',
  aprobado:             'Aprobado',
  con_anticipo:         'Con anticipo',
  en_comision:          'En comisión',
  pendiente_liquidacion:'Pend. liquidación',
  liquidado:            'Liquidado',
  contabilizado:        'Contabilizado',
}

export function ViaticoView() {
  const [modalAbierto, { open, close }] = useDisclosure(false)
  const [viaticoSel, setViaticoSel] =
    useState<ViaticoConRelaciones | null>(null)
  const [detalleAbierto, { open: abrirDetalle, close: cerrarDetalle }] =
    useDisclosure(false)
  const [filtroEstado, setFiltroEstado] =
    useState<string>('solicitado')

  const filtros = {
    estado: filtroEstado === 'todos'
      ? undefined
      : filtroEstado as EstadoViatico,
    per_page: 50,
  }

  const { data, isLoading } = useViaticos(filtros)
  const lista = (data?.data ?? []) as ViaticoConRelaciones[]
  const { aprobar } = useViaticoMutations()

  const handleVer = (v: ViaticoConRelaciones) => {
    setViaticoSel(v)
    abrirDetalle()
  }

  const columns: DataTableColumn<ViaticoConRelaciones>[] = [
    {
      accessor: 'codigo_viatico',
      title:    'Código',
      width:    150,
      render: ({ codigo_viatico }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {codigo_viatico ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render: (v) => {
        const s = v.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(' ')}
          </Text>
        )
      },
    },
    {
      accessor: 'zona',
      title:    'Zona',
      width:    130,
      render: ({ zona }) => {
        const labels: Record<string, string> = {
          dentro_provincia: 'Dentro prov.',
          fuera_provincia:  'Fuera prov.',
          exterior:         'Exterior',
        }
        return <Text size="sm">{labels[zona as string] ?? zona}</Text>
      },
    },
    {
      accessor: 'datetime_salida',
      title:    'Período',
      width:    150,
      render: ({ datetime_salida, datetime_llegada }) => {
        const fmt = (f: string | null | undefined) =>
          f ? new Date(f).toLocaleDateString('es-EC', {
            timeZone: 'UTC',
            day: '2-digit', month: '2-digit', year: '2-digit',
          }) : '—'
        return (
          <Text size="xs" ff="monospace">
            {fmt(datetime_salida as string)} – {fmt(datetime_llegada as string)}
          </Text>
        )
      },
    },
    {
      accessor: 'monto_calculado',
      title:    'Monto',
      width:    100,
      render: ({ monto_calculado }) => (
        <Text size="sm" ff="monospace" ta="right">
          ${Number(monto_calculado ?? 0).toFixed(2)}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    140,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as EstadoViatico] ?? 'gray'}
          variant="light"
          size="sm"
        >
          {ESTADO_LABELS[estado as EstadoViatico] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (v) => (
        <TableActions actions={[
          {
            label:   'Ver detalle',
            icon:    <IconPlane size={14} />,
            color:   'blue',
            onClick: () => handleVer(v),
          },
          {
            label:   'Aprobar',
            icon:    <IconCheck size={14} />,
            color:   'emerald',
            onClick: () => aprobar.mutate(v.id),
            hidden:  (v.estado as string) !== 'solicitado',
          },
          {
            label:   'Liquidar',
            icon:    <IconCurrencyDollar size={14} />,
            color:   'orange',
            onClick: () => handleVer(v),
            hidden:  (v.estado as string) !== 'pendiente_liquidacion',
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <PageHeader
        title="Viáticos"
        subtitle="Gestión de comisiones de servicio y viáticos"
        icon={<IconPlane size={24} />}
      />

      <Tabs defaultValue="viaticos">
        <Tabs.List>
          <Tabs.Tab value="viaticos">
            Solicitudes
          </Tabs.Tab>
          <Tabs.Tab value="vuelos">
            Autorizaciones de vuelo
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="viaticos" pt="md">
          <Stack gap="sm">

            {/* Chips de estado */}
            <Group gap="xs">
              <Text size="sm" fw={500} c="dimmed">Estado:</Text>
              {[
                { value: 'todos',                   label: 'Todos',         color: 'gray'    },
                { value: 'solicitado',               label: 'Solicitados',   color: 'orange'  },
                { value: 'aprobado',                 label: 'Aprobados',     color: 'blue'    },
                { value: 'con_anticipo',             label: 'Con anticipo',  color: 'cyan'    },
                { value: 'pendiente_liquidacion',    label: 'Pend. liquid.', color: 'yellow'  },
                { value: 'liquidado',                label: 'Liquidados',    color: 'emerald' },
              ].map(op => (
                <Chip
                  key={op.value}
                  size="sm"
                  color={op.color}
                  checked={filtroEstado === op.value}
                  onChange={() => setFiltroEstado(op.value)}
                >
                  {op.label}
                </Chip>
              ))}
            </Group>

            {/* Botón nueva solicitud */}
            <Group justify="flex-end">
              <Button
                size="xs"
                color="emerald"
                variant="light"
                leftSection={<IconPlus size={14} />}
                onClick={open}
              >
                Nueva solicitud
              </Button>
            </Group>

            {lista.length === 0 && !isLoading ? (
              <EmptyState
                icon={IconPlane}
                title="Sin solicitudes de viáticos"
                description="No hay viáticos en este estado."
              />
            ) : (
              <SgthTable
                records={lista}
                columns={columns}
                fetching={isLoading}
                minHeight={200}
              />
            )}
          </Stack>
        </Tabs.Panel>

        <Tabs.Panel value="vuelos" pt="md">
          <VuelosTab />
        </Tabs.Panel>
      </Tabs>

      <ViaticoModal
        opened={modalAbierto}
        onClose={close}
      />

      <ViaticoDetalle
        opened={detalleAbierto}
        onClose={cerrarDetalle}
        viatico={viaticoSel}
      />
    </Stack>
  )
}
