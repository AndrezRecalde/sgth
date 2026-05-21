import { Group, Stack, Text, Divider } from '@mantine/core'

interface PageHeaderProps {
  title: string
  subtitle?: string
  icon?: React.ReactNode
  actions?: React.ReactNode
}

export function PageHeader({ title, subtitle, icon, actions }: PageHeaderProps) {
  return (
    <>
      <Group justify="space-between" align="flex-start" mb="md">
        <Group>
          {icon && (
            <div style={{ color: 'var(--mantine-color-emerald-6)' }}>
              {icon}
            </div>
          )}
          <Stack gap={0}>
            <Text fw={700} size="xl">
              {title}
            </Text>
            {subtitle && (
              <Text c="dimmed" size="sm">
                {subtitle}
              </Text>
            )}
          </Stack>
        </Group>
        
        {actions && (
          <Group>
            {actions}
          </Group>
        )}
      </Group>
      <Divider mb="xl" />
    </>
  )
}
