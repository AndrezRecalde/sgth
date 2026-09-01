"use client";

import React from "react";
import { useState } from "react";
import {
  Stack,
  Group,
  Button,
  Text,
  TextInput,
  Chip,
  ActionIcon,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconBeach,
  IconCheck,
  IconX,
  IconPrinter,
  IconSearch,
  IconCubePlus,
} from "@tabler/icons-react";
import { notifications } from "@mantine/notifications";
import {
  DataState,
  SgthTable,
  StatusBadge,
  TableActions,
  Toolbar,
  confirmar,
} from "@/components/ui";
import { SEMANTIC_COLOR, type SemanticTone } from "@/config/design.tokens";
import { useContainedInput } from "@/hooks/useContainedInput";
import { VacacionModal } from "./VacacionModal";
import { useVacaciones } from "../hooks/useVacaciones";
import { useVacacionMutations } from "../hooks/useVacacionMutations";
import { asistenciaService } from "../services/asistenciaService";
import type { Vacacion, EstadoVacacion, MotivoVacacion } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";

const TONO_ESTADO: Record<EstadoVacacion, SemanticTone> = {
  pendiente: "warning",
  aprobada: "success",
  rechazada: "danger",
  gozada: "neutral",
};
const ESTADO_LABELS: Record<EstadoVacacion, string> = {
  pendiente: "Pendiente",
  aprobada: "Aprobada",
  rechazada: "Rechazada",
  gozada: "Gozada",
};
const MOTIVO_LABELS: Record<MotivoVacacion, string> = {
  vacaciones_anuales: "Vacaciones Anuales",
  permiso_cargo_vacaciones: "Cargo a Vacaciones",
  licencia_sin_goce: "Licencia sin Goce",
  matrimonio: "Matrimonio",
  capacitacion: "Capacitación",
  enfermedad: "Enfermedad",
  maternidad: "Maternidad",
  paternidad: "Paternidad",
  estudios_sin_remuneracion: "Estudios sin Rem.",
  calamidad_domestica: "Calamidad",
  licencia_con_goce: "Licencia con Goce",
};

