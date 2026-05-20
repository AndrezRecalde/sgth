import { Group, Avatar, Text, Box } from '@mantine/core'
import { useAuth } from '@/hooks/useAuth'

interface Props { collapsed: boolean }

export function SidebarUserRow({ collapsed }: Props) {
  const { usuario } = useAuth()
  if (!usuario) return null

  const initials = usuario.name.substring(0, 2).toUpperCase()
  const role = usuario.roles?.[0] || 'Usuario'

  return (
    <Box p="md" style={{ borderBottom: '1px solid rgba(255,255,255,0.07)' }}>
      <Group wrap="nowrap" justify={collapsed ? 'center' : 'flex-start'}>
        <Avatar color="emerald" radius="xl">{initials}</Avatar>
        {!collapsed && (
          <Box style={{ flex: 1, overflow: 'hidden' }}>
            <Text size="sm" fw={500} c="rgba(255,255,255,0.85)" truncate>{usuario.name}</Text>
            <Text size="xs" c="rgba(255,255,255,0.45)" truncate>{role}</Text>
          </Box>
        )}
      </Group>
    </Box>
  )
}
