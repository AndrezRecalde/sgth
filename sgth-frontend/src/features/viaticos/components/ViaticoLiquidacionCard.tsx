"use client";

import { Card, Group, Text, Divider, Stack, ThemeIcon } from "@mantine/core";
import { IconFileInvoice, IconChecks } from "@tabler/icons-react";
import { formatFecha } from "@/lib/fecha";
import { LiquidacionSection } from "./LiquidacionSection";
import { CalculoViaticoCard } from "./CalculoViaticoCard";
import { RevisionComprobantes } from "./RevisionComprobantes";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  viatico: ViaticoConRelaciones;
  estadoActual: string;
  onSuccess: () => void;
}

/*
| La liquidación en la ficha del viático: se presenta mientras está pendiente y
| se consulta después.
|
| La cuenta la resuelve el backend y llega en `viatico.calculo`. Aquí se
| rehacía entera —con otra fórmula que la de la pantalla de liquidación y la de
| los PDF— y no mostraba el 30 % que se reconoce sin comprobante.
*/

export function ViaticoLiquidacionCard({
  viatico: d,
  estadoActual,
  onSuccess,
}: Props) {
  return (
    <Card withBorder radius="md">
      <Group gap="xs" mb="sm">
        <ThemeIcon variant="light" size="sm">
          <IconFileInvoice size={14} />
        </ThemeIcon>
        <Text fw={600} size="sm">
          Liquidación
        </Text>
      </Group>
      <Divider mb="sm" />

      {estadoActual === "pendiente_liquidacion" ? (
        <LiquidacionSection viatico={d} onSuccess={onSuccess} />
      ) : d.liquidacion ? (
        <Stack gap="xs">
          {d.calculo && <CalculoViaticoCard calculo={d.calculo} />}

          {(d.liquidacion.actividades?.length ?? 0) > 0 && (
            <Stack gap={4}>
              <Text size="xs" fw={600} c="dimmed">
                ACTIVIDADES REALIZADAS
              </Text>
              {d.liquidacion.actividades!.map((a, i) => (
                <Group key={i} gap="xs">
                  <ThemeIcon size="xs" variant="light" radius="xl">
                    <IconChecks size={8} />
                  </ThemeIcon>
                  <Text size="xs">
                    {a.fecha ? formatFecha(a.fecha) : "—"}
                    {" — "}
                    {a.lugar}
                  </Text>
                </Group>
              ))}
            </Stack>
          )}

          <RevisionComprobantes viatico={d} />
        </Stack>
      ) : (
        <Text size="sm" c="dimmed">
          Pendiente de registrar la liquidación.
        </Text>
      )}
    </Card>
  );
}
