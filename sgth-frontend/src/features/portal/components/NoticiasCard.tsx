'use client'

import {
  Card, Stack, Group, Text,
  ThemeIcon, Center,
} from '@mantine/core'
import { IconSpeakerphone } from '@tabler/icons-react'

export function NoticiasCard() {
  return (
    <Card withBorder radius="xl" p="xl">
      <Stack gap="md">
        <Group gap="xs">
          <ThemeIcon color="orange" variant="light" size="md">
            <IconSpeakerphone size={16} />
          </ThemeIcon>
          <Text fw={600} size="md">
            Noticias y comunicados
          </Text>
        </Group>

        <Center py="xl">
          <Stack gap={4} align="center">
            <ThemeIcon
              color="gray"
              variant="light"
              size={48}
              radius="xl"
            >
              <IconSpeakerphone size={24} />
            </ThemeIcon>
            <Text size="sm" c="dimmed" ta="center" maw={280}>
              Aquí aparecerán los comunicados y noticias
              publicados por Talento Humano.
            </Text>
            <Text size="xs" c="dimmed" ta="center">
              Funcionalidad próximamente disponible
            </Text>
          </Stack>
        </Center>
      </Stack>
    </Card>
  )
}
