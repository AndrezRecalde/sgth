'use client'

import { useState } from 'react'
import {
  Modal, Stack, Group, Button,
  Text, Badge, Skeleton,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconSearch } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useReporteEppEntregas } from '../hooks/useEppEntregas'
import { toDateValue, fromDateValue } from '../utils/fecha'
import type { ReporteEppFila } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function ReporteEppModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()

  const [fechaInicio, setFechaInicio] = useState('')
  const [fechaFin, setFechaFin] = useState('')
  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [filtros, setFiltros] = useState<{ fecha_inicio: string; fecha_fin: string; puesto_id?: number } | null>(null)

  const { data: reporte, isLoading } = useReporteEppEntregas(filtros)

  const handleBuscar = () => {
    if (!fechaInicio || !fechaFin) return
    setFiltros({ fecha_inicio: fechaInicio, fecha_fin: fechaFin, puesto_id: puestoId ?? undefined })
  }

  const columns: DataTableColumn<ReporteEppFila>[] = [
    { accessor: 'servidor_nombre', title: 'Servidor' },
    { accessor: 'puesto', title: 'Puesto' },
    {
      accessor: 'total_entregas',
      title: 'Entregas',
      render: (f) => <Badge color="emerald" variant="light" size="sm">{f.total_entregas}</Badge>,
    },
    {
      accessor: 'total_devoluciones',
      title: 'Devoluciones',
      render: (f) => <Badge color="blue" variant="light" size="sm">{f.total_devoluciones}</Badge>,
    },
    {
      accessor: 'total_reposiciones',
      title: 'Reposiciones',
      render: (f) => <Badge color="orange" variant="light" size="sm">{f.total_reposiciones}</Badge>,
    },
  ]

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Lista de EPP entregados"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <Group align="flex-end" wrap="wrap">
          <DatePickerInput
            label="Desde"
            placeholder="Seleccionar"
            valueFormat="DD/MM/YYYY"
            {...contained}
            value={toDateValue(fechaInicio)}
            onChange={(d) => setFechaInicio(fromDateValue(d ?? null))}
          />
          <DatePickerInput
            label="Hasta"
            placeholder="Seleccionar"
            valueFormat="DD/MM/YYYY"
            {...contained}
            value={toDateValue(fechaFin)}
            onChange={(d) => setFechaFin(fromDateValue(d ?? null))}
          />
          <BuscarPuestoSelect
            label="Puesto (opcional)"
            value={puestoId}
            onChange={setPuestoId}
          />
          <Button
            leftSection={<IconSearch size={16} />}
            color="emerald"
            onClick={handleBuscar}
            disabled={!fechaInicio || !fechaFin}
          >
            Buscar
          </Button>
        </Group>

        {isLoading && <Skeleton height={150} radius="md" />}

        {!isLoading && reporte && (
          <>
            <Group gap="lg">
              <Text size="sm">
                Total registros: <Text span fw={600}>{reporte.totales.total_registros}</Text>
              </Text>
              <Text size="sm">
                Servidores: <Text span fw={600}>{reporte.totales.total_servidores}</Text>
              </Text>
            </Group>

            <SgthTable
              records={reporte.consolidado}
              columns={columns}
              idAccessor="servidor_id"
              noRecordsText="Sin movimientos de EPP en el período seleccionado."
              minHeight={120}
            />
          </>
        )}
      </Stack>
    </Modal>
  )
}
