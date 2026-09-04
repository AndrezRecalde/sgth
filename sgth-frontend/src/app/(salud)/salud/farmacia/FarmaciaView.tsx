"use client";

import { useState } from "react";
import Link from "next/link";
import {
  Group,
  Button,
  TextInput,
  ActionIcon,
  Chip,
  Indicator,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconSearch,
  IconX,
  IconShoppingCart,
  IconCubePlus,
  IconPill,
} from "@tabler/icons-react";
import { DataState, PageHeader, SgthTable, Toolbar } from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import {
  useInventarioMedicinas,
  useInventarioMutations,
  useStockBajoCount,
} from "@/features/dispensario/hooks/useInventarioMedicina";
import { MedicinaModal } from "@/features/dispensario/components/MedicinaModal";
import { AjustarInventarioModal } from "@/features/dispensario/components/AjustarInventarioModal";
import { KardexDrawer } from "@/features/dispensario/components/KardexDrawer";
import { getMedicinasColumns } from "@/features/dispensario/components/medicinas.columns";
import type { InventarioMedicina } from "@/features/dispensario/services/inventarioMedicinaService";

export function FarmaciaView() {
  const contained = useContainedInput("sm");
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [filtroEstado, setFiltroEstado] = useState<
    "todos" | "activos" | "inactivos"
  >("activos");
  const [filtroStockBajo, setFiltroStockBajo] = useState(false);

  const [medicinaSel, setMedicinaSel] = useState<InventarioMedicina | null>(
    null,
  );

  const [modalOpened, { open: abrirModal, close: cerrarModal }] =
    useDisclosure(false);
  const [ajustarOpened, { open: abrirAjustar, close: cerrarAjustar }] =
    useDisclosure(false);
  const [kardexOpened, { open: abrirKardex, close: cerrarKardex }] =
    useDisclosure(false);

  const { data, isLoading, error } = useInventarioMedicinas({
    page,
    per_page: 15,
    search: query || undefined,
    estado:
      filtroEstado === "todos"
        ? undefined
        : filtroEstado === "activos"
          ? "true"
          : "false",
    stock_bajo: filtroStockBajo || undefined,
  });
  const { data: stockBajoCount = 0 } = useStockBajoCount();
  const { toggleEstado } = useInventarioMutations();

  const medicinas = data?.data ?? [];

  const handleBuscar = () => {
    setQuery(search.trim());
    setPage(1);
  };

  const columns = getMedicinasColumns({
    onEditar: (m) => {
      setMedicinaSel(m);
      abrirModal();
    },
    onAjustar: (m) => {
      setMedicinaSel(m);
      abrirAjustar();
    },
    onVerKardex: (m) => {
      setMedicinaSel(m);
      abrirKardex();
    },
    onToggleEstado: (m) =>
      confirmar({
        title: m.estado ? "Dar de baja medicina" : "Reactivar medicina",
        message: m.estado ? (
          <>
            Se dará de baja <b>{m.nombre}</b>. Dejará de aparecer en
            despachos y recetas.
          </>
        ) : (
          <>
            Se reactivará <b>{m.nombre}</b>. Volverá a estar
            disponible para despacho.
          </>
        ),
        destructiva: m.estado,
        confirmLabel: m.estado ? "Dar de baja" : "Reactivar",
        onConfirm: () => toggleEstado.mutate(m.id),
      }),
  });

  return (
    <PageShell>
      <PageHeader
        title="Farmacia"
        description="Gestión del inventario de medicinas"
        actions={
          <Group gap="xs">
            <Button
              component={Link}
              href="/salud/farmacia/adquisiciones"
              variant="light"
              color="blue"
              leftSection={<IconShoppingCart size={14} />}
            >
              Adquisiciones
            </Button>
            <Button
              variant="light"
              color="emerald"
              leftSection={<IconCubePlus size={14} />}
              onClick={() => {
                setMedicinaSel(null);
                abrirModal();
              }}
            >
              Nueva medicina
            </Button>
          </Group>
        }
      />

      <Toolbar
        actions={
          <Button
            variant="light"
            leftSection={<IconSearch size={14} />}
            onClick={handleBuscar}
          >
            Buscar
          </Button>
        }
      >
        <TextInput
          label="Buscar"
          placeholder="Nombre, código o principio activo"
          {...contained}
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") handleBuscar();
          }}
          style={{ minWidth: 300 }}
          rightSection={
            search ? (
              <ActionIcon
                size="sm"
                variant="subtle"
                color="gray"
                onClick={() => {
                  setSearch("");
                  setQuery("");
                  setPage(1);
                }}
              >
                <IconX size={12} />
              </ActionIcon>
            ) : null
          }
        />

        <Group gap="xs">
          <Chip
          checked={filtroEstado === "activos"}
          onChange={() => {
            setFiltroEstado(filtroEstado === "activos" ? "todos" : "activos");
            setPage(1);
          }}
          color="emerald"
          size="sm"
        >
          Solo activas
        </Chip>
        <Chip
          checked={filtroEstado === "inactivos"}
          onChange={() => {
            setFiltroEstado(
              filtroEstado === "inactivos" ? "todos" : "inactivos",
            );
            setPage(1);
          }}
          color="gray"
          size="sm"
        >
          Inactivas
        </Chip>
        <Indicator
          label={stockBajoCount > 0 ? stockBajoCount : undefined}
          disabled={stockBajoCount === 0}
          color="red"
          size={16}
          offset={4}
        >
          <Chip
            checked={filtroStockBajo}
            onChange={() => {
              setFiltroStockBajo((v) => !v);
              setPage(1);
            }}
            color="red"
            size="sm"
          >
            Stock bajo
          </Chip>
        </Indicator>
        </Group>
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!medicinas.length}
        emptyProps={{
          icon: IconPill,
          title: "Sin medicinas",
          description: query || filtroStockBajo
            ? "Ninguna medicina coincide con los filtros aplicados."
            : "Aún no hay medicinas registradas en el inventario.",
        }}
      >
      <SgthTable
        records={medicinas}
        columns={columns}
        minHeight={200}
        totalRecords={data?.total ?? 0}
        recordsPerPage={15}
        page={page}
        onPageChange={setPage}
      />
      </DataState>

      <MedicinaModal
        opened={modalOpened}
        onClose={() => {
          setMedicinaSel(null);
          cerrarModal();
        }}
        initialValues={medicinaSel}
      />

      <AjustarInventarioModal
        opened={ajustarOpened}
        onClose={() => {
          setMedicinaSel(null);
          cerrarAjustar();
        }}
        medicina={medicinaSel}
      />

      <KardexDrawer
        opened={kardexOpened}
        onClose={() => {
          setMedicinaSel(null);
          cerrarKardex();
        }}
        medicina={medicinaSel}
      />
    </PageShell>
  );
}

import { PageShell , confirmar } from '@/components/ui'