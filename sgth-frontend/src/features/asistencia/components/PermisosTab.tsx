"use client";

import React, { useState } from "react";
import { ActionIcon, Button, Chip, Group, Stack, TextInput } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconCheck,
  IconClipboardList,
  IconCubePlus,
  IconSearch,
  IconX,
} from "@tabler/icons-react";
import { notifications } from "@mantine/notifications";
import { DataState, SgthTable, Toolbar } from "@/components/ui";
import { SEMANTIC_COLOR } from "@/config/design.tokens";
import { useContainedInput } from "@/hooks/useContainedInput";
import { PermisoModal } from "./PermisoModal";
import { MotivoPermisoModal } from "./MotivoPermisoModal";
import { getPermisosColumns } from "./permisos.columns";
import { ESTADO_LABELS, FILTROS_ESTADO, TONO_ESTADO } from "./permisos.constants";
import { usePermisos } from "../hooks/usePermisos";
import { usePermisoMutations } from "../hooks/usePermisoMutations";
import { asistenciaService } from "../services/asistenciaService";
import type { PermisoServidor } from "@/types/api";

/** Qué acción abrió el modal de motivo. */
type AccionConMotivo = "rechazar" | "revertir";

export function PermisosTab() {
  const [opened, { open, close }] = useDisclosure(false);
  const contained = useContainedInput("sm");

  const [filtroEstado, setFiltroEstado] = useState<string>("pendiente");
  const [busquedaFolio, setBusquedaFolio] = useState<string>("");
  const [folioQuery, setFolioQuery] = useState<string>("");
  const [exportandoId, setExportandoId] = useState<number | null>(null);
  const [conMotivo, setConMotivo] = useState<
    { accion: AccionConMotivo; permiso: PermisoServidor } | null
  >(null);

  const { data, isLoading, error } = usePermisos({
    estado: filtroEstado === "todos" ? undefined : filtroEstado,
    folio: folioQuery || undefined,
    per_page: 50,
  });
  const lista = (data?.data ?? []) as PermisoServidor[];

  const {
    confirmar: confirmarPermiso,
    anular,
    validarTs,
    rechazar,
    revertirConfirmacion,
  } = usePermisoMutations();

  const handleExportar = async (id: number) => {
    setExportandoId(id);
    notifications.show({
      id: `export-permiso-${id}`,
      title: "Exportando permiso...",
      message: "Generando el documento PDF, espere.",
      color: "blue",
      loading: true,
      autoClose: false,
      withCloseButton: false,
    });
    try {
      const blob = await asistenciaService.permisos.exportar(id);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `permiso_${id}.pdf`;
      link.click();
      URL.revokeObjectURL(url);
      notifications.update({
        id: `export-permiso-${id}`,
        title: "PDF descargado",
        message: "El permiso fue exportado correctamente.",
        color: "emerald",
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
        icon: React.createElement(IconCheck, { size: 16 }),
      });
    } catch {
      notifications.update({
        id: `export-permiso-${id}`,
        title: "Error",
        message: "No se pudo exportar el permiso.",
        color: "red",
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
      });
    } finally {
      setExportandoId(null);
    }
  };

  const enviarMotivo = (motivo: string) => {
    if (!conMotivo) return;

    const { accion, permiso } = conMotivo;
    const mutacion = accion === "rechazar" ? rechazar : revertirConfirmacion;

    mutacion.mutate({ id: permiso.id, motivo }, { onSuccess: () => setConMotivo(null) });
  };

  const columns = getPermisosColumns({
    exportandoId,
    onExportar: handleExportar,
    onConfirmar: (folio) => confirmarPermiso.mutate(folio),
    onValidarTs: (id) => validarTs.mutate(id),
    onAnular: (id) => anular.mutate(id),
    onRechazar: (permiso) => setConMotivo({ accion: "rechazar", permiso }),
    onRevertir: (permiso) => setConMotivo({ accion: "revertir", permiso }),
  });

  const esRechazo = conMotivo?.accion === "rechazar";

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
              Nuevo permiso
            </Button>
          </>
        }
      >
        <TextInput
          label="Folio"
          placeholder="Ej: PER-2026-00001"
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
                variant="subtle"
                color="gray"
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
          {FILTROS_ESTADO.map((valor) => (
            <Chip
              key={valor}
              // El chip toma el color del mismo tono semántico que la
              // etiqueta de estado, para que filtro y resultado coincidan.
              color={SEMANTIC_COLOR[TONO_ESTADO[valor] ?? "neutral"]}
              checked={filtroEstado === valor}
              onChange={() => setFiltroEstado(valor)}
            >
              {valor === "todos" ? "Todos" : ESTADO_LABELS[valor]}
            </Chip>
          ))}
        </Group>
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconClipboardList,
          title: "Sin permisos registrados",
          description: folioQuery
            ? `No se encontraron permisos con folio «${folioQuery}»`
            : "No hay permisos en este estado.",
        }}
      >
        <SgthTable records={lista} columns={columns} minHeight={200} />
      </DataState>

      <PermisoModal opened={opened} onClose={close} />

      <MotivoPermisoModal
        opened={conMotivo !== null}
        onClose={() => setConMotivo(null)}
        title={esRechazo ? "Rechazar documento" : "Revertir confirmación"}
        confirmLabel={esRechazo ? "Rechazar" : "Revertir"}
        cargando={esRechazo ? rechazar.isPending : revertirConfirmacion.isPending}
        onConfirm={enviarMotivo}
        descripcion={
          esRechazo ? (
            <>
              El permiso <b>{conMotivo?.permiso.folio}</b> quedará rechazado y no
              amparará la ausencia.
            </>
          ) : (
            <>
              El permiso <b>{conMotivo?.permiso.folio}</b> volverá a pendiente y
              se devolverá al servidor el saldo de vacaciones descontado.
            </>
          )
        }
      />
    </Stack>
  );
}
