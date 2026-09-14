"use client";

import {
  Card,
  Group,
  Text,
  Button,
  Divider,
  Stack,
  Alert,
  ThemeIcon,
} from "@mantine/core";
import {
  IconClipboardList,
  IconAlertCircle,
  IconPencil,
  IconCircleCheck,
} from "@tabler/icons-react";
import type { ActividadData } from "./ActividadesModal";
import { StatusBadge } from "@/components/ui";

interface Props {
  actividades: ActividadData[];
  /** Sin estas dos, la tarjeta es de solo lectura. */
  onRegistrar?: () => void;
  onEditar?: () => void;
}

export function LiquidacionActividadesCard({
  actividades,
  onRegistrar,
  onEditar,
}: Props) {
  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon variant="light" size="sm">
            <IconClipboardList size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">
            Informe de actividades
          </Text>
        </Group>
        {actividades.length > 0 && (
          <StatusBadge>
            {actividades.length}{" "}
            {actividades.length === 1 ? "actividad" : "actividades"}
          </StatusBadge>
        )}
      </Group>
      <Divider mb="sm" />

      {actividades.length === 0 ? (
        <Stack gap="xs" align="center" py="md">
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="amber"
            variant="light"
            w="100%"
          >
            <Text size="xs">
              Debe registrar las actividades realizadas durante la comisión.
            </Text>
          </Alert>
          {onRegistrar && (
            <Button
              variant="light"
              size="sm"
              leftSection={<IconClipboardList size={14} />}
              onClick={onRegistrar}
              fullWidth
            >
              Registrar actividades
            </Button>
          )}
        </Stack>
      ) : (
        <Stack gap="xs">
          {actividades.map((a, i) => (
            <Stack key={i} gap={2}>
              <Group gap="xs">
                <IconCircleCheck
                  size={14}
                  color="var(--mantine-color-emerald-6)"
                />
                <Text size="xs" fw={500}>
                  {a.fecha
                    ? new Date(a.fecha).toLocaleDateString("es-EC", {
                        timeZone: "UTC",
                        day: "2-digit",
                        month: "2-digit",
                      })
                    : "—"}
                  {" — "}
                  {a.lugar}
                </Text>
              </Group>
              {a.descripcion && (
                <Text size="xs" c="dimmed" ml={22}>
                  {a.descripcion}
                </Text>
              )}
            </Stack>
          ))}
          {onEditar && (
            <Button
              size="xs"
              variant="subtle"
              leftSection={<IconPencil size={12} />}
              onClick={onEditar}
            >
              Editar actividades
            </Button>
          )}
        </Stack>
      )}
    </Card>
  );
}
