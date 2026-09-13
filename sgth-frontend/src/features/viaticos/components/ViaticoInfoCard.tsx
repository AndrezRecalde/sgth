"use client";

import { StatusBadge } from "@/components/ui";
import {
  Card,
  Group,
  Text,
  Button,
  Divider,
  Stack,
  ThemeIcon,
} from "@mantine/core";
import { IconClipboardList, IconPencil } from "@tabler/icons-react";
import type { ViaticoConRelaciones } from "@/types/api";
import { formatFechaHora as fmt } from "@/lib/fecha";

interface Props {
  viatico: ViaticoConRelaciones;
  puedeEditar: boolean;
  onEditar: () => void;
}

import { ZONA_LABELS } from "../constants/viatico.constants";

export function ViaticoInfoCard({ viatico: d, puedeEditar, onEditar }: Props) {
  const servidor = d.servidor;
  const nombreCompleto = [servidor?.nombre, servidor?.apellido]
    .filter(Boolean)
    .join(" ");

  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon variant="default" size="sm">
            <IconClipboardList size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">
            Información general
          </Text>
        </Group>
        {puedeEditar && (
          <Button
            size="xs"
            variant="light"
            leftSection={<IconPencil size={12} />}
            onClick={onEditar}
          >
            Gestionar
          </Button>
        )}
      </Group>
      <Divider mb="sm" />
      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Servidor
          </Text>
          <Text size="sm" fw={500}>
            {nombreCompleto || "—"}
          </Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Cargo
          </Text>
          <Text size="sm">{servidor?.puesto?.cargo?.nombre ?? "—"}</Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Unidad
          </Text>
          <Text size="sm">
            {servidor?.puesto?.unidad_administrativa?.nombre ?? "—"}
          </Text>
        </Group>
        <Divider />
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Zona
          </Text>
          <Text size="sm" fw={500}>
            {ZONA_LABELS[d.zona ?? ""] ?? d.zona}
          </Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Salida
          </Text>
          <Text size="sm">{fmt(d.datetime_salida)}</Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Regreso
          </Text>
          <Text size="sm">{fmt(d.datetime_llegada)}</Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Total días
          </Text>
          <StatusBadge>
            {Number(d.total_dias ?? 0).toFixed(1)} días
          </StatusBadge>
        </Group>
        <Stack gap={2}>
          <Text size="xs" c="dimmed">
            Justificación
          </Text>
          <Text size="sm" lineClamp={4}>
            {d.justificacion ?? "—"}
          </Text>
        </Stack>
        {d.zona === "exterior" && d.pais_destino && (
          <>
            <Divider />
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                País destino
              </Text>
              <Text size="sm">{d.pais_destino as string}</Text>
            </Group>
            {d.coeficiente_exterior && (
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Coeficiente
                </Text>
                <Text size="sm">
                  {Number(d.coeficiente_exterior).toFixed(4)}
                </Text>
              </Group>
            )}
          </>
        )}
      </Stack>
    </Card>
  );
}
