"use client";

import { useState } from "react";
import { Button, Stack, Text, TextInput } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconEdit, IconPlus, IconReceipt, IconTrash } from "@tabler/icons-react";
import type { DataTableColumn } from "mantine-datatable";
import {
  DataState,
  SgthTable,
  StatusBadge,
  TableActions,
  Toolbar,
  confirmar,
} from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import { usePartidasPresupuestarias } from "../hooks/usePartidasPresupuestarias";
import { usePartidaPresupuestariaMutations } from "../hooks/usePartidaPresupuestariaMutations";
import { PartidaPresupuestariaModal } from "./PartidaPresupuestariaModal";
import type { PartidaPresupuestaria } from "@/types/api";

// El texto escrito entraba directo en la clave de consulta, así que cada tecla
// pedía la lista de partidas entera y solo importaba la última.
const RETARDO_BUSQUEDA_MS = 300;

export function PartidasPresupuestariasTab() {
  const [search, setSearch] = useState("");
  const [editPartida, setEditPartida] = useState<PartidaPresupuestaria | null>(
    null,
  );
  const [modalOpened, { open, close }] = useDisclosure(false);

  const contained = useContainedInput();
  const { eliminar } = usePartidaPresupuestariaMutations();
  const [searchConRetardo] = useDebouncedValue(search, RETARDO_BUSQUEDA_MS);
  const { data: partidas = [], isLoading, error } = usePartidasPresupuestarias(
    searchConRetardo ? { search: searchConRetardo } : undefined,
  );

  const handleEdit = (partida: PartidaPresupuestaria) => {
    setEditPartida(partida);
    open();
  };

  const handleClose = () => {
    setEditPartida(null);
    close();
  };

  const columns: DataTableColumn<PartidaPresupuestaria>[] = [
    {
      accessor: "codigo",
      title: "Código",
      width: 110,
      render: ({ codigo }) => (
        <Text size="sm" fw={600} ff="monospace">
          {codigo}
        </Text>
      ),
    },
    {
      accessor: "descripcion",
      title: "Descripción",
      render: ({ descripcion, grupo_gasto }) => (
        <div>
          <Text size="sm">{descripcion}</Text>
          <Text size="xs" c="dimmed">
            {grupo_gasto}
          </Text>
        </div>
      ),
    },
    {
      accessor: "disponible",
      title: "Disponibilidad",
      width: 150,
      render: ({ disponible }) => (
        <StatusBadge tone={disponible ? "success" : "warning"}>
          {disponible ? "Verificada" : "Sin verificar"}
        </StatusBadge>
      ),
    },
    {
      accessor: "activo",
      title: "Estado",
      width: 90,
      render: ({ activo }) => (
        <StatusBadge tone={activo ? "success" : "neutral"}>
          {activo ? "Activa" : "Inactiva"}
        </StatusBadge>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 50,
      render: (partida) => (
        <TableActions
          actions={[
            {
              label: "Editar partida",
              icon: <IconEdit size={14} />,
              color: "blue",
              onClick: () => handleEdit(partida),
            },
            {
              label: "Eliminar partida",
              icon: <IconTrash size={14} />,
              color: "red",
              onClick: () =>
                confirmar({
                  title: "Eliminar partida presupuestaria",
                  message: (
                    <>
                      Se eliminará la partida{" "}
                      <b>
                        {partida.codigo} — {partida.descripcion}
                      </b>
                      . No se puede deshacer.
                    </>
                  ),
                  destructiva: true,
                  onConfirm: () => eliminar.mutate(partida.id),
                }),
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
          <Button
            leftSection={<IconPlus size={16} />}
            color="emerald"
            variant="light"
            onClick={() => {
              setEditPartida(null);
              open();
            }}
          >
            Nueva partida
          </Button>
        }
      >
        <TextInput
          label="Código o descripción"
          placeholder="Buscar partida..."
          onChange={(e) => setSearch(e.currentTarget.value)}
          {...contained}
          style={{ minWidth: 280 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!partidas.length}
        emptyProps={{
          icon: IconReceipt,
          title: "Sin partidas presupuestarias",
          description: search
            ? "Ninguna partida coincide con la búsqueda."
            : "Aún no hay partidas registradas.",
        }}
      >
        <SgthTable
          records={partidas as PartidaPresupuestaria[]}
          columns={columns}
          minHeight={200}
        />
      </DataState>
      <PartidaPresupuestariaModal
        opened={modalOpened}
        onClose={handleClose}
        partida={editPartida}
      />
    </Stack>
  );
}
