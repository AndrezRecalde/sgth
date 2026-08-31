'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import { Stack, Text, Badge, Group, Button,
         ActionIcon, Tooltip, Skeleton, Menu } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash, IconStar, IconEdit,
         IconCreditCard, IconStarFilled } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useCuentasBancarias } from '../../hooks/useCuentasBancarias'
import { useCuentaBancariaMutations } from '../../hooks/useCuentaBancariaMutations'
import { CuentaBancariaModal } from '../CuentaBancariaModal'
import type { CuentaBancariaConRelaciones } from '@/types/api'

interface Props { servidorId: number }

export function CuentasBancariasTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [editCuenta, setEditCuenta] = useState<CuentaBancariaConRelaciones | null>(null)
  const [editOpened, { open: openEdit, close: closeEdit }] = useDisclosure(false)

  const { data: cuentas = [], isLoading } = useCuentasBancarias(servidorId)
  const { setPrincipal, eliminar } = useCuentaBancariaMutations(servidorId)

  const listaCuentas = cuentas as CuentaBancariaConRelaciones[]

  return (
    <Stack gap="sm">
      <Group justify="flex-end">
        <Button
          size="xs"
          color="emerald"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nueva cuenta
        </Button>
      </Group>

      {isLoading ? (
        <Stack gap="xs">
          <Skeleton height={72} radius="md" />
          <Skeleton height={72} radius="md" />
        </Stack>
      ) : listaCuentas.length === 0 ? (
        <EmptyState
          icon={IconCreditCard}
          title="Sin cuentas bancarias"
          description="Registra la cuenta bancaria para el pago de nómina."
        />
      ) : (
        <Stack gap="xs">
          {listaCuentas.map(c => (
            <Group
              key={c.id}
              justify="space-between"
              p="sm"
              style={{
                borderRadius: 8,
                border: (c.es_principal_sueldo || c.es_principal_viatico)
                  ? '1.5px solid var(--mantine-color-emerald-6)'
                  : '1px solid var(--mantine-color-default-border)',
                background: (c.es_principal_sueldo || c.es_principal_viatico)
                  ? 'var(--mantine-color-emerald-light)'
                  : undefined,
              }}
            >
              <Stack gap={2}>
                <Group gap="xs">
                  {(c.es_principal_sueldo || c.es_principal_viatico) && (
                    <IconStarFilled
                      size={14}
                      color={c.es_principal_sueldo ? 'var(--mantine-color-emerald-6)' : 'var(--mantine-color-blue-6)'}
                    />
                  )}
                  <Text size="sm" fw={600}>
                    {(c.entidad_financiera as { nombre?: string })?.nombre
                      ?? c.entidad_financiera_id
                      ?? '-'}
                  </Text>
                  {c.es_principal_sueldo && (
                    <Badge size="xs" color="emerald" variant="light">
                      Principal nómina
                    </Badge>
                  )}
                  {c.es_principal_viatico && (
                    <Badge size="xs" color="blue" variant="light">
                      Principal viáticos
                    </Badge>
                  )}
                </Group>
                <Text size="xs" c="dimmed">
                  {c.tipo_cuenta === 'ahorros' ? 'Cuenta de ahorros' : 'Cuenta corriente'}
                  {c.proposito ? ` · ${
                    c.proposito === 'sueldo' ? 'Nómina'
                    : c.proposito === 'viaticos' ? 'Viáticos'
                    : 'Nómina y Viáticos'
                  }` : ''}
                </Text>
                <Text size="xs" ff="monospace" c="dimmed">
                  {c.numero_cuenta ?? '-'}
                </Text>
              </Stack>

              <Group gap="xs">
                {!(c.es_principal_sueldo && c.es_principal_viatico) && (
                  <Menu position="bottom-end" shadow="md" width={200}>
                    <Menu.Target>
                      <Tooltip label="Establecer como principal" withArrow>
                        <ActionIcon variant="subtle" color="emerald" size="sm">
                          <IconStar size={14} />
                        </ActionIcon>
                      </Tooltip>
                    </Menu.Target>
                    <Menu.Dropdown>
                      <Menu.Label>Establecer principal para:</Menu.Label>
                      {!c.es_principal_sueldo && (
                        <Menu.Item
                          leftSection={<IconStarFilled size={14} color="var(--mantine-color-emerald-6)" />}
                          onClick={() => setPrincipal.mutate({ id: Number(c.id), proposito: 'sueldo' })}
                        >
                          Nómina / Sueldo
                        </Menu.Item>
                      )}
                      {!c.es_principal_viatico && (
                        <Menu.Item
                          leftSection={<IconStarFilled size={14} color="var(--mantine-color-blue-6)" />}
                          onClick={() => setPrincipal.mutate({ id: Number(c.id), proposito: 'viatico' })}
                        >
                          Viáticos
                        </Menu.Item>
                      )}
                    </Menu.Dropdown>
                  </Menu>
                )}
                <Tooltip label="Editar cuenta" withArrow>
                  <ActionIcon
                    variant="subtle" color="blue" size="sm"
                    onClick={() => { setEditCuenta(c); openEdit() }}
                  >
                    <IconEdit size={14} />
                  </ActionIcon>
                </Tooltip>
                <Tooltip label="Eliminar cuenta" withArrow>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    size="sm"
                    onClick={() => confirmar({
                      title:   'Eliminar cuenta bancaria',
                      message: 'Se eliminará esta cuenta bancaria del expediente. No se puede deshacer.',
                      destructiva: true,
                      onConfirm: () => eliminar.mutate(Number(c.id)),
                    })}
                  >
                    <IconTrash size={14} />
                  </ActionIcon>
                </Tooltip>
              </Group>
            </Group>
          ))}
        </Stack>
      )}

      <CuentaBancariaModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
      />
      <CuentaBancariaModal
        opened={editOpened}
        onClose={() => { setEditCuenta(null); closeEdit() }}
        servidorId={servidorId}
        initialValues={editCuenta}
      />
    </Stack>
  )
}
