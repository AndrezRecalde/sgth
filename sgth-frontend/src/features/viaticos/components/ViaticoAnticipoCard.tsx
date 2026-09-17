"use client";

import { StatusBadge } from "@/components/ui";
import {
  Card,
  Group,
  Text,
  Divider,
  Stack,
  ThemeIcon,
} from "@mantine/core";
import { IconCurrencyDollar } from "@tabler/icons-react";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  viatico: ViaticoConRelaciones;
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return "—";
  return `$${Number(v).toFixed(2)}`;
}

import { MODALIDAD_LABELS } from "../constants/viatico.constants";

export function ViaticoAnticipoCard({ viatico: d }: Props) {
  return (
    <Card withBorder radius="md" h="100%">
      <Group gap="xs" mb="sm">
        <ThemeIcon variant="default" size="sm">
          <IconCurrencyDollar size={14} />
        </ThemeIcon>
        <Text fw={600} size="sm">
          Anticipo y monto
        </Text>
      </Group>
      <Divider mb="sm" />
      <Stack gap="xs">
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Modalidad
          </Text>
          <StatusBadge>
            {MODALIDAD_LABELS[d.modalidad_anticipo ?? ""] ??
              d.modalidad_anticipo}
          </StatusBadge>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Monto calculado
          </Text>
          <Text fw={700} c="emerald" size="md">
            {fmtMonto(d.monto_calculado)}
          </Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Anticipo a entregar
          </Text>
          <Text fw={600} size="sm">
            {fmtMonto(d.monto_anticipo)}
          </Text>
        </Group>

        {/* El respaldo que asigna Financiero; se imprime en el comprobante. */}
        {(d.numero_resolucion || d.partida_presupuestaria) && (
          <>
            <Divider my={2} />
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                Resolución
              </Text>
              <Text size="xs" fw={600}>
                {d.numero_resolucion ?? "—"}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                Partida presupuestaria
              </Text>
              <Text size="xs" fw={600} ff="monospace">
                {d.partida_presupuestaria ?? "—"}
              </Text>
            </Group>
          </>
        )}
      </Stack>
    </Card>
  );
}
