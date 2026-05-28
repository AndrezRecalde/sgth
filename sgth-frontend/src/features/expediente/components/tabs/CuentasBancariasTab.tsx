'use client'

import { Stack, Text, Badge, Group, Button,
         ActionIcon, Tooltip, Skeleton } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash, IconStar,
         IconCreditCard, IconStarFilled } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useCuentasBancarias } from '../../hooks/useCuentasBancarias'
import { useCuentaBancariaMutations } from '../../hooks/useCuentaBancariaMutations'
import { CuentaBancariaModal } from '../CuentaBancariaModal'
import type { CuentaBancariaConRelaciones } from '@/types/api'

interface Props { servidorId: number }

export function CuentasBancariasTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
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
                border: c.es_principal
                  ? '1.5px solid var(--mantine-color-emerald-6)'
                  : '1px solid var(--mantine-color-default-border)',
                background: c.es_principal
                  ? 'var(--mantine-color-emerald-light)'
                  : undefined,
              }}
            >
              <Stack gap={2}>
                <Group gap="xs">
                  {c.es_principal && (
                    <IconStarFilled
                      size={14}
                      color="var(--mantine-color-emerald-6)"
                    />
                  )}
                  <Text size="sm" fw={600}>
                    {(c.entidad_financiera as { nombre?: string })?.nombre
                      ?? c.entidad_financiera_id
                      ?? '-'}
                  </Text>
                  {c.es_principal && (
                    <Badge size="xs" color="emerald" variant="light">
                      Principal
                    </Badge>
                  )}
                </Group>
                <Text size="xs" c="dimmed">
                  {c.tipo_cuenta === 'ahorros' ? 'Cuenta de ahorros' : 'Cuenta corriente'}
                  {' · '}
                  <Text span ff="monospace" size="xs">
                    {c.numero_cuenta ?? '-'}
                  </Text>
                </Text>
              </Stack>

              <Group gap="xs">
                {!c.es_principal && (
                  <Tooltip label="Establecer como principal" withArrow>
                    <ActionIcon
                      variant="subtle"
                      color="emerald"
                      size="sm"
                      onClick={() => setPrincipal.mutate(Number(c.id))}
                    >
                      <IconStar size={14} />
                    </ActionIcon>
                  </Tooltip>
                )}
                <Tooltip label="Eliminar cuenta" withArrow>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    size="sm"
                    onClick={() => {
                      if (confirm('¿Eliminar esta cuenta bancaria?'))
                        eliminar.mutate(Number(c.id))
                    }}
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
    </Stack>
  )
}
