"use client";

import { confirmar, StatusBadge, notificar } from '@/components/ui'
import {
  Stack,
  Text,
  Card,
  Group,
  Timeline,
  ActionIcon,
  Tooltip,
} from "@mantine/core";
import {
  IconPlane,
  IconBus,
  IconShip,
  IconMapPin,
  IconTrash,
} from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import React from "react";
import { useTramos } from "../hooks/useViaticos";
import { viaticoService } from "../services/viaticoService";
import type { TramoViatico } from "@/types/api";
import { formatFechaHora } from "@/lib/fecha";

interface Props {
  viaticoId: number;
  puedeEditar?: boolean;
}

const TIPO_ICONS: Record<string, React.ReactNode> = {
  aereo: <IconPlane size={14} />,
  terrestre: <IconBus size={14} />,
  maritimo: <IconShip size={14} />,
};

const TIPO_TRAMO_LABELS: Record<string, string> = {
  ida:     'IDA',
  destino: 'DESTINO',
  escala:  'PARADA/ESCALA',
  regreso: 'REGRESO',
}

function LugarText({
  tipo,
  provincia,
  canton,
  pais,
  ciudad,
}: {
  tipo: string;
  provincia?: { nombre?: string } | null;
  canton?: { nombre?: string } | null;
  pais?: string | null;
  ciudad: string;
}) {
  if (tipo === "nacional") {
    return (
      <Text size="sm">
        {[provincia?.nombre, canton?.nombre, ciudad]
          .filter(Boolean)
          .join(" / ")}
      </Text>
    );
  }
  return <Text size="sm">{[pais, ciudad].filter(Boolean).join(" / ")}</Text>;
}

export function TramosList({ viaticoId, puedeEditar }: Props) {
  const { data: tramos = [], isLoading } = useTramos(viaticoId);
  const qc = useQueryClient();

  const eliminar = useMutation({
    mutationFn: (tramoId: number) =>
      viaticoService.tramos.eliminar(viaticoId, tramoId),
    onSuccess: () => {
      notificar.exito("Tramo eliminado", "El tramo fue eliminado del itinerario.");
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      qc.invalidateQueries({ queryKey: ["viatico", viaticoId] });
    },
    onError: notificar.alFallar("No se pudo eliminar el tramo"),
  });

  if (isLoading) {
    return (
      <Text size="sm" c="dimmed">
        Cargando itinerario...
      </Text>
    );
  }

  const lista = tramos as TramoViatico[];

  if (lista.length === 0) {
    return (
      <Text size="sm" c="dimmed" ta="center" py="md">
        Sin tramos registrados. Agrega el itinerario del viaje.
      </Text>
    );
  }

  return (
    <Stack gap="xs">
      <Timeline active={lista.length} bulletSize={24} lineWidth={2}>
        {lista.map((t) => {
          const tipoVehiculo =
            t.empresa?.catalogo?.tipo_vehiculo ?? "terrestre";
          const requiereAuth =
            t.empresa?.catalogo?.requiere_autorizacion ?? false;
          const estadoAuth = t.autorizacion_vuelo?.estado;

          return (
            <Timeline.Item
              key={t.id}
              bullet={TIPO_ICONS[tipoVehiculo] ?? <IconBus size={14} />}
              title={
                <Group gap="xs" justify="space-between">
                  <Group gap="xs">
                    <Text size="sm" fw={600}>
                      Tramo {t.orden}
                    </Text>
                    <StatusBadge size="xs">
                      {t.empresa?.nombre ?? "—"}
                    </StatusBadge>
                    {t.tipo_tramo && (
                      <StatusBadge size="xs">
                        {TIPO_TRAMO_LABELS[t.tipo_tramo] ?? t.tipo_tramo}
                      </StatusBadge>
                    )}
                    {requiereAuth && (
                      <StatusBadge
                        tone={estadoAuth === 'aprobada' ? 'success' : estadoAuth === 'rechazada' ? 'danger' : 'warning'}
                        size="xs"
                        variant="dot"
                      >
                        Auth. vuelo:{" "}
                        {estadoAuth === "aprobada"
                          ? "aprobada"
                          : estadoAuth === "rechazada"
                            ? "rechazada"
                            : "pendiente"}
                      </StatusBadge>
                    )}
                  </Group>
                  {puedeEditar && (
                    <Tooltip label="Eliminar tramo">
                      <ActionIcon
                        size="xs"
                        color="red"
                        variant="subtle"
                        loading={eliminar.isPending}
                        onClick={() =>
                          confirmar({
                            title: "Eliminar tramo",
                            message:
                              "Se eliminará este tramo del itinerario. No se puede deshacer.",
                            destructiva: true,
                            onConfirm: () => eliminar.mutate(t.id),
                          })
                        }
                      >
                        <IconTrash size={12} />
                      </ActionIcon>
                    </Tooltip>
                  )}
                </Group>
              }
            >
              <Card withBorder radius="sm" p="xs" mt={4}>
                <Group gap="xl" wrap="wrap">
                  <div>
                    <Group gap={4} mb={2}>
                      <IconMapPin size={12} />
                      <Text size="xs" c="dimmed">
                        Origen
                      </Text>
                    </Group>
                    <LugarText
                      tipo={t.origen_tipo}
                      provincia={t.origen_provincia}
                      canton={t.origen_canton}
                      pais={t.origen_pais}
                      ciudad={t.origen_ciudad}
                    />
                    <Text size="xs" c="dimmed" mt={2}>
                      {formatFechaHora(t.datetime_salida)}
                    </Text>
                  </div>
                  <div>
                    <Group gap={4} mb={2}>
                      <IconMapPin size={12} />
                      <Text size="xs" c="dimmed">
                        Destino
                      </Text>
                    </Group>
                    <LugarText
                      tipo={t.destino_tipo}
                      provincia={t.destino_provincia}
                      canton={t.destino_canton}
                      pais={t.destino_pais}
                      ciudad={t.destino_ciudad}
                    />
                    <Text size="xs" c="dimmed" mt={2}>
                      {formatFechaHora(t.datetime_llegada)}
                    </Text>
                  </div>
                </Group>
              </Card>
            </Timeline.Item>
          );
        })}
      </Timeline>
    </Stack>
  );
}
