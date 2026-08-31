"use client";

import { Card, Group, Text, Divider, Alert } from "@mantine/core";
import type { Viatico } from "@/types/api";

interface Props {
  viatico: Viatico;
  totalFacturas: number;
}

export function FacturasResumen({ viatico, totalFacturas }: Props) {

  const montoAsignado = Number(viatico.monto_calculado ?? 0);
  const anticipo = Number(viatico.monto_anticipo ?? 0);
  const monto70 = Math.round(montoAsignado * 0.7 * 100) / 100;
  const modalidad = (viatico.modalidad_anticipo as string) ?? "sin_anticipo";

  const diferencia = modalidad === "sin_anticipo" ? 0 : monto70 - totalFacturas;

  const porcentajeJustif =
    monto70 > 0
      ? Math.min(Math.round((totalFacturas / monto70) * 100), 100)
      : 0;

  const justificadoCompleto = totalFacturas >= monto70;

  return (
    <Card withBorder radius="md" p="sm" bg="gray.0">
      <Group justify="space-between">
        <Text size="sm" c="dimmed">
          Monto total asignado:
        </Text>
        <Text size="sm" fw={600}>
          ${montoAsignado.toFixed(2)}
        </Text>
      </Group>
      <Group justify="space-between">
        <Text size="sm" c="dimmed">
          70% a justificar (H&A):
        </Text>
        <Text size="sm" fw={600}>
          ${monto70.toFixed(2)}
        </Text>
      </Group>
      {anticipo > 0 && (
        <Group justify="space-between">
          <Text size="sm" c="dimmed">
            Anticipo entregado:
          </Text>
          <Text size="sm" fw={600}>
            ${anticipo.toFixed(2)}
          </Text>
        </Group>
      )}
      <Group justify="space-between">
        <Text size="sm" c="dimmed">
          Total comprobantes:
        </Text>
        <Text size="sm" fw={600} c={justificadoCompleto ? "teal" : "orange"}>
          ${totalFacturas.toFixed(2)}{" "}
          <Text span size="xs" c={justificadoCompleto ? "teal" : "orange"}>
            ({porcentajeJustif}%)
          </Text>
        </Text>
      </Group>
      <Divider my={4} />
      <Group justify="space-between">
        <Text size="sm" fw={600}>
          A devolver a la institución:
        </Text>
        <Text size="sm" fw={700} c={justificadoCompleto ? "teal" : "orange"}>
          {diferencia >= 0 ? `$${diferencia.toFixed(2)}` : "$0.00"}
        </Text>
      </Group>
      {!justificadoCompleto && diferencia > 0 && (
        <Alert color="yellow" variant="light" p="xs" mt={4}>
          <Text size="xs">
            Faltan <strong>${diferencia.toFixed(2)}</strong> en H&A por
            justificar.
          </Text>
        </Alert>
      )}
      {diferencia < 0 && (
        <Alert color="orange" variant="light" p="xs" mt={4}>
          <Text size="xs">
            Los comprobantes exceden en{" "}
            <strong>${Math.abs(diferencia).toFixed(2)}</strong>. Gastos extras a
            cargo del servidor.
          </Text>
        </Alert>
      )}
    </Card>
  );
}
