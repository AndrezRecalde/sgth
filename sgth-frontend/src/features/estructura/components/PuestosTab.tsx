"use client";

import { useState } from "react";
import { Box, Button, Group } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconCubePlus } from "@tabler/icons-react";
import { SgthTable } from '@/components/ui/SgthTable';
import { usePuestos } from "../hooks/usePuestos";
import { usePuestoMutations } from "../hooks/usePuestoMutations";
import { getPuestoColumns } from "./puesto.columns";
import type { PuestoConRelaciones } from "@/types/api";
import { PuestoModal } from "./PuestoModal";

export function PuestosTab() {
  const [page, setPage] = useState(1);
  const [editPuesto, setEditPuesto] = useState<PuestoConRelaciones | null>(
    null,
  );
  const [modalOpened, { open, close }] = useDisclosure(false);
  const { eliminar } = usePuestoMutations();

  const { data, isLoading } = usePuestos({ page, per_page: 15 });
  const records = (data?.data ?? []) as PuestoConRelaciones[];

  const handleEdit = (puesto: PuestoConRelaciones) => {
    setEditPuesto(puesto);
    open();
  };

  const handleDelete = (puesto: PuestoConRelaciones) => {
    if (confirm(`¿Eliminar el puesto "${puesto.denominacion}"?`)) {
      eliminar.mutate(Number(puesto.id));
    }
  };

  const handleClose = () => {
    setEditPuesto(null);
    close();
  };

  return (
    <Box>
      <Group justify="flex-end" mb="sm">
        <Button
          radius="lg"
          leftSection={<IconCubePlus size={20} stroke={2} />}
          variant="light"
          onClick={() => {
            setEditPuesto(null);
            open();
          }}
        >
          Nuevo puesto
        </Button>
      </Group>
      <SgthTable
        records={records}
        columns={getPuestoColumns({
          onEdit: handleEdit,
          onDelete: handleDelete,
        })}
        fetching={isLoading}
        totalRecords={data?.total ?? 0}
        recordsPerPage={15}
        page={page}
        onPageChange={setPage}
        minHeight={200}
      />
      <PuestoModal
        opened={modalOpened}
        onClose={handleClose}
        puesto={editPuesto}
      />
    </Box>
  );
}
