"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ROUTES } from "@/config/routes";
import { Stack, Group, Button } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconInbox, IconPlane, IconPlus } from "@tabler/icons-react";
import { EmptyState, PageHeader, PageShell, SgthTable } from "@/components/ui";
import { useViaticos } from "../hooks/useViaticos";
import { ViaticoModal } from "./ViaticoModal";
import { ViaticoFiltros } from "./ViaticoFiltros";
import { getViaticoColumns } from "./ViaticoColumns";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import type { Viatico, EstadoViatico, ViaticoConRelaciones } from "@/types/api";

/*
| «Mis viáticos»: los propios de cada uno, como titular o acompañante, también
| para Financiero. Los de todos se trabajan en la bandeja, que tiene sus
| propias etapas, montos y plazos; a quien la puede abrir se le ofrece el
| enlace desde aquí.
*/

export function ViaticoView() {
  const router = useRouter();
  const [modalAbierto, { open, close }] = useDisclosure(false);
  const puede = useAccionesViatico();

  const [filtroEstado, setFiltroEstado] = useState("todos");
  const [page, setPage] = useState(1);
  const [busquedaCodigo, setBusquedaCodigo] = useState("");
  const [codigoQuery, setCodigoQuery] = useState("");

  const { data, isLoading } = useViaticos({
    estado:
      filtroEstado === "todos" ? undefined : (filtroEstado as EstadoViatico),
    per_page: 15,
    page,
    search: codigoQuery || undefined,
    propios: 1,
  });
  const lista = (data?.data ?? []) as ViaticoConRelaciones[];

  const handleVer = (v: ViaticoConRelaciones) =>
    router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id));

  const handleCreado = (v: Viatico) =>
    router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id));

  const columns = getViaticoColumns({
    onVer: handleVer,
    onLiquidar: handleVer,
    puede,
  });

  return (
    <PageShell>
      <PageHeader
        title="Mis viáticos"
        description="Tus comisiones de servicio, como titular o acompañante"
        actions={
          puede.veTodos && (
            <Button
              component={Link}
              href={ROUTES.PORTAL.VIATICOS_BANDEJA}
              variant="light"
              leftSection={<IconInbox size={16} />}
            >
              Bandeja de viáticos
            </Button>
          )
        }
      />

      <Stack gap="sm">
        <ViaticoFiltros
          filtroEstado={filtroEstado}
          busquedaCodigo={busquedaCodigo}
          onEstadoChange={(v) => {
            setFiltroEstado(v);
            setPage(1);
          }}
          onBusquedaChange={setBusquedaCodigo}
          onBuscar={() => {
            setCodigoQuery(busquedaCodigo.trim());
            setPage(1);
          }}
          onLimpiar={() => {
            setBusquedaCodigo("");
            setCodigoQuery("");
            setPage(1);
          }}
        />

        {puede.solicitar && (
          <Group justify="flex-end">
            <Button
              size="xs"
              variant="light"
              leftSection={<IconPlus size={14} />}
              onClick={open}
            >
              Nueva solicitud
            </Button>
          </Group>
        )}

        {lista.length === 0 && !isLoading ? (
          <EmptyState
            icon={IconPlane}
            title="Sin solicitudes de viáticos"
            description="No tienes viáticos en este estado."
          />
        ) : (
          <SgthTable
            records={lista}
            columns={columns}
            fetching={isLoading}
            minHeight={200}
            totalRecords={data?.total ?? lista.length}
            recordsPerPage={15}
            page={page}
            onPageChange={setPage}
          />
        )}
      </Stack>

      <ViaticoModal
        opened={modalAbierto}
        onClose={close}
        onCreated={handleCreado}
      />
    </PageShell>
  );
}
