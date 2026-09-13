'use client'

import {
  Stack, Text, Group, Divider, Skeleton,
} from '@mantine/core'
import { SgthModal, SgthTable, StatusBadge } from '@/components/ui'
import { formatMonto, getConceptosColumns } from './rolesPago.columns'
import { useQuery } from '@tanstack/react-query'
import { nominaService } from '../services/nominaService'
import type { Nomina, ServidorConRelaciones } from '@/types/api'

interface Props {
  opened:    boolean
  onClose:   () => void
  nomina:    Nomina | null
  servidor:  ServidorConRelaciones | null
}


export function RolPagoModal({ opened, onClose, nomina, servidor }: Props) {
  const { data: rol, isLoading } = useQuery({
    queryKey: ['rol-pago', nomina?.id, servidor?.id],
    queryFn:  () => nominaService.rolPago(nomina!.id, Number(servidor!.id)),
    enabled:  !!nomina && !!servidor,
    staleTime: 0,
  })

  const detalles = rol?.nomina?.detalles ?? []
  const ingresos   = detalles.filter(d => d.concepto?.tipo === 'ingreso')
  const descuentos = detalles.filter(d => d.concepto?.tipo === 'descuento')

  const nombreServidor = servidor
    ? [servidor.apellido, servidor.segundo_apellido,
       servidor.nombre, servidor.segundo_nombre]
        .filter(Boolean).join(' ')
    : '—'

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={`Rol de pago — ${nomina?.periodo ?? ''}`}
      size="lg"
    >
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={24} />
          <Skeleton height={200} />
        </Stack>
      ) : (
        <Stack gap="md">
          <Group justify="space-between">
            <div>
              <Text size="sm" fw={600}>{nombreServidor}</Text>
              <Text size="xs" c="dimmed">
                {servidor?.cedula ?? '—'}
              </Text>
            </div>
            <StatusBadge>
              {nomina?.periodo}
            </StatusBadge>
          </Group>

          <Divider label="Ingresos" labelPosition="left" />
          <SgthTable
            records={ingresos}
            columns={getConceptosColumns('ingreso')}
            minHeight={100}
            noRecordsText="Sin ingresos en este rol"
          />

          <Divider label="Descuentos" labelPosition="left" />
          <SgthTable
            records={descuentos}
            columns={getConceptosColumns('descuento')}
            minHeight={100}
            noRecordsText="Sin descuentos en este rol"
          />

          <Divider />
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Total ingresos</Text>
            <Text size="sm" fw={500} c="emerald">
              {formatMonto(rol?.total_ingresos)}
            </Text>
          </Group>
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Total descuentos</Text>
            <Text size="sm" fw={500} c="red">
              {formatMonto(rol?.total_descuentos)}
            </Text>
          </Group>
          <Divider />
          <Group justify="space-between">
            <Text fw={700}>NETO A PAGAR</Text>
            <Text fw={700} size="lg">
              {formatMonto(rol?.total_neto)}
            </Text>
          </Group>
        </Stack>
      )}
    </SgthModal>
  )
}
