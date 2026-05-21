'use client'

import { Drawer, Tabs, Avatar, Group, Stack, Text, Badge } from '@mantine/core'
import {
  IconUser, IconFileText, IconBuildingBank,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { ContratosTab } from './tabs/ContratosTab'
import { CuentasBancariasTab } from './tabs/CuentasBancariasTab'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor: ServidorConRelaciones | null
}

function getInitials(s: ServidorConRelaciones) {
  const n = s.nombres?.charAt(0) ?? ''
  const a = s.apellidos?.charAt(0) ?? ''
  return `${n}${a}`.toUpperCase() || '?'
}

export function ServidorDetail({ opened, onClose, servidor }: Props) {
  const { isMobile } = useMobileBreakpoint()

  if (!servidor) return null

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title="Expediente del servidor"
      position="right"
      size={isMobile ? '100%' : 680}
      padding="lg"
    >
      <Stack gap="md">
        <Group>
          <Avatar size={56} radius="xl" color="emerald">
            {getInitials(servidor)}
          </Avatar>
          <Stack gap={2}>
            <Text fw={700} size="lg">
              {servidor.apellidos} {servidor.nombres}
            </Text>
            <Text size="sm" c="dimmed">
              C.I. {servidor.cedula ?? '-'}
            </Text>
            {servidor.contrato_vigente && (
              <Badge color="emerald" variant="light" size="sm">
                {servidor.contrato_vigente.puesto?.nombre ?? 'Sin cargo'}
              </Badge>
            )}
          </Stack>
        </Group>

        <Tabs defaultValue="personal" color="emerald">
          <Tabs.List>
            <Tabs.Tab value="personal"
              leftSection={<IconUser size={14} />}>
              Personal
            </Tabs.Tab>
            <Tabs.Tab value="contratos"
              leftSection={<IconFileText size={14} />}>
              Contratos
            </Tabs.Tab>
            <Tabs.Tab value="cuentas"
              leftSection={<IconBuildingBank size={14} />}>
              Cuentas
            </Tabs.Tab>
          </Tabs.List>

          <Tabs.Panel value="personal" pt="md">
            <Stack gap="xs">
              {([
                ['Género', servidor.genero],
                ['Estado civil', servidor.estado_civil],
                ['Fecha de nacimiento', servidor.fecha_nacimiento],
                ['Teléfono personal', servidor.telefono_personal],
                ['Teléfono institucional', servidor.telefono_institucional],
                ['Correo personal', servidor.correo_personal],
                ['Correo institucional', servidor.correo_institucional],
                ['Dirección', servidor.direccion],
              ] as [string, string | undefined][]).map(([label, value]) => (
                <Group key={label} justify="space-between">
                  <Text size="sm" c="dimmed" w={160}>{label}</Text>
                  <Text size="sm" fw={500}>{value ?? '-'}</Text>
                </Group>
              ))}
            </Stack>
          </Tabs.Panel>

          <Tabs.Panel value="contratos" pt="md">
            <ContratosTab servidorId={Number((servidor as unknown as { id: number }).id)} />
          </Tabs.Panel>

          <Tabs.Panel value="cuentas" pt="md">
            <CuentasBancariasTab servidorId={Number((servidor as unknown as { id: number }).id)} />
          </Tabs.Panel>
        </Tabs>
      </Stack>
    </Drawer>
  )
}
