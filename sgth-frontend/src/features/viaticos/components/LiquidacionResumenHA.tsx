"use client";

import { Stack, Card, Text, Group, Divider, Alert } from "@mantine/core";

interface Props {
  montoAsignado: number;
  montoAnticipo: number;
  monto70: number;
  monto30: number;
  totalHospAli: number;
  totalMovilizacion: number;
  porcentajeHA: number;
  justificadoCompleto: boolean;
  diferenciaDevolver: number;
  modalidad: string;
}

export function LiquidacionResumenHA({
  montoAsignado,
  montoAnticipo,
  monto70,
  monto30,
  totalHospAli,
  totalMovilizacion,
  porcentajeHA,
  justificadoCompleto,
  diferenciaDevolver,
  modalidad,
}: Props) {
  const haydatos = totalHospAli > 0 || totalMovilizacion > 0;
  if (!haydatos) return null;

  return (
    <Stack gap="sm">
      <Card withBorder radius="md" p="sm">
        <Text size="xs" fw={700} c="blue" mb="xs">
          Viático diario — Hospedaje y Alimentación
        </Text>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Monto asignado:
          </Text>
          <Text size="xs" fw={600}>
            ${montoAsignado.toFixed(2)}
          </Text>
        </Group>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            70% a justificar (H&A):
          </Text>
          <Text size="xs" fw={600}>
            ${monto70.toFixed(2)}
          </Text>
        </Group>
        {montoAnticipo > 0 && (
          <Group justify="space-between">
            <Text size="xs" c="dimmed">
              Anticipo entregado:
            </Text>
            <Text size="xs" fw={600}>
              ${montoAnticipo.toFixed(2)}
            </Text>
          </Group>
        )}
        <Divider my={4} />
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            Total H&A presentado:
          </Text>
          <Text size="xs" fw={700} c={justificadoCompleto ? "teal" : "orange"}>
            ${totalHospAli.toFixed(2)} ({porcentajeHA}%)
          </Text>
        </Group>
        {diferenciaDevolver > 0 && (
          <Group justify="space-between" mt={4}>
            <Text size="xs" c="red" fw={600}>
              A devolver a la institución:
            </Text>
            <Text size="xs" c="red" fw={700}>
              ${diferenciaDevolver.toFixed(2)}
            </Text>
          </Group>
        )}
        {!justificadoCompleto &&
          diferenciaDevolver === 0 &&
          modalidad === "sin_anticipo" && (
            <Alert color="yellow" variant="light" p="xs" mt={4}>
              <Text size="xs">
                Faltan <strong>${(monto70 - totalHospAli).toFixed(2)}</strong>{" "}
                en H&A. Recibirás solo lo justificado + el 30% devengado ($
                {monto30.toFixed(2)}).
              </Text>
            </Alert>
          )}
        {justificadoCompleto && (
          <Alert color="teal" variant="light" p="xs" mt={4}>
            <Text size="xs">
              Justificación completa del 70%. Recibirás el 30% devengado
              adicional (${monto30.toFixed(2)}).
            </Text>
          </Alert>
        )}
      </Card>

      {totalMovilizacion > 0 && (
        <Card withBorder radius="md" p="sm">
          <Text size="xs" fw={700} c="orange" mb="xs">
            Movilización (rubro independiente)
          </Text>
          <Group justify="space-between">
            <Text size="xs" c="dimmed">
              Total movilización:
            </Text>
            <Text size="xs" fw={600} c="orange">
              ${totalMovilizacion.toFixed(2)}
            </Text>
          </Group>
          <Text size="xs" c="dimmed" mt={4}>
            Se presenta como respaldo adicional. No afecta el cálculo del
            viático diario.
          </Text>
        </Card>
      )}
    </Stack>
  );
}
