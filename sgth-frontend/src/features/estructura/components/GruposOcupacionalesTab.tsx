"use client";

import { Badge, Select, Stack, Text } from "@mantine/core";
import { IconLayersIntersect } from "@tabler/icons-react";
import { DataState, SgthTable, StatusBadge, Toolbar } from "@/components/ui";
import { REGIMEN_LABELS, REGIMEN_TONOS } from "@/lib/regimen";
import type { SemanticTone } from "@/config/design.tokens";
import { useGruposOcupacionales } from "../hooks/useGruposOcupacionales";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useState } from "react";
import type { DataTableColumn } from "mantine-datatable";

type GrupoOcupacional = {
  id: number;
  grado_codigo: string;
  grado_numerico: number | null;
  grupo: string;
  denominacion_generica: string | null;
  rmu: number;
  regimen: "losep" | "codigo_trabajo";
  nivel_complejidad: string | null;
  rol_puesto: string | null;
  activo: boolean;
};

const REGIMEN_OPTIONS = [
  { value: "", label: "Todos los regímenes" },
  { value: "losep", label: "LOSEP" },
  { value: "codigo_trabajo", label: "Código del Trabajo" },
];

const TONO_COMPLEJIDAD: Record<string, SemanticTone> = {
  bajo: "neutral",
  medio: "warning",
  alto: "success",
};

export function GruposOcupacionalesTab() {
  const [regimen, setRegimen] = useState<string>("");
  const contained = useContainedInput();

  const { data: grupos = [], isLoading, error } = useGruposOcupacionales();

  const filtrados = regimen
    ? (grupos as GrupoOcupacional[]).filter((g) => g.regimen === regimen)
    : (grupos as GrupoOcupacional[]);

  const columns: DataTableColumn<GrupoOcupacional>[] = [
    {
      accessor: "grado_codigo",
      title: "Grado",
      width: 100,
      render: ({ grado_codigo }) => (
        <Badge color="emerald" variant="light" size="sm">
          {grado_codigo}
        </Badge>
      ),
    },
    {
      accessor: "grupo",
      title: "Grupo Ocupacional",
      render: ({ grupo, denominacion_generica }) => (
        <div>
          <Text size="sm" fw={500}>
            {grupo}
          </Text>
          {denominacion_generica && (
            <Text size="xs" c="dimmed">
              {denominacion_generica}
            </Text>
          )}
        </div>
      ),
    },
    {
      accessor: "rmu",
      title: "RMU",
      width: 110,
      render: ({ rmu }) => (
        <Text size="sm" fw={600} c="emerald">
          ${Number(rmu).toFixed(2)}
        </Text>
      ),
    },
    {
      accessor: "regimen",
      title: "Régimen",
      width: 130,
      render: ({ regimen }) => (
        <StatusBadge tone={REGIMEN_TONOS[regimen] ?? "neutral"}>
          {REGIMEN_LABELS[regimen] ?? regimen}
        </StatusBadge>
      ),
    },
    {
      accessor: "nivel_complejidad",
      title: "Complejidad",
      width: 110,
      render: ({ nivel_complejidad }) =>
        nivel_complejidad ? (
          <StatusBadge tone={TONO_COMPLEJIDAD[nivel_complejidad] ?? "neutral"}>
            {nivel_complejidad.charAt(0).toUpperCase() +
              nivel_complejidad.slice(1)}
          </StatusBadge>
        ) : (
          <Text size="sm" c="dimmed">
            -
          </Text>
        ),
    },
  ];

  return (
    <Stack gap="md">
      <Toolbar>
        <Select
          label="Régimen"
          placeholder="Todos los regímenes"
          data={REGIMEN_OPTIONS}
          clearable
          {...contained}
          style={{ minWidth: 220 }}
          value={regimen}
          onChange={(v) => setRegimen(v ?? "")}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!filtrados.length}
        emptyProps={{
          icon: IconLayersIntersect,
          title: "Sin grupos ocupacionales",
          description: regimen
            ? "Ningún grupo ocupacional pertenece a ese régimen."
            : "Aún no hay grupos ocupacionales en el catálogo.",
        }}
      >
        <SgthTable
          records={filtrados}
          columns={columns}
          minHeight={200}
        />
      </DataState>
    </Stack>
  );
}
