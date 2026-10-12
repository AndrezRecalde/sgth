"use client";

import { Button, Select, Stack, Text } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconLayersIntersect, IconTrendingUp } from "@tabler/icons-react";
import { DataState, SgthTable, StatusBadge, Toolbar } from "@/components/ui";
import { REGIMEN_LABELS } from "@/lib/regimen";
import { useAuth } from "@/hooks/useAuth";
import { useGruposOcupacionales, type GrupoOcupacional } from "../hooks/useGruposOcupacionales";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useState } from "react";
import type { DataTableColumn } from "mantine-datatable";
import { EscalasRemuneracionSection } from "./EscalasRemuneracionSection";
import { NuevaEscalaModal } from "./NuevaEscalaModal";

const REGIMEN_OPTIONS = [
  { value: "", label: "Todos los regímenes" },
  { value: "losep", label: "LOSEP" },
  { value: "codigo_trabajo", label: "Código del Trabajo" },
];

export function GruposOcupacionalesTab() {
  const [regimen, setRegimen] = useState<string>("");
  const contained = useContainedInput();

  const { data: grupos = [], isLoading, error } = useGruposOcupacionales();
  // La nueva escala de la LOSEP (fase 7.3b): la prepara quien prepara
  // acciones; la registra el Director.
  const { hasPermiso } = useAuth();
  const veEscalas = hasPermiso("preparar-accion-personal") || hasPermiso("registrar-accion-personal");
  const [escalaAbierta, { open: abrirEscala, close: cerrarEscala }] = useDisclosure(false);

  const filtrados = regimen
    ? (grupos as GrupoOcupacional[]).filter((g) => g.regimen === regimen)
    : (grupos as GrupoOcupacional[]);

  const columns: DataTableColumn<GrupoOcupacional>[] = [
    {
      accessor: "grado_codigo",
      title: "Grado",
      width: 100,
      render: ({ grado_codigo }) => (
        <StatusBadge>
          {grado_codigo}
        </StatusBadge>
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
        <StatusBadge>
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
          <StatusBadge>
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
      <Toolbar
        actions={veEscalas && (
          <Button leftSection={<IconTrendingUp size={16} />} onClick={abrirEscala}>
            Nueva escala
          </Button>
        )}
      >
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

      {veEscalas && <EscalasRemuneracionSection />}

      <NuevaEscalaModal opened={escalaAbierta} onClose={cerrarEscala} />
    </Stack>
  );
}
