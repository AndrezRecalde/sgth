"use client";

import { useState } from "react";
import { Alert, Button, Group, Stack, Text } from "@mantine/core";
import {
  IconClipboardList,
  IconFileDownload,
  IconFileTypeCsv,
  IconInfoCircle,
} from "@tabler/icons-react";
import { DataState, SgthTable } from "@/components/ui";
import {
  ConsolidadoFiltros,
  FILTROS_INICIALES_CONSOLIDADO,
  type FiltrosConsolidado,
} from "./ConsolidadoFiltros";
import { getConsolidadoColumns } from "./consolidado.columns";
import {
  useConsolidadoPermisos,
  useExportarConsolidado,
  type ParamsConsolidado,
} from "../hooks/useConsolidadoPermisos";

/**
 * El consolidado de permisos: cuántos tiene cada servidor en un rango.
 *
 * Tenía 332 líneas con la consulta, la exportación, los filtros y una tabla
 * HTML escrita a mano. Ahora la consulta y la exportación viven en
 * `useConsolidadoPermisos`, los filtros en `ConsolidadoFiltros` y las columnas
 * en `consolidado.columns.tsx`; la tabla es `SgthTable`, como en el resto del
 * sistema, y `DataState` agrega el estado de error, que faltaba.
 *
 * Hay DOS estados de filtro y no uno: los que se están eligiendo (`filtros`) y
 * los que se consultaron (`consultados`). Con uno solo, la consulta se lanzaba
 * sola en cuanto cambiaba cualquier campo, y además el botón de exportar podía
 * pedir un período distinto del que estaba en pantalla.
 */
export function ConsolidadoPermisosTab() {
  const [filtros, setFiltros] = useState<FiltrosConsolidado>(
    FILTROS_INICIALES_CONSOLIDADO,
  );
  const [consultados, setConsultados] = useState<ParamsConsolidado | null>(null);

  const { data, isFetching, error, refetch } = useConsolidadoPermisos(consultados);
  const { exportar, exportando } = useExportarConsolidado();

  const consolidado = data?.consolidado ?? [];
  const puedeConsultar = !!filtros.fechaInicio && !!filtros.fechaFin;

  const consultar = () => {
    if (!puedeConsultar) return;

    const pedidos: ParamsConsolidado = {
      fecha_inicio: filtros.fechaInicio ?? "",
      fecha_fin: filtros.fechaFin ?? "",
      tipo: filtros.tipo,
    };

    const mismosFiltros =
      consultados !== null &&
      consultados.fecha_inicio === pedidos.fecha_inicio &&
      consultados.fecha_fin === pedidos.fecha_fin &&
      consultados.tipo === pedidos.tipo;

    setConsultados(pedidos);

    // Si los filtros cambiaron, basta con guardarlos: la clave de la consulta
    // cambia y TanStack la lanza. `refetch()` solo hace falta cuando son los
    // mismos, porque entonces serviría lo cacheado y pulsar «Consultar» tiene
    // que volver a preguntar igualmente.
    //
    // Llamarlo siempre disparaba DOS peticiones, y la primera con la query
    // vacía: `refetch()` corre antes de que el `setConsultados` de arriba haya
    // entrado, así que la consulta todavía valía `null`.
    if (mismosFiltros) void refetch();
  };

  return (
    <Stack gap="md">
      <ConsolidadoFiltros
        filtros={filtros}
        onCambiar={(cambio) => setFiltros((actuales) => ({ ...actuales, ...cambio }))}
        puedeConsultar={puedeConsultar}
        consultando={isFetching}
        onConsultar={consultar}
      />

      {/* Se exporta lo consultado, no lo que haya en los filtros: si no,
          cambiar una fecha sin consultar descargaba un período distinto del
          que la pantalla está mostrando. */}
      {consultados && consolidado.length > 0 && (
        <Group justify="flex-end" gap="sm">
          <Button
            variant="light"
            size="xs"
            leftSection={<IconFileTypeCsv size={14} />}
            loading={exportando === "excel"}
            onClick={() => exportar("excel", consultados)}
          >
            Exportar Excel (CSV)
          </Button>
          <Button
            variant="light"
            size="xs"
            leftSection={<IconFileDownload size={14} />}
            loading={exportando === "pdf"}
            onClick={() => exportar("pdf", consultados)}
          >
            Exportar PDF
          </Button>
        </Group>
      )}

      {!consultados ? (
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          <Text size="sm">
            Selecciona un rango de fechas y el tipo de permiso, luego presiona
            Consultar.
          </Text>
        </Alert>
      ) : (
        <DataState
          loading={isFetching}
          error={error}
          empty={!consolidado.length}
          emptyProps={{
            icon: IconClipboardList,
            title: "Sin permisos en el período",
            description:
              "No hay permisos de ese tipo entre las fechas elegidas. Prueba con otro rango o tipo.",
          }}
        >
          <SgthTable
            idAccessor="servidor_id"
            records={consolidado}
            columns={getConsolidadoColumns(data?.totales)}
          />
        </DataState>
      )}
    </Stack>
  );
}
