"use client";
import { confirmar } from '@/components/ui'
import { useState } from "react";
import { Box, Button, Group, Select } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconCubePlus } from "@tabler/icons-react";
import { SgthTable } from "@/components/ui/SgthTable";
import { usePuestos } from "../hooks/usePuestos";
import { usePuestoMutations } from "../hooks/usePuestoMutations";
import {
  useUnidades,
  useTodasUnidades,
} from '../hooks/useUnidades'
import { getPuestoColumns } from "./puesto.columns";
import { PuestoModal } from "./PuestoModal";
import { PuestoActividadesDrawer } from "./PuestoActividadesDrawer";
import { useContainedInput } from "@/hooks/useContainedInput";
import type { PuestoConRelaciones, UnidadConRelaciones } from "@/types/api";

export function PuestosTab() {
  const [page, setPage] = useState(1);
  const [unidadIds, setUnidadIds] = useState<string[]>([]);
  const [editPuesto, setEditPuesto] = useState<PuestoConRelaciones | null>(null);
  const [actividadesPuesto, setActividadesPuesto] =
    useState<PuestoConRelaciones | null>(null);
  const [modalOpened, { open, close }] = useDisclosure(false);
  const [drawerOpened,
    { open: abrirDrawer, close: cerrarDrawer }] = useDisclosure(false);
  const contained = useContainedInput();
  const { eliminar } = usePuestoMutations();
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 });
  const unidadIdNum = unidadIds.length > 0 ? Number(unidadIds[0]) : undefined;
  const { data, isLoading } = usePuestos({
    page,
    per_page: 15,
    unidad_administrativa_id: unidadIdNum,
  });
  const records = (data?.data ?? []) as PuestoConRelaciones[];
  const unidadOptions = ((unidades ?? []) as UnidadConRelaciones[]).map(
    (u) => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    }),
  );

  const handleEdit = (puesto: PuestoConRelaciones) => {
    setEditPuesto(puesto);
    open();
  };

  const handleDelete = (puesto: PuestoConRelaciones) =>
    confirmar({
      title: "Eliminar puesto",
      message: (
        <>
          Se eliminará el puesto <b>{puesto.cargo?.nombre ?? "—"}</b>. No se
          puede deshacer.
        </>
      ),
      destructiva: true,
      onConfirm: () => eliminar.mutate(Number(puesto.id)),
    });

  const handleActividades = (puesto: PuestoConRelaciones) => {
    setActividadesPuesto(puesto);
    abrirDrawer();
  };

  const handleClose = () => {
    setEditPuesto(null);
    close();
  };

  const handleCerrarDrawer = () => {
    setActividadesPuesto(null);
    cerrarDrawer();
  };

  return (
    <Box>
      <Group justify="space-between" mb="md">
        <Select
          label="Filtrar por gestión"
          placeholder="Filtrar por gestión"
          data={unidadOptions}
          searchable
          clearable
          {...contained}
          style={{ minWidth: 300 }}
          value={unidadIds[0] ?? ""}
          onChange={(v) => setUnidadIds(v ? [v] : [])}
        />
        <Button
          leftSection={<IconCubePlus size={16} />}
          color="emerald"
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
          onActividades: handleActividades,
        })}
        fetching={isLoading}
        totalRecords={data?.total || records.length || 0}
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

      <PuestoActividadesDrawer
        opened={drawerOpened}
        onClose={handleCerrarDrawer}
        puesto={actividadesPuesto}
      />
    </Box>
  );
}
