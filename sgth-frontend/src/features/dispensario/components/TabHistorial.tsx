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
  Alert,
  Center,
  Pagination,
} from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import {
  IconStethoscope, IconAlertTriangle, IconRefresh,
} from "@tabler/icons-react";
import { useState } from "react";
import { useDisclosure } from "@mantine/hooks";
import { useQuery } from "@tanstack/react-query";
import { consultaMedicaService } from "../services/consultaMedicaService";
import { DetalleConsultaDrawer } from "./DetalleConsultaDrawer";
import { EmptyState } from "@/components/ui/EmptyState";
import { getApiErrorMessage } from "@/types/api";
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

/** `Date` → 'YYYY-MM-DD' sin pasar por UTC, que restaría un día. */
function aIso(d: Date | string | null): string | undefined {
  if (!d) return undefined;
  if (typeof d === "string") return d.slice(0, 10);
  if (isNaN(d.getTime())) return undefined;
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, "0"),
    String(d.getDate()).padStart(2, "0"),
  ].join("-");
}

export function TabHistorial({ historiaClinicaId }: Props) {
  const [consultaSelId, setConsultaSelId] = useState<number | null>(null);
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false);

  const [page, setPage] = useState(1);
  const [rango, setRango] = useState<[Date | null, Date | null]>([null, null]);

  const filtros = {
    page,
    fecha_desde: aIso(rango[0]),
    fecha_hasta: aIso(rango[1]),
  };

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ["consultas", "historial", historiaClinicaId, filtros],
    queryFn: () =>
      consultaMedicaService.listarPorHistoria(historiaClinicaId, filtros),
    enabled: !!historiaClinicaId,
    staleTime: 1000 * 30,
    placeholderData: (anterior) => anterior,
  });

  const consultas = data?.consultas ?? [];
  const hayFiltros = !!(rango[0] || rango[1]);

  const cambiarRango = (v: [Date | null, Date | null]) => {
    setRango(v);
    setPage(1);
  };

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
        <Skeleton height={80} radius="md" />
      </Stack>
    );
  }

  // El fallo se dice, no se disfraza de historial vacío. Antes cualquier error
  // dejaba la lista en cero y la pantalla afirmaba que el paciente no tenía
  // consultas previas, que en una historia clínica es lo peor que puede decir.
  if (isError) {
    return (
      <Stack p="md">
        <Alert
          icon={<IconAlertTriangle size={16} />}
          color="red"
          variant="light"
          title="No se pudo cargar el historial"
        >
          <Stack gap="xs" align="flex-start">
            <Text size="xs">
              {getApiErrorMessage(error)} No quiere decir que el paciente no
              tenga consultas previas: no se pudieron consultar.
            </Text>
            <Button
              size="compact-xs"
              variant="light"
              color="red"
              leftSection={<IconRefresh size={13} />}
              onClick={() => refetch()}
            >
              Reintentar
            </Button>
          </Stack>
        </Alert>
      </Stack>
    );
  }

  const filtroFechas = (
    <DatePickerInput
      type="range"
      size="xs"
      label="Filtrar por fechas"
      placeholder="Todo el historial"
      valueFormat="DD/MM/YYYY"
      clearable
      value={rango}
      onChange={(v) => cambiarRango(v as [Date | null, Date | null])}
    />
  );

  if (consultas.length === 0) {
    return (
      <Stack gap="sm" p="md">
        {filtroFechas}
        <EmptyState
          icon={IconStethoscope}
          title={hayFiltros ? "Sin consultas en ese rango" : "Sin consultas previas"}
          description={
            hayFiltros
              ? "Ninguna consulta de este paciente cae en las fechas elegidas."
              : "Este paciente no tiene consultas anteriores registradas."
          }
        />
      </Stack>
    );
  }

  return (
    <Stack gap="sm" p="md">
      {filtroFechas}

      <Text size="xs" c="dimmed">
        {data?.total} consulta{data?.total !== 1 ? "s" : ""}{" "}
        registrada{data?.total !== 1 ? "s" : ""}
        {hayFiltros ? " en el rango elegido" : ""}
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

      {(data?.ultimaPagina ?? 1) > 1 && (
        <Center>
          <Pagination
            size="sm"
            value={page}
            onChange={setPage}
            total={data?.ultimaPagina ?? 1}
            withEdges
          />
        </Center>
      )}

      <DetalleConsultaDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
        consultaId={consultaSelId}
      />
    </Stack>
  );
}
