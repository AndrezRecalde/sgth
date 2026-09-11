"use client";

import { useState } from "react";
import { useSearchParams } from "next/navigation";
import { Stack } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconBeach } from "@tabler/icons-react";
import { DataState, PAGINACION_ES, SgthTable } from "@/components/ui";
import { VacacionModal } from "./VacacionModal";
import { MotivoPermisoModal } from "./MotivoPermisoModal";
import {
  FILTROS_INICIALES,
  VacacionesFiltros,
  type FiltrosVacacion,
} from "./VacacionesFiltros";
import { getVacacionesColumns } from "./vacaciones.columns";
import { MOTIVOS_QUE_DESCUENTAN } from "./vacaciones.constants";
import { useVacaciones } from "../hooks/useVacaciones";
import { useVacacionMutations } from "../hooks/useVacacionMutations";
import { useExportarVacacion } from "../hooks/useExportarVacacion";
import { useAuth } from "@/hooks/useAuth";
import type { Vacacion } from "@/types/api";

// El folio escrito entraba directo en la clave de consulta: cada tecla pediría
// un listado paginado entero, y solo importa el último.
const RETARDO_BUSQUEDA_MS = 300;

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15;

/** Qué pasará exactamente al anular, con el folio nombrado. */
function consecuenciaDeAnular(v: Vacacion) {
  const folio = v.folio ?? `#${v.id}`;

  if (v.estado === "aprobada" && MOTIVOS_QUE_DESCUENTAN.includes(v.motivo)) {
    return (
      <>
        Se anulará la solicitud aprobada <b>{folio}</b> y sus{" "}
        <b>{v.dias_solicitados} días</b> volverán al saldo del servidor, a los
        mismos períodos de donde salieron.
      </>
    );
  }

  return (
    <>
      Se anulará la solicitud <b>{folio}</b>. No había descontado días, así que
      el saldo no cambia.
    </>
  );
}

export function VacacionesTab() {
  const [opened, { open, close }] = useDisclosure(false);
  const [anulando, setAnulando] = useState<Vacacion | null>(null);

  // El QR impreso en la solicitud trae `?folio=`: quien lo escanea tiene que
  // encontrar esa solicitud, esté en el estado que esté. Por eso el filtro de
  // estado pasa a «todos», no se queda en «pendiente».
  const folioDelQr = useSearchParams().get("folio");

  const [page, setPage] = useState(1);
  const [filtros, setFiltros] = useState<FiltrosVacacion>(() =>
    folioDelQr
      ? { ...FILTROS_INICIALES, folio: folioDelQr, estado: "todos" }
      : FILTROS_INICIALES,
  );
  const { exportar, exportandoId } = useExportarVacacion();

  // Las acciones siguen la misma matriz que la API: ofrecerlas a quien no
  // tiene el permiso solo serviría para que recibiera un 403.
  const { hasPermiso } = useAuth();
  const puedeRegistrar = hasPermiso("gestionar-vacaciones");
  const puedeResolver = hasPermiso("aprobar-vacaciones");

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

  const { actualizar, anular } = useVacacionMutations();

  const columns = getVacacionesColumns({
    exportandoId,
    puedeResolver,
    onExportar: (id) => exportar(id),
    onAprobar: (id) => actualizar.mutate({ id, data: { estado: "aprobada" } }),
    onRechazar: (id) => actualizar.mutate({ id, data: { estado: "rechazada" } }),
    onAnular: setAnulando,
  });

  return (
    <Stack gap="md">
      <VacacionesFiltros
        filtros={filtros}
        onCambiar={cambiarFiltros}
        onNueva={puedeRegistrar ? open : undefined}
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

      {puedeRegistrar && <VacacionModal opened={opened} onClose={close} />}

      {/* El modal de motivo es el de permisos: sus props ya son genéricas. */}
      <MotivoPermisoModal
        opened={anulando !== null}
        onClose={() => setAnulando(null)}
        title="Anular solicitud de vacaciones"
        descripcion={anulando && consecuenciaDeAnular(anulando)}
        confirmLabel="Anular"
        cargando={anular.isPending}
        onConfirm={(motivo) => {
          if (!anulando) return;
          anular.mutate(
            { id: anulando.id, motivo },
            { onSuccess: () => setAnulando(null) },
          );
        }}
      />
    </Stack>
  );
}
