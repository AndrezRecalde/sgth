"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import {
  Stack,
  Group,
  Button,
  Text,
  Badge,
  Tabs,
  Chip,
  TextInput,
  ActionIcon,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconPlane,
  IconPlus,
  IconCheck,
  IconCurrencyDollar,
  IconSearch,
  IconX,
} from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { SgthTable } from "@/components/ui/SgthTable";
import { EmptyState } from "@/components/ui/EmptyState";
import { TableActions } from "@/components/ui/TableActions";
import { useViaticos } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { ViaticoModal } from "./ViaticoModal";
import { VuelosTab } from "./VuelosTab";
import type { Viatico, EstadoViatico, ViaticoConRelaciones } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";

import { ESTADO_COLORS, ESTADO_LABELS } from "../constants/viatico.constants";

export function ViaticoView() {
  const router = useRouter();
  const [modalAbierto, { open, close }] = useDisclosure(false);

  const [filtroEstado, setFiltroEstado] = useState<string>("solicitado");
  const [page, setPage] = useState(1);
  const [busquedaCodigo, setBusquedaCodigo] = useState("");
  const [codigoQuery, setCodigoQuery] = useState("");

  const filtros = {
    estado:
      filtroEstado === "todos" ? undefined : (filtroEstado as EstadoViatico),
    per_page: 15,
    page,
    search: codigoQuery || undefined,
  };

  const { data, isLoading } = useViaticos(filtros);
  const lista = (data?.data ?? []) as ViaticoConRelaciones[];
  const { aprobar } = useViaticoMutations();

  const handleVer = (v: ViaticoConRelaciones) => {
    router.push(`/viaticos/${v.codigo_viatico ?? v.id}`);
  };

  const handleCreado = (v: Viatico) => {
    router.push(`/viaticos/${v.codigo_viatico ?? v.id}`);
  };

  const columns: DataTableColumn<ViaticoConRelaciones>[] = [
    {
      accessor: "codigo_viatico",
      title: "Código",
      width: 150,
      render: ({ codigo_viatico }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {codigo_viatico ?? "—"}
        </Text>
      ),
    },
    {
      accessor: "servidor",
      title: "Servidor",
      render: (v) => {
        const s = v.servidor;
        if (!s)
          return (
            <Text size="sm" c="dimmed">
              —
            </Text>
          );
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(" ")}
          </Text>
        );
      },
    },
    {
      accessor: "zona",
      title: "Zona",
      width: 130,
      render: ({ zona }) => {
        const labels: Record<string, string> = {
          dentro_provincia: "Dentro prov.",
          fuera_provincia: "Fuera prov.",
          exterior: "Exterior",
        };
        return <Text size="sm">{labels[zona as string] ?? zona}</Text>;
      },
    },
    {
      accessor: "datetime_salida",
      title: "Período",
      width: 160,
      render: ({ datetime_salida, datetime_llegada, total_dias }) => {
        if (!datetime_salida) {
          return (
            <Badge color="orange" variant="dot" size="sm">
              Sin itinerario
            </Badge>
          );
        }
        const fmt = (f: string) =>
          new Date(f.replace(/-/g, "/")).toLocaleDateString("es-EC", {
            day: "2-digit",
            month: "2-digit",
            year: "2-digit",
          });
        return (
          <Stack gap={0}>
            <Text size="xs" ff="monospace">
              {fmt(datetime_salida as string)} –{" "}
              {fmt(datetime_llegada as string)}
            </Text>
            <Text size="xs" c="dimmed">
              {Number(total_dias ?? 0).toFixed(1)} días
            </Text>
          </Stack>
        );
      },
    },
    {
      accessor: "monto_calculado",
      title: "Monto",
      width: 100,
      render: ({ monto_calculado }) => (
        <Text size="sm" ff="monospace" ta="right">
          ${Number(monto_calculado ?? 0).toFixed(2)}
        </Text>
      ),
    },
    {
      accessor: "estado",
      title: "Estado",
      width: 140,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as EstadoViatico] ?? "gray"}
          variant="light"
          size="sm"
        >
          {ESTADO_LABELS[estado as EstadoViatico] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 50,
      render: (v) => (
        <TableActions
          actions={[
            {
              label: "Ver detalle",
              icon: <IconPlane size={14} />,
              color: "blue",
              onClick: () => handleVer(v),
            },
            {
              label: "Aprobar",
              icon: <IconCheck size={14} />,
              color: "emerald",
              onClick: () => aprobar.mutate({ id: v.id }),
              hidden: (v.estado as string) !== "solicitado",
            },
            {
              label: "Liquidar",
              icon: <IconCurrencyDollar size={14} />,
              color: "orange",
              onClick: () => handleVer(v),
              hidden: (v.estado as string) !== "pendiente_liquidacion",
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Stack gap="md">
      <PageHeader
        title="Viáticos"
        subtitle="Gestión de comisiones de servicio y viáticos"
        icon={<IconPlane size={24} />}
      />

      <Tabs defaultValue="viaticos">
        <Tabs.List>
          <Tabs.Tab value="viaticos">Solicitudes</Tabs.Tab>
          <Tabs.Tab value="vuelos">Autorizaciones de vuelo</Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="viaticos" pt="md">
          <Stack gap="sm">
            <Group gap="xs">
              <TextInput
                placeholder="Buscar por código..."
                leftSection={<IconSearch size={14} />}
                value={busquedaCodigo}
                onChange={(e) => setBusquedaCodigo(e.currentTarget.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    setCodigoQuery(busquedaCodigo.trim());
                    setPage(1);
                  }
                }}
                style={{ width: 260 }}
                rightSection={
                  busquedaCodigo ? (
                    <ActionIcon
                      size="sm"
                      variant="subtle"
                      color="gray"
                      onClick={() => {
                        setBusquedaCodigo("");
                        setCodigoQuery("");
                        setPage(1);
                      }}
                    >
                      <IconX size={12} />
                    </ActionIcon>
                  ) : null
                }
              />
              <Button
                variant="light"
                color="blue"
                leftSection={<IconSearch size={14} />}
                onClick={() => {
                  setCodigoQuery(busquedaCodigo.trim());
                  setPage(1);
                }}
              >
                Buscar
              </Button>
            </Group>

            {/* Chips de estado */}
            <Group gap="xs">
              <Text size="sm" fw={500} c="dimmed">
                Estado:
              </Text>
              {[
                { value: "todos", label: "Todos", color: "gray" },
                { value: "solicitado", label: "Solicitados", color: "orange" },
                { value: "aprobado", label: "Aprobados", color: "blue" },
                { value: "con_anticipo", label: "Con anticipo", color: "cyan" },
                {
                  value: "pendiente_liquidacion",
                  label: "Pend. liquid.",
                  color: "yellow",
                },
                { value: "liquidado", label: "Liquidados", color: "emerald" },
              ].map((op) => (
                <Chip
                  key={op.value}
                  size="sm"
                  color={op.color}
                  checked={filtroEstado === op.value}
                  onChange={() => {
                    setFiltroEstado(op.value);
                    setPage(1);
                  }}
                >
                  {op.label}
                </Chip>
              ))}
            </Group>

            {/* Botón nueva solicitud */}
            <Group justify="flex-end">
              <Button
                size="xs"
                color="emerald"
                variant="light"
                leftSection={<IconPlus size={14} />}
                onClick={open}
              >
                Nueva solicitud
              </Button>
            </Group>

            {lista.length === 0 && !isLoading ? (
              <EmptyState
                icon={IconPlane}
                title="Sin solicitudes de viáticos"
                description="No hay viáticos en este estado."
              />
            ) : (
              <SgthTable
                records={lista}
                columns={columns}
                fetching={isLoading}
                minHeight={200}
                totalRecords={data?.total ?? lista.length}
                recordsPerPage={15}
                page={page}
                onPageChange={setPage}
              />
            )}
          </Stack>
        </Tabs.Panel>

        <Tabs.Panel value="vuelos" pt="md">
          <VuelosTab />
        </Tabs.Panel>
      </Tabs>

      <ViaticoModal
        opened={modalAbierto}
        onClose={close}
        onCreated={handleCreado}
      />
    </Stack>
  );
}
