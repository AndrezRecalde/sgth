"use client";

import { useState } from "react";
import { Stack } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconClipboardList } from "@tabler/icons-react";
import { DataState, PAGINACION_ES, SgthTable } from "@/components/ui";
import { PermisoModal } from "./PermisoModal";
import { MotivoPermisoModal } from "./MotivoPermisoModal";
import {
  FILTROS_INICIALES,
  PermisosFiltros,
  type FiltrosPermiso,
} from "./PermisosFiltros";
import { getPermisosColumns } from "./permisos.columns";
import { usePermisos } from "../hooks/usePermisos";
import { usePermisoMutations } from "../hooks/usePermisoMutations";
import { useExportarPermiso } from "../hooks/useExportarPermiso";
import type { PermisoServidor } from "@/types/api";

// El folio escrito entraba directo en la clave de consulta: cada tecla pediría
// un listado paginado entero, y solo importa el último.
const RETARDO_BUSQUEDA_MS = 300;

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15;

/** Qué acción abrió el modal de motivo. */
type AccionConMotivo = "rechazar" | "revertir";

export function PermisosTab() {
  const [opened, { open, close }] = useDisclosure(false);

  const [page, setPage] = useState(1);
  const [filtros, setFiltros] = useState<FiltrosPermiso>(FILTROS_INICIALES);
  const { exportar, exportandoId } = useExportarPermiso();
  const [conMotivo, setConMotivo] = useState<
    { accion: AccionConMotivo; permiso: PermisoServidor } | null
  >(null);

  const [folioConRetardo] = useDebouncedValue(
    filtros.folio,
    RETARDO_BUSQUEDA_MS,
  );

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarFiltros = (cambio: Partial<FiltrosPermiso>) => {
    setFiltros((actuales) => ({ ...actuales, ...cambio }));
    setPage(1);
  };

  const { data, isLoading, error } = usePermisos({
    page,
    per_page: POR_PAGINA,
    estado: filtros.estado === "todos" ? undefined : filtros.estado,
    tipo: filtros.tipo ?? undefined,
    unidad_administrativa_id: filtros.unidadId
      ? Number(filtros.unidadId)
      : undefined,
    fecha_desde: filtros.fechaDesde ?? undefined,
    fecha_hasta: filtros.fechaHasta ?? undefined,
    folio: folioConRetardo || undefined,
  });

  const lista = (data?.data ?? []) as PermisoServidor[];

  const {
    confirmar: confirmarPermiso,
    anular,
    validarTs,
    rechazar,
    revertirConfirmacion,
  } = usePermisoMutations();

  const enviarMotivo = (motivo: string) => {
    if (!conMotivo) return;

    const { accion, permiso } = conMotivo;
    const mutacion = accion === "rechazar" ? rechazar : revertirConfirmacion;

    mutacion.mutate(
      { id: permiso.id, motivo },
      { onSuccess: () => setConMotivo(null) },
    );
  };

  const columns = getPermisosColumns({
    exportandoId,
    onExportar: (id) => exportar(id),
    onConfirmar: (folio) => confirmarPermiso.mutate(folio),
    onValidarTs: (id) => validarTs.mutate(id),
    onAnular: (id) => anular.mutate(id),
    onRechazar: (permiso) => setConMotivo({ accion: "rechazar", permiso }),
    onRevertir: (permiso) => setConMotivo({ accion: "revertir", permiso }),
  });

  const esRechazo = conMotivo?.accion === "rechazar";

  return (
    <Stack gap="md">
      <PermisosFiltros
        filtros={filtros}
        onCambiar={cambiarFiltros}
        onNuevo={open}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconClipboardList,
          title: "Sin permisos registrados",
          description: folioConRetardo
            ? `No se encontraron permisos con folio «${folioConRetardo}»`
            : "No hay permisos que coincidan con los filtros.",
        }}
      >
        <SgthTable
          // Solo `paginationText`, no el objeto entero: `recordsPerPageLabel`
          // pertenece a la variante de mantine-datatable que además exige
          // `recordsPerPageOptions` y `onRecordsPerPageChange`, y sin ellas no
          // compila. De ahí que ninguna pantalla use `PAGINACION_ES` completo.
          paginationText={PAGINACION_ES.paginationText}
          records={lista}
          columns={columns}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
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
