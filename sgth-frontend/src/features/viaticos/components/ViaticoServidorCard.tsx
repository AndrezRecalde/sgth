"use client";

import { Card, Group, Text, ThemeIcon } from "@mantine/core";
import { IconUser } from "@tabler/icons-react";
import { StatusBadge } from "@/components/ui";

interface Props {
  nombre: string;
  cargo?: string | null;
  unidad?: string | null;
}

/** Quién solicita: el nombre, el cargo y la unidad del usuario con sesión. */
export function ViaticoServidorCard({ nombre, cargo, unidad }: Props) {
  return (
    <Card withBorder radius="md" p="sm" bg="var(--sgth-surface-sunken)">
      <Group gap="sm">
        <ThemeIcon variant="light" size="lg" radius="xl">
          <IconUser size={18} />
        </ThemeIcon>
        <div>
          <Text fw={600} size="sm">
            {nombre}
          </Text>
          <Text size="xs" c="dimmed">
            {cargo ?? "Sin cargo asignado"}
          </Text>
          {unidad && (
            <Text size="xs" c="dimmed">
              {unidad}
            </Text>
          )}
        </div>
        <StatusBadge size="xs" ml="auto">
          Solicitante
        </StatusBadge>
      </Group>
    </Card>
  );
}
