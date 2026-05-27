"use client";

import { useState } from "react";
import { Box, Button, Group, TextInput } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconPlus } from "@tabler/icons-react";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { IconEdit, IconTrash } from "@tabler/icons-react";
import { useCargos } from "../hooks/useCargos";
import { useCargoMutations } from "../hooks/useCargoMutations";
import { CargoModal } from "./CargoModal";
import { useContainedInput } from "@/hooks/useContainedInput";
import type { Cargo, ClasificacionPersonal } from "@/types/api";
import { Badge, Text } from "@mantine/core";
import type { DataTableColumn } from "mantine-datatable";

const CLASIFICACION_LABELS: Record<ClasificacionPersonal, string> = {
  empleado: "Empleado",
  contratado: "Contratado",
  obrero: "Obrero",
};

const CLASIFICACION_COLORS: Record<ClasificacionPersonal, string> = {
  empleado: "blue",
  contratado: "orange",
  obrero: "violet",
};

export function CargosTab() {
  const [search, setSearch] = useState("");
  const [editCargo, setEditCargo] = useState<Cargo | null>(null);
  const [modalOpened, { open, close }] = useDisclosure(false);

  const contained = useContainedInput();
  const { eliminar } = useCargoMutations();
  const { data: cargos = [], isLoading } = useCargos(
    search ? { search } : undefined,
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
      accessor: "clasificacion_personal",
      title: "Clasificación",
      width: 130,
      render: ({ clasificacion_personal }) => (
        <Badge
          color={CLASIFICACION_COLORS[clasificacion_personal] ?? "gray"}
          variant="light"
          size="sm"
        >
          {CLASIFICACION_LABELS[clasificacion_personal] ??
            clasificacion_personal}
        </Badge>
      ),
    },
    {
      accessor: "activo",
      title: "Estado",
      width: 90,
      render: ({ activo }) => (
        <Badge color={activo ? "emerald" : "gray"} variant="light" size="sm">
          {activo ? "Activo" : "Inactivo"}
        </Badge>
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
              onClick: () => {
                if (confirm(`¿Eliminar el cargo "${cargo.nombre}"?`)) {
                  eliminar.mutate(cargo.id);
                }
              },
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Box>
      <Group justify="space-between" mb="md">
        <TextInput
          label="Filtar por cargo"
          placeholder="Buscar cargo..."
          onChange={(e) => setSearch(e.currentTarget.value)}
          {...contained}
          style={{ minWidth: 280 }}
        />
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
      </Group>
      <SgthTable
        records={cargos as Cargo[]}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />
      <CargoModal
        opened={modalOpened}
        onClose={handleClose}
        cargo={editCargo}
      />
    </Box>
  );
}
