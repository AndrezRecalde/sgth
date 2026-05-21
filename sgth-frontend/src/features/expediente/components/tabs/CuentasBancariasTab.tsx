'use client'

import { Stack, Text, Badge, Card, Group, Button, ActionIcon } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash, IconStar } from '@tabler/icons-react'
import { useCuentasBancarias } from '../../hooks/useCuentasBancarias'
import { useCuentaBancariaMutations } from '../../hooks/useCuentaBancariaMutations'
import { CuentaBancariaModal } from '../CuentaBancariaModal'
import type { CuentaBancariaConRelaciones } from '@/types/api'

interface Props { servidorId: number }

export function CuentasBancariasTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: cuentas = [], isLoading } = useCuentasBancarias(servidorId)
  const { setPrincipal, eliminar } = useCuentaBancariaMutations(servidorId)

  return (
    <Stack gap="sm">
      <Group justify="flex-end">
        <Button size="sm" leftSection={<IconPlus size={14} />}
          color="emerald" onClick={open}>
          Nueva cuenta
        </Button>
      </Group>
      {isLoading && <Text size="sm" c="dimmed">Cargando cuentas...</Text>}
      {(cuentas as CuentaBancariaConRelaciones[]).map(c => (
        <Card key={c.id} withBorder radius="md" p="sm">
          <Group justify="space-between" wrap="nowrap">
            <Stack gap={2}>
              <Group gap="xs">
                <Text size="sm" fw={600}>
                  {c.entidad_financiera?.nombre ?? '-'}
                </Text>
                {c.es_principal && (
                  <Badge size="xs" color="emerald">Principal</Badge>
                )}
              </Group>
              <Text size="xs" c="dimmed">
                {c.tipo_cuenta === 'ahorros' ? 'Ahorros' : 'Corriente'} ·{' '}
                {c.numero_cuenta ?? '-'}
              </Text>
            </Stack>
            <Group gap={4}>
              {!c.es_principal && (
                <ActionIcon variant="subtle" color="emerald"
                  onClick={() => setPrincipal.mutate(Number(c.id))}
                  aria-label="Marcar como principal"
                >
                  <IconStar size={16} />
                </ActionIcon>
              )}
              <ActionIcon variant="subtle" color="red"
                onClick={() => {
                  if (confirm('¿Eliminar esta cuenta?'))
                    eliminar.mutate(Number(c.id))
                }}
                aria-label="Eliminar cuenta"
              >
                <IconTrash size={16} />
              </ActionIcon>
            </Group>
          </Group>
        </Card>
      ))}
      {!isLoading && cuentas.length === 0 && (
        <Text size="sm" c="dimmed">No hay cuentas bancarias registradas.</Text>
      )}
      <CuentaBancariaModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
      />
    </Stack>
  )
}