export function VacacionesTab() {
  const [opened, { open, close }] = useDisclosure(false);
  const contained = useContainedInput("sm");

  // ── Filtros ──────────────────────────────────────
  const [filtroEstado, setFiltroEstado] = useState<string>("pendiente");
  const [busquedaFolio, setBusquedaFolio] = useState<string>("");
  const [folioQuery, setFolioQuery] = useState<string>("");

  const filtros = {
    estado: filtroEstado === "todos" ? undefined : filtroEstado,
    folio: folioQuery || undefined,
    per_page: 50,
  };

  const { data, isLoading, error } = useVacaciones(filtros);
  const lista = (
    Array.isArray(data)
      ? data
      : ((data as { data?: Vacacion[] } | null)?.data ?? [])
  ) as Vacacion[];

  const { actualizar } = useVacacionMutations();
  const [exportandoId, setExportandoId] = useState<number | null>(null);

  const handleExportar = async (id: number) => {
    setExportandoId(id);
    notifications.show({
      id: `export-vacacion-${id}`,
      title: "Exportando solicitud...",
      message: "Generando el documento PDF, espere.",
      color: "blue",
      loading: true,
      autoClose: false,
      withCloseButton: false,
    });
    try {
      const blob = await asistenciaService.vacaciones.exportar(id);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `vacacion_${id}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
      notifications.update({
        id: `export-vacacion-${id}`,
        title: "PDF descargado",
        message: "La solicitud fue exportada correctamente.",
        color: "emerald",
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
        icon: React.createElement(IconCheck, { size: 16 }),
      });
    } catch {
      notifications.update({
        id: `export-vacacion-${id}`,
        title: "Error",
        message: "No se pudo exportar la solicitud.",
        color: "red",
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
      });
    } finally {
      setExportandoId(null);
    }
  };

  const columns: DataTableColumn<Vacacion>[] = [
    {
      accessor: "folio",
      title: "Folio",
      width: 145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {folio ?? "—"}
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
      accessor: "motivo",
      title: "Motivo",
      render: ({ motivo }) => (
        <Text size="sm">{MOTIVO_LABELS[motivo] ?? motivo}</Text>
      ),
    },
    {
      accessor: "fecha_inicio",
      title: "Desde",
      width: 110,
      render: ({ fecha_inicio }) => (
        <Text size="sm">
          {fecha_inicio
            ? new Date(fecha_inicio).toLocaleDateString("es-EC", {
                timeZone: "UTC",
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
              })
            : "—"}
        </Text>
      ),
    },
    {
      accessor: "fecha_fin",
      title: "Hasta",
      width: 110,
      render: ({ fecha_fin }) => (
        <Text size="sm">
          {fecha_fin
            ? new Date(fecha_fin).toLocaleDateString("es-EC", {
                timeZone: "UTC",
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
              })
            : "—"}
        </Text>
      ),
    },
    {
      accessor: "dias_solicitados",
      title: "Días",
      width: 70,
      render: ({ dias_solicitados }) => (
        <Text size="sm" ta="center">
          {dias_solicitados}
        </Text>
      ),
    },
    {
      accessor: "estado",
      title: "Estado",
      width: 110,
      render: ({ estado }) => (
        <StatusBadge tone={TONO_ESTADO[estado] ?? "neutral"}>
          {ESTADO_LABELS[estado] ?? estado}
        </StatusBadge>
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
              label:
                exportandoId === v.id ? "Exportando..." : "Imprimir solicitud",
              icon: <IconPrinter size={14} />,
              color: "blue",
              onClick: () => handleExportar(v.id),
            },
            {
              label: "Aprobar",
              icon: <IconCheck size={14} />,
              color: "emerald",
              onClick: () =>
                actualizar.mutate({
                  id: v.id,
                  data: { estado: "aprobada" },
                }),
              hidden: v.estado !== "pendiente",
            },
            {
              label: "Rechazar",
              icon: <IconX size={14} />,
              color: "red",
              onClick: () =>
                confirmar({
                  title: "Rechazar solicitud",
                  message:
                    "Se rechazará esta solicitud de vacaciones y el servidor será notificado.",
                  destructiva: true,
                  confirmLabel: "Rechazar",
                  onConfirm: () =>
                    actualizar.mutate({
                      id: v.id,
                      data: { estado: "rechazada" },
                    }),
                }),
              hidden: v.estado !== "pendiente",
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <>
            <Button
              variant="light"
              leftSection={<IconSearch size={14} />}
              onClick={() => setFolioQuery(busquedaFolio)}
            >
              Buscar
            </Button>
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconCubePlus size={16} />}
              onClick={open}
            >
              Nueva solicitud
            </Button>
          </>
        }
      >
        <TextInput
          label="Folio"
          placeholder="Ej: VAC-2026-00001"
          {...contained}
          value={busquedaFolio}
          onChange={(e) => setBusquedaFolio(e.currentTarget.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") setFolioQuery(busquedaFolio);
          }}
          style={{ minWidth: 280 }}
          rightSection={
            busquedaFolio && (
              <ActionIcon
                size="sm"
                color="gray"
                variant="subtle"
                onClick={() => {
                  setBusquedaFolio("");
                  setFolioQuery("");
                }}
              >
                <IconX size={12} />
              </ActionIcon>
            )
          }
        />
        <Group gap="xs">
          {[
            { value: "todos", label: "Todos" },
            { value: "pendiente", label: "Pendiente" },
            { value: "aprobada", label: "Aprobada" },
            { value: "rechazada", label: "Rechazada" },
            { value: "gozada", label: "Gozada" },
          ].map((op) => (
            <Chip
              key={op.value}
              // Mismo tono semántico que la etiqueta de estado de la fila.
              color={SEMANTIC_COLOR[
                TONO_ESTADO[op.value as EstadoVacacion] ?? "neutral"
              ]}
              checked={filtroEstado === op.value}
              onChange={() => setFiltroEstado(op.value)}
            >
              {op.label}
            </Chip>
          ))}
        </Group>
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconBeach,
          title: "Sin solicitudes de vacaciones",
          description: folioQuery
            ? `No se encontraron solicitudes con folio «${folioQuery}»`
            : "No hay solicitudes en este estado.",
        }}
      >
        <SgthTable
          records={lista}
          columns={columns}
          minHeight={200}
        />
      </DataState>

      <VacacionModal opened={opened} onClose={close} />
    </Stack>
  );
}
