"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { ROUTES } from "@/config/routes";
import { Stack, Group, Button, Tabs } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconPlane, IconPlus } from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { SgthTable } from "@/components/ui/SgthTable";
import { EmptyState } from "@/components/ui/EmptyState";
import { useViaticos } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { ViaticoModal } from "./ViaticoModal";
import { VuelosTab } from "./VuelosTab";
import { ViaticoFiltros } from "./ViaticoFiltros";
import { getViaticoColumns } from "./ViaticoColumns";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import type { Viatico, EstadoViatico, ViaticoConRelaciones } from "@/types/api";

export function ViaticoView() {
  const router = useRouter();
  const [modalAbierto, { open, close }] = useDisclosure(false);
  const puede = useAccionesViatico();

  // Quien revisa entra a la bandeja de solicitudes; el servidor, a todos los
  // suyos, que son pocos y le interesan en cualquier estado.
  const [filtroEstado, setFiltroEstado] = useState(
    puede.veTodos ? "solicitado" : "todos",
  );
  const [page, setPage] = useState(1);
  const [busquedaCodigo, setBusquedaCodigo] = useState("");
  const [codigoQuery, setCodigoQuery] = useState("");

  const filtros = {
    estado:
      filtroEstado === "todos" ? undefined : (filtroEstado as EstadoViatico),
    per_page: 15,
    page,
    search: codigoQuery || undefined,
  };

  const { data, isLoading } = useViaticos(filtros);
  const lista = (data?.data ?? []) as ViaticoConRelaciones[];
  const { aprobar } = useViaticoMutations();

  const handleVer = (v: ViaticoConRelaciones) =>
    router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id));

  const handleCreado = (v: Viatico) =>
    router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id));

  const handleBuscar = () => {
    setCodigoQuery(busquedaCodigo.trim());
    setPage(1);
  };

  const handleLimpiar = () => {
    setBusquedaCodigo("");
    setCodigoQuery("");
    setPage(1);
  };

  const handleEstado = (v: string) => {
    setFiltroEstado(v);
    setPage(1);
  };

  const columns = getViaticoColumns({
    onVer: handleVer,
    onAprobar: (v) => aprobar.mutate({ id: v.id }),
    onLiquidar: handleVer,
    puede,
  });

  return (
    <PageShell>
      <PageHeader
        title="Viáticos"
        description={
          puede.veTodos
            ? "Gestión de comisiones de servicio y viáticos"
            : "Tus comisiones de servicio y viáticos"
        }
      />

      <Tabs defaultValue="viaticos">
        <Tabs.List>
          <Tabs.Tab value="viaticos">Solicitudes</Tabs.Tab>
          {puede.autorizarVuelos && (
            <Tabs.Tab value="vuelos">Autorizaciones de vuelo</Tabs.Tab>
          )}
        </Tabs.List>

        <Tabs.Panel value="viaticos" pt="md">
          <Stack gap="sm">
            <ViaticoFiltros
              filtroEstado={filtroEstado}
              busquedaCodigo={busquedaCodigo}
              onEstadoChange={handleEstado}
              onBusquedaChange={setBusquedaCodigo}
              onBuscar={handleBuscar}
              onLimpiar={handleLimpiar}
            />

            {puede.solicitar && (
              <Group justify="flex-end">
                <Button
                  size="xs"
                  color="emerald"
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
                description="No hay viáticos en este estado."
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
        </Tabs.Panel>

        {puede.autorizarVuelos && (
          <Tabs.Panel value="vuelos" pt="md">
            <VuelosTab />
          </Tabs.Panel>
        )}
      </Tabs>

      <ViaticoModal
        opened={modalAbierto}
        onClose={close}
        onCreated={handleCreado}
      />
    </PageShell>
  );
}

import { PageShell } from '@/components/ui'