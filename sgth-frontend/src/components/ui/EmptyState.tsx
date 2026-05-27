'use client'

import { Stack, Text, ThemeIcon } from '@mantine/core'
import type { Icon } from '@tabler/icons-react'

interface Props {
  icon:         Icon
  title:        string
  description?: string
  action?:      React.ReactNode
}

export function EmptyState({ icon: IconComponent, title, description, action }: Props) {
  return (
    <Stack align="center" gap="md" py="xl">
      <ThemeIcon size={56} radius="xl" variant="light" color="gray">
        <IconComponent size={28} />
      </ThemeIcon>
      <Stack align="center" gap={4}>
        <Text fw={600} size="md" c="dimmed">{title}</Text>
        {description && (
          <Text size="sm" c="dimmed" ta="center" maw={360}>
            {description}
          </Text>
        )}
      </Stack>
      {action}
    </Stack>
  )
}
