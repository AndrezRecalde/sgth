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
} from "../hooks/useConsolidadoPermisos";

/**
 * El consolidado de permisos: cuántos tiene cada servidor en un rango.
 *
 * Tenía 332 líneas con la consulta, la exportación, los filtros y una tabla
 * HTML escrita a mano. Ahora la consulta y la exportación viven en
 * `useConsolidadoPermisos`, los filtros en `ConsolidadoFiltros` y las columnas
 * en `consolidado.columns.tsx`; la tabla es `SgthTable`, como en el resto del
 * sistema, y `DataState` agrega el estado de error, que faltaba.
 */
export function ConsolidadoPermisosTab() {
  const [filtros, setFiltros] = useState<FiltrosConsolidado>(
    FILTROS_INICIALES_CONSOLIDADO,
  );
  const [buscar, setBuscar] = useState(false);

  const params = {
    fecha_inicio: filtros.fechaInicio ?? "",
    fecha_fin: filtros.fechaFin ?? "",
    tipo: filtros.tipo,
  };

  const { data, isLoading, error, refetch } = useConsolidadoPermisos(params, buscar);
  const { exportar, exportando } = useExportarConsolidado();

  const consolidado = data?.consolidado ?? [];
  const puedeConsultar = !!filtros.fechaInicio && !!filtros.fechaFin;

  return (
    <Stack gap="md">
      <ConsolidadoFiltros
        filtros={filtros}
        onCambiar={(cambio) => setFiltros((actuales) => ({ ...actuales, ...cambio }))}
        puedeConsultar={puedeConsultar}
        consultando={isLoading && buscar}
        onConsultar={() => {
          setBuscar(true);
          refetch();
        }}
      />

      {buscar && consolidado.length > 0 && (
        <Group justify="flex-end" gap="sm">
          <Button
            variant="light"
            color="blue"
            size="xs"
            leftSection={<IconFileTypeCsv size={14} />}
            loading={exportando === "excel"}
            onClick={() => exportar("excel", params)}
          >
            Exportar Excel (CSV)
          </Button>
          <Button
            variant="light"
            color="red"
            size="xs"
            leftSection={<IconFileDownload size={14} />}
            loading={exportando === "pdf"}
            onClick={() => exportar("pdf", params)}
          >
            Exportar PDF
          </Button>
        </Group>
      )}

      {!buscar ? (
        <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
          <Text size="sm">
            Selecciona un rango de fechas y el tipo de permiso, luego presiona
            Consultar.
          </Text>
        </Alert>
      ) : (
        <DataState
          loading={isLoading}
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
