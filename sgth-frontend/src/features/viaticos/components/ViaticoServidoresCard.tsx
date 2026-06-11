"use client";

import {
  Card,
  Group,
  Text,
  Badge,
  Button,
  Divider,
  Stack,
  ThemeIcon,
} from "@mantine/core";
import { IconUsers, IconPencil } from "@tabler/icons-react";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  viatico: ViaticoConRelaciones;
  puedeEditar: boolean;
  onEditar: () => void;
}

export function ViaticoServidoresCard({
  viatico: d,
  puedeEditar,
  onEditar,
}: Props) {
  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon variant="default" size="sm">
            <IconUsers size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">
            Servidores en comisión
          </Text>
        </Group>
        {puedeEditar && (
          <Button
            size="xs"
            variant="light"
            leftSection={<IconPencil size={12} />}
            onClick={onEditar}
          >
            Editar
          </Button>
        )}
      </Group>
      <Divider mb="sm" />
      <Stack gap="xs">
        {(d.todos_servidores ?? []).length === 0 ? (
          <Text size="sm" c="dimmed">
            Solo el servidor titular.
          </Text>
        ) : (
          (d.todos_servidores ?? []).map((vs) => (
            <Group key={vs.id} gap="xs">
              <Badge
                size="xs"
                color={vs.es_titular ? "blue" : "gray"}
                variant="light"
              >
                {vs.es_titular ? "Titular" : "Acompañante"}
              </Badge>
              <Text size="sm">
                {[vs.servidor?.nombre, vs.servidor?.apellido]
                  .filter(Boolean)
                  .join(" ") || "—"}
              </Text>
              <Text size="xs" c="dimmed">
                {vs.servidor?.puesto?.cargo?.nombre ?? ""}
              </Text>
            </Group>
          ))
        )}
      </Stack>
    </Card>
  );
}
