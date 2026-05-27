"use client";

import { Box, Badge, Text, Group, Select } from "@mantine/core";
import { SgthTable } from "@/components/ui/SgthTable";
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

const COMPLEJIDAD_COLORS: Record<string, string> = {
  bajo: "gray",
  medio: "orange",
  alto: "emerald",
};

export function GruposOcupacionalesTab() {
  const [regimen, setRegimen] = useState<string>("");
  const contained = useContainedInput();

  const { data: grupos = [], isLoading } = useGruposOcupacionales();

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
        <Badge
          color={regimen === "losep" ? "blue" : "orange"}
          variant="light"
          size="sm"
        >
          {regimen === "losep" ? "LOSEP" : "Cód. Trabajo"}
        </Badge>
      ),
    },
    {
      accessor: "nivel_complejidad",
      title: "Complejidad",
      width: 110,
      render: ({ nivel_complejidad }) =>
        nivel_complejidad ? (
          <Badge
            color={COMPLEJIDAD_COLORS[nivel_complejidad] ?? "gray"}
            variant="dot"
            size="sm"
          >
            {nivel_complejidad.charAt(0).toUpperCase() +
              nivel_complejidad.slice(1)}
          </Badge>
        ) : (
          <Text size="sm" c="dimmed">
            -
          </Text>
        ),
    },
  ];

  return (
    <Box>
      <Group justify="flex-end" mb="md">
        <Select
          label="Buscar por Régimen"
          placeholder="Filtrar por régimen"
          data={REGIMEN_OPTIONS}
          clearable
          {...contained}
          style={{ minWidth: 220 }}
          value={regimen}
          onChange={(v) => setRegimen(v ?? "")}
        />
      </Group>
      <SgthTable
        records={filtrados}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />
    </Box>
  );
}
