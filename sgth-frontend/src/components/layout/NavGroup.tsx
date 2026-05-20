import { Text, Box } from '@mantine/core'

interface Props { label: string; collapsed: boolean }

export function NavGroup({ label, collapsed }: Props) {
  if (collapsed) return <Box mt="md" mb="xs" style={{ borderTop: '1px solid rgba(255,255,255,0.07)' }} />
  return (
    <Text size="xs" fw={600} tt="uppercase" mt="md" mb="xs" px="md" c="rgba(255,255,255,0.35)">
      {label}
    </Text>
  )
}
