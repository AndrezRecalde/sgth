'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconCreditCard, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useCuentasBancarias } from '../../hooks/useCuentasBancarias'
import { useCuentaBancariaMutations } from '../../hooks/useCuentaBancariaMutations'
import { getCuentasBancariasColumns } from '../cuentasBancarias.columns'
import { CuentaBancariaModal } from '../CuentaBancariaModal'
import type { CuentaBancariaConRelaciones } from '@/types/api'

interface Props { servidorId: number }

export function CuentasBancariasTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [editCuenta, setEditCuenta] = useState<CuentaBancariaConRelaciones | null>(null)
  const { data: cuentas = [], isLoading, error } = useCuentasBancarias(servidorId)
  const { setPrincipal, eliminar } = useCuentaBancariaMutations(servidorId)

  const columns = getCuentasBancariasColumns({
    onEdit: (cuenta) => { setEditCuenta(cuenta); open() },
    onPrincipal: (cuenta, proposito) =>
      setPrincipal.mutate({ id: Number(cuenta.id), proposito }),
    onDelete: (id) => eliminar.mutate(id),
  })

  return (
    <Stack gap="md">
      <SectionCard
        title="Cuentas bancarias"
        actions={
          <Button size="xs" variant="light"
            leftSection={<IconPlus size={14} />} onClick={open}>
            Nueva cuenta
          </Button>
        }
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={cuentas.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconCreditCard,
            title: 'Sin cuentas bancarias',
            description: 'Registre la cuenta bancaria para el pago de nómina.',
          }}
        >
          <SgthTable records={cuentas} columns={columns} minHeight={80} />
        </DataState>
      </SectionCard>

      <CuentaBancariaModal
        key={editCuenta?.id ?? 'nueva'}
        opened={opened}
        onClose={() => { setEditCuenta(null); close() }}
        servidorId={servidorId}
        initialValues={editCuenta}
      />
    </Stack>
  )
}
