"use client";

import { useState } from "react";
import { Button, Stack, TextInput } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconBriefcase, IconPlus } from "@tabler/icons-react";
import {
  DataState,
  SgthTable,
  StatusBadge,
  TableActions,
  Toolbar,
  confirmar,
} from "@/components/ui";
import { IconEdit, IconTrash } from "@tabler/icons-react";
import { useCargos } from "../hooks/useCargos";
import { useCargoMutations } from "../hooks/useCargoMutations";
import { CargoModal } from "./CargoModal";
import { useContainedInput } from "@/hooks/useContainedInput";
import type { Cargo } from "@/types/api";
import { Text } from "@mantine/core";
import type { DataTableColumn } from "mantine-datatable";

// El texto escrito entraba directo en la clave de consulta, así que cada tecla
// pedía la lista de cargos entera y solo importaba la última.
const RETARDO_BUSQUEDA_MS = 300;

export function CargosTab() {
  const [search, setSearch] = useState("");
  const [editCargo, setEditCargo] = useState<Cargo | null>(null);
  const [modalOpened, { open, close }] = useDisclosure(false);

  const contained = useContainedInput();
  const { eliminar } = useCargoMutations();
  const [searchConRetardo] = useDebouncedValue(search, RETARDO_BUSQUEDA_MS);
  const { data: cargos = [], isLoading, error } = useCargos(
    searchConRetardo ? { search: searchConRetardo } : undefined,
  );

  const handleEdit = (cargo: Cargo) => {
    setEditCargo(cargo);
    open();
  };

  const handleClose = () => {
    setEditCargo(null);
    close();
  };

  const columns: DataTableColumn<Cargo>[] = [
    {
      accessor: "nombre",
      title: "Cargo",
      render: ({ nombre, denominacion_generica }) => (
        <div>
          <Text size="sm" fw={500}>
            {nombre}
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
      accessor: "activo",
      title: "Estado",
      width: 90,
      render: ({ activo }) => (
        <StatusBadge tone={activo ? "success" : "neutral"}>
          {activo ? "Activo" : "Inactivo"}
        </StatusBadge>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 50,
      render: (cargo) => (
        <TableActions
          actions={[
            {
              label: "Editar cargo",
              icon: <IconEdit size={14} />,
              color: "blue",
              onClick: () => handleEdit(cargo),
            },
            {
              label: "Eliminar cargo",
              icon: <IconTrash size={14} />,
              color: "red",
              onClick: () =>
                confirmar({
                  title: "Eliminar cargo",
                  message: (
                    <>
                      Se eliminará el cargo <b>{cargo.nombre}</b>. No se puede
                      deshacer.
                    </>
                  ),
                  destructiva: true,
                  onConfirm: () => eliminar.mutate(cargo.id),
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
              setEditCargo(null);
              open();
            }}
          >
            Nuevo cargo
          </Button>
        }
      >
        <TextInput
          label="Cargo"
          placeholder="Buscar cargo..."
          onChange={(e) => setSearch(e.currentTarget.value)}
          {...contained}
          style={{ minWidth: 280 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!cargos.length}
        emptyProps={{
          icon: IconBriefcase,
          title: "Sin cargos",
          description: search
            ? "Ningún cargo coincide con la búsqueda."
            : "Aún no hay cargos registrados en el catálogo.",
        }}
      >
        <SgthTable
          records={cargos as Cargo[]}
          columns={columns}
          minHeight={200}
        />
      </DataState>
      <CargoModal
        opened={modalOpened}
        onClose={handleClose}
        cargo={editCargo}
      />
    </Stack>
  );
}
