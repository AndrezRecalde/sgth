"use client";

import { useState } from "react";
import {
  Stack,
  Text,
  Button,
  TextInput,
  Grid,
} from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useAuth } from "@/hooks/useAuth";
import { useQuery } from "@tanstack/react-query";
import { SgthTable } from "@/components/ui/SgthTable";
import { EmptyState } from "@/components/ui/EmptyState";
import { DataState } from "@/components/ui/DataState";
import { asistenciaService } from "../services/asistenciaService";
import { BuscarServidorSelect } from "@/features/expediente/components/BuscarServidorSelect";
import { IconSearch, IconClock, IconLock } from "@tabler/icons-react";
import type { MarcacionBiometrica, ServidorConRelaciones } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";
import { StatusBadge } from "@/components/ui";
import { formatFecha, fromDateValue } from '@/lib/fecha'

function formatHora(h?: string | null): string {
  if (!h) return "—";
  return h.substring(0, 5);
}

export function MarcacionesTab() {
  const contained = useContainedInput();
  const { usuario, hasPermiso, hasRole } = useAuth();
  const [servidorId, setServidorId] = useState<number | null>(null);
  const [elegido, setElegido] = useState<ServidorConRelaciones | null>(null);
  const [cedulaEscrita, setCedulaEscrita] = useState("");
  const [fechaInicio, setFechaInicio] = useState<Date | string | null>(null);
  const [fechaFin, setFechaFin] = useState<Date | string | null>(null);
  const [buscar, setBuscar] = useState(false);

  // Las de cualquier servidor, quien tiene `ver-asistencia-todos`; los demás,
  // solo las propias. Es la regla de MarcacionController::index: aquí solo se
  // evita ofrecer un selector que terminaría en 403.
  const veTodos = hasPermiso("ver-asistencia-todos");
  const propio = usuario?.servidor;
  const cedulaPropia = propio?.puede_marcar ? (propio.cedula ?? null) : null;

  // El selector buscaba en `useServidores()` sin parámetros, que trae solo la
  // primera página (15): Talento Humano no podía elegir a nadie más. El
  // buscador consulta el backend por nombre o cédula.
  const elegidoSinMarcacion = !!elegido && !elegido.puede_marcar;
  const cedulaElegida = elegido && !elegidoSinMarcacion ? (elegido.cedula ?? null) : null;

  // Buscar servidores es de Talento Humano (ServidorPolicy::verAny). La máxima
  // autoridad y auditoría ven la asistencia de todos pero no el listado de
  // expedientes: escriben la cédula, y el backend dice si existe y marca.
  const puedeBuscar = hasRole("admin-uath") || hasRole("asistente-uath") || hasRole("admin-ti");
  const cedulaEscritaValida = /^\d{10}$/.test(cedulaEscrita);

  const cedula = !veTodos
    ? cedulaPropia
    : puedeBuscar
      ? cedulaElegida
      : cedulaEscritaValida ? cedulaEscrita : null;

  const {
    data: marcaciones = [],
    isLoading,
    error,
    refetch,
  } = useQuery({
    queryKey: [
      "marcaciones",
      cedula,
      fromDateValue(fechaInicio),
      fromDateValue(fechaFin),
    ],
    queryFn: () =>
      asistenciaService.marcaciones.listar({
        cedula: cedula!,
        fecha_inicio: fromDateValue(fechaInicio),
        fecha_fin: fromDateValue(fechaFin),
      }),
    enabled: buscar && !!cedula && !!fechaInicio && !!fechaFin,
    staleTime: 0,
  });

  const columns: DataTableColumn<MarcacionBiometrica>[] = [
    {
      accessor: "Fecha",
      title: "Fecha",
      width: 110,
      render: ({ Fecha }) => (
        <Text size="sm">
          {formatFecha(Fecha)}
        </Text>
      ),
    },
    {
      accessor: "Entrada",
      title: "Entrada",
      width: 80,
      render: ({ Entrada, HoraEntradaProgramada }) => (
        <Text
          size="sm"
          c={
            Entrada && HoraEntradaProgramada && Entrada > HoraEntradaProgramada
              ? "amber"
              : "inherit"
          }
        >
          {formatHora(Entrada)}
        </Text>
      ),
    },
    {
      accessor: "AlmuerzoSalida",
      title: "Sal. Almuerzo",
      width: 100,
      render: ({ AlmuerzoSalida }) => (
        <Text size="sm">{formatHora(AlmuerzoSalida)}</Text>
      ),
    },
    {
      accessor: "AlmuerzoRetorno",
      title: "Ret. Almuerzo",
      width: 100,
      render: ({ AlmuerzoRetorno }) => (
        <Text size="sm">{formatHora(AlmuerzoRetorno)}</Text>
      ),
    },
    {
      accessor: "Salida",
      title: "Salida",
      width: 80,
      render: ({ Salida }) => <Text size="sm">{formatHora(Salida)}</Text>,
    },
    {
      accessor: "TipoPermiso",
      title: "Tipo",
      width: 110,
      render: ({ TipoPermiso }) =>
        TipoPermiso ? (
          <StatusBadge size="xs">
            {TipoPermiso}
          </StatusBadge>
        ) : null,
    },
  ];

  if (!veTodos && !cedulaPropia) {
    return (
      <EmptyState
        icon={IconLock}
        title="Su usuario no tiene la marcación biométrica habilitada"
        description="Aquí se consultan las marcaciones propias. Si debería tenerla, solicítelo a Talento Humano."
      />
    );
  }

  return (
    <Stack gap="md">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          {veTodos && !puedeBuscar ? (
            <TextInput
              label="Cédula del servidor"
              placeholder="10 dígitos"
              inputMode="numeric"
              maxLength={10}
              {...contained}
              value={cedulaEscrita}
              onChange={(e) => setCedulaEscrita(e.currentTarget.value.replace(/\D/g, ""))}
              error={
                cedulaEscrita && !cedulaEscritaValida
                  ? "La cédula tiene 10 dígitos."
                  : undefined
              }
            />
          ) : veTodos ? (
            <BuscarServidorSelect
              label="Servidor"
              value={servidorId}
              onChange={(id) => {
                setServidorId(id);
                if (id === null) setElegido(null);
              }}
              onSelect={setElegido}
              error={
                elegidoSinMarcacion
                  ? "Este servidor no tiene habilitada la marcación biométrica."
                  : undefined
              }
            />
          ) : (
            <TextInput
              label="Servidor"
              {...contained}
              value={`${cedulaPropia} — ${[propio?.apellido, propio?.nombre].filter(Boolean).join(" ")}`}
              readOnly
            />
          )}
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <DatePickerInput
            label="Fecha inicio"
            placeholder="Desde"
            valueFormat="YYYY-MM-DD"
            {...contained}
            value={fechaInicio}
            onChange={(val) => setFechaInicio(val)}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <DatePickerInput
            label="Fecha fin"
            placeholder="Hasta"
            valueFormat="YYYY-MM-DD"
            {...contained}
            value={fechaFin}
            onChange={(val) => setFechaFin(val)}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12 }}>
          <Button
            fullWidth
            size="sm"
            variant="light"
            leftSection={<IconSearch size={16} />}
            disabled={!cedula || !fechaInicio || !fechaFin}
            onClick={() => {
              setBuscar(true);
              refetch();
            }}
          >
            Consultar
          </Button>
        </Grid.Col>
      </Grid>

      {!buscar ? (
        <EmptyState
          icon={IconClock}
          title={veTodos ? "Seleccione un servidor y un rango de fechas" : "Seleccione un rango de fechas"}
          description="Las marcaciones se consultan desde el sistema biométrico."
        />
      ) : (
        <DataState
          loading={isLoading}
          error={error}
          empty={marcaciones.length === 0}
          errorTitle="No se pudieron consultar las marcaciones"
          errorHint="No quiere decir que no haya marcaciones: el biométrico no respondió a la consulta."
          onRetry={() => void refetch()}
          skeletonRows={4}
          emptyProps={{ icon: IconClock, title: "Sin marcaciones en el período" }}
        >
          <SgthTable
            records={marcaciones}
            columns={columns}
            fetching={false}
            minHeight={200}
          />
        </DataState>
      )}
    </Stack>
  );
}



