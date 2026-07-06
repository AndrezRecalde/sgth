"use client";

import {
  Stack,
  Text,
  Card,
  Group,
  Badge,
  Skeleton,
  Button,
  ThemeIcon,
} from "@mantine/core";
import { IconStethoscope } from "@tabler/icons-react";
import { useState } from "react";
import { useDisclosure } from "@mantine/hooks";
import { useQuery } from "@tanstack/react-query";
import { consultaMedicaService } from "../services/consultaMedicaService";
import { DetalleConsultaDrawer } from "./DetalleConsultaDrawer";
import { EmptyState } from "@/components/ui/EmptyState";
import type { ConsultaMedica } from "../services/consultaMedicaService";

interface Props {
  historiaClinicaId: number;
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString("es-EC", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function ConsultaItem({
  consulta,
  onVerDetalle,
}: {
  consulta: ConsultaMedica;
  onVerDetalle: (id: number) => void;
}) {
  return (
    <Card withBorder radius="md" p="sm">
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="xs">
            <ThemeIcon size="sm" color="blue" variant="light">
              <IconStethoscope size={12} />
            </ThemeIcon>
            <Text size="sm" fw={500}>
              {formatFecha(consulta.fecha_consulta)}
            </Text>
            {consulta.tipo_atencion && (
              <Badge size="xs" variant="light" color="gray">
                {consulta.tipo_atencion.replace("_", " ")}
              </Badge>
            )}
            {consulta.tipo_diagnostico && (
              <Badge
                size="xs"
                variant="light"
                color={
                  consulta.tipo_diagnostico === "definitivo"
                    ? "emerald"
                    : "orange"
                }
              >
                {consulta.tipo_diagnostico}
              </Badge>
            )}
          </Group>
          <Button
            size="compact-xs"
            variant="subtle"
            color="blue"
            onClick={() => onVerDetalle(consulta.id)}
          >
            Ver detalle
          </Button>
        </Group>

        <Text size="xs" c="dimmed">
          Dr. {consulta.medico?.nombre_completo ?? "—"}
        </Text>
      </Stack>
    </Card>
  );
}

export function TabHistorial({ historiaClinicaId }: Props) {
  const [consultaSelId, setConsultaSelId] = useState<number | null>(null);
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false);

  const { data: consultas = [], isLoading } = useQuery({
    queryKey: ["consultas", "historial", historiaClinicaId],
    queryFn: () => consultaMedicaService.listarPorHistoria(historiaClinicaId),
    enabled: !!historiaClinicaId,
    staleTime: 1000 * 30,
  });

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    );
  }

  if (consultas.length === 0) {
    return (
      <Stack p="md">
        <EmptyState
          icon={IconStethoscope}
          title="Sin consultas previas"
          description="Este paciente no tiene
            consultas anteriores registradas."
        />
      </Stack>
    );
  }

  return (
    <Stack gap="sm" p="md">
      <Text size="xs" c="dimmed">
        {consultas.length} consulta{consultas.length !== 1 ? "s" : ""}{" "}
        registrada{consultas.length !== 1 ? "s" : ""}
      </Text>
      {consultas.map((consulta) => (
        <ConsultaItem
          key={consulta.id}
          consulta={consulta}
          onVerDetalle={(id) => {
            setConsultaSelId(id);
            abrirDrawer();
          }}
        />
      ))}

      <DetalleConsultaDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
        consultaId={consultaSelId}
      />
    </Stack>
  );
}
