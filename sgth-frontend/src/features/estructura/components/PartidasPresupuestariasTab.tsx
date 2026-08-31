"use client";

import { confirmar } from '@/components/ui'
import { useState } from "react";
import { Badge, Box, Button, Group, Text, TextInput } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconEdit, IconPlus, IconTrash } from "@tabler/icons-react";
import type { DataTableColumn } from "mantine-datatable";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { useContainedInput } from "@/hooks/useContainedInput";
import { usePartidasPresupuestarias } from "../hooks/usePartidasPresupuestarias";
import { usePartidaPresupuestariaMutations } from "../hooks/usePartidaPresupuestariaMutations";
import { PartidaPresupuestariaModal } from "./PartidaPresupuestariaModal";
import type { PartidaPresupuestaria } from "@/types/api";

export function PartidasPresupuestariasTab() {
  const [search, setSearch] = useState("");
  const [editPartida, setEditPartida] = useState<PartidaPresupuestaria | null>(
    null,
  );
  const [modalOpened, { open, close }] = useDisclosure(false);

  const contained = useContainedInput();
  const { eliminar } = usePartidaPresupuestariaMutations();
  const { data: partidas = [], isLoading } = usePartidasPresupuestarias(
    search ? { search } : undefined,
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
        <Badge
          color={disponible ? "emerald" : "orange"}
          variant="light"
          size="sm"
        >
          {disponible ? "Verificada" : "Sin verificar"}
        </Badge>
      ),
    },
    {
      accessor: "activo",
      title: "Estado",
      width: 90,
      render: ({ activo }) => (
        <Badge color={activo ? "emerald" : "gray"} variant="light" size="sm">
          {activo ? "Activa" : "Inactiva"}
        </Badge>
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
    <Box>
      <Group justify="space-between" mb="md">
        <TextInput
          label="Filtrar por código o descripción"
          placeholder="Buscar partida..."
          onChange={(e) => setSearch(e.currentTarget.value)}
          {...contained}
          style={{ minWidth: 280 }}
        />
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
      </Group>
      <SgthTable
        records={partidas as PartidaPresupuestaria[]}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />
      <PartidaPresupuestariaModal
        opened={modalOpened}
        onClose={handleClose}
        partida={editPartida}
      />
    </Box>
  );
}
