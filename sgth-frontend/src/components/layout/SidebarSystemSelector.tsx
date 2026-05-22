import { Group, Text, Box } from '@mantine/core'

interface Props { collapsed: boolean }

export function SidebarSystemSelector({ collapsed }: Props) {
  const version = process.env.NEXT_PUBLIC_APP_VERSION || 'v1.0'
  
  return (
    <Box p="md" style={{ borderBottom: '1px solid rgba(255,255,255,0.07)' }}>
      <Group wrap="nowrap" justify={collapsed ? 'center' : 'space-between'}>
        <Text fw={800} size="xl">SGTH</Text>
        {!collapsed && <Text size="xs">{version}</Text>}
      </Group>
    </Box>
  )
}
