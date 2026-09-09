"use client";

import { useState } from "react";
import { Stack } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconBeach } from "@tabler/icons-react";
import { DataState, PAGINACION_ES, SgthTable } from "@/components/ui";
import { VacacionModal } from "./VacacionModal";
import {
  FILTROS_INICIALES,
  VacacionesFiltros,
  type FiltrosVacacion,
} from "./VacacionesFiltros";
import { getVacacionesColumns } from "./vacaciones.columns";
import { useVacaciones } from "../hooks/useVacaciones";
import { useVacacionMutations } from "../hooks/useVacacionMutations";
import { useExportarVacacion } from "../hooks/useExportarVacacion";
import type { Vacacion } from "@/types/api";

// El folio escrito entraba directo en la clave de consulta: cada tecla pediría
// un listado paginado entero, y solo importa el último.
const RETARDO_BUSQUEDA_MS = 300;

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15;

export function VacacionesTab() {
  const [opened, { open, close }] = useDisclosure(false);

  const [page, setPage] = useState(1);
  const [filtros, setFiltros] = useState<FiltrosVacacion>(FILTROS_INICIALES);
  const { exportar, exportandoId } = useExportarVacacion();

  const [folioConRetardo] = useDebouncedValue(
    filtros.folio,
    RETARDO_BUSQUEDA_MS,
  );

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarFiltros = (cambio: Partial<FiltrosVacacion>) => {
    setFiltros((actuales) => ({ ...actuales, ...cambio }));
    setPage(1);
  };

  const { data, isLoading, error } = useVacaciones({
    page,
    per_page: POR_PAGINA,
    estado: filtros.estado === "todos" ? undefined : filtros.estado,
    motivo: filtros.motivo ?? undefined,
    unidad_administrativa_id: filtros.unidadId
      ? Number(filtros.unidadId)
      : undefined,
    fecha_desde: filtros.fechaDesde ?? undefined,
    fecha_hasta: filtros.fechaHasta ?? undefined,
    folio: folioConRetardo || undefined,
  });

  const lista = (data?.data ?? []) as Vacacion[];

  const { actualizar } = useVacacionMutations();

  const columns = getVacacionesColumns({
    exportandoId,
    onExportar: (id) => exportar(id),
    onAprobar: (id) => actualizar.mutate({ id, data: { estado: "aprobada" } }),
    onRechazar: (id) => actualizar.mutate({ id, data: { estado: "rechazada" } }),
  });

  return (
    <Stack gap="md">
      <VacacionesFiltros
        filtros={filtros}
        onCambiar={cambiarFiltros}
        onNueva={open}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconBeach,
          title: "Sin solicitudes de vacaciones",
          description: folioConRetardo
            ? `No se encontraron solicitudes con folio «${folioConRetardo}»`
            : "No hay solicitudes que coincidan con los filtros.",
        }}
      >
        <SgthTable
          // Solo `paginationText`: `recordsPerPageLabel` pertenece a la
          // variante de mantine-datatable que además exige
          // `recordsPerPageOptions` y `onRecordsPerPageChange`, y sin ellas no
          // compila. Es la misma razón por la que `PermisosTab` no usa
          // `PAGINACION_ES` entero.
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

      <VacacionModal opened={opened} onClose={close} />
    </Stack>
  );
}
