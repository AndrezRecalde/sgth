"use client";

import { Center, Pagination, Skeleton, Stack, Text } from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import { IconStethoscope } from "@tabler/icons-react";
import { useState } from "react";
import { useDisclosure } from "@mantine/hooks";
import { useQuery } from "@tanstack/react-query";
import { consultaMedicaService } from "../services/consultaMedicaService";
import { DetalleConsultaDrawer } from "./DetalleConsultaDrawer";
import { ConsultaItem } from "./ConsultaItem";
import { EmptyState } from "@/components/ui/EmptyState";
import { DataState } from "@/components/ui";
import { fromDateValueOrUndefined } from '@/lib/fecha'

interface Props {
  historiaClinicaId: number;
}

export function TabHistorial({ historiaClinicaId }: Props) {
  const [consultaSelId, setConsultaSelId] = useState<number | null>(null);
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false);

  const [page, setPage] = useState(1);
  const [rango, setRango] = useState<[Date | null, Date | null]>([null, null]);

  const filtros = {
    page,
    fecha_desde: fromDateValueOrUndefined(rango[0]),
    fecha_hasta: fromDateValueOrUndefined(rango[1]),
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
  //
  // Esto se resolvía aquí a mano y en ningún otro tab del panel. Ahora el
  // bloque vive en `DataState`, así que Receta, Certificado, Resultados y el
  // contexto del paciente dicen lo mismo con el mismo aspecto.
  if (isError) {
    return (
      <Stack p="md">
        <DataState
          loading={false}
          error={error}
          errorTitle="No se pudo cargar el historial"
          errorHint="No quiere decir que el paciente no tenga consultas previas: no se pudieron consultar."
          onRetry={() => refetch()}
        />
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
