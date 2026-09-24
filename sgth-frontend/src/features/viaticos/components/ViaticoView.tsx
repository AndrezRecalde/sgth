"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ROUTES } from "@/config/routes";
import { Button } from "@mantine/core";
import { useDebouncedValue, useDisclosure } from "@mantine/hooks";
import { IconInbox, IconPlane, IconPlus } from "@tabler/icons-react";
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable } from "@/components/ui";
import { useViaticos } from "../hooks/useViaticos";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import { ViaticoModal } from "./ViaticoModal";
import {
  FILTROS_VIATICO_INICIALES,
  ViaticoFiltros,
  type FiltrosViaticoForm,
} from "./ViaticoFiltros";
import { getViaticoColumns } from "./viatico.columns";
import type { EstadoViatico, Viatico, ViaticoConRelaciones } from "@/types/api";

/*
| «Mis viáticos»: los de quien tiene la sesión, también si es de Financiero.
| Los de todos se trabajan en la bandeja, que tiene sus propias etapas, montos
| y plazos; a quien la puede abrir se le ofrece el enlace desde aquí.
*/

export function ViaticoView() {
  const router = useRouter();
  const [modalAbierto, { open, close }] = useDisclosure(false);
  const puede = useAccionesViatico();

  const [filtros, setFiltros] = useState<FiltrosViaticoForm>(FILTROS_VIATICO_INICIALES);
  const [page, setPage] = useState(1);
  const [busqueda] = useDebouncedValue(filtros.busqueda.trim(), 400);

  const { data, isLoading, error } = useViaticos({
    estado: (filtros.estado as EstadoViatico | null) ?? undefined,
    zona: filtros.zona ?? undefined,
    search: busqueda || undefined,
    per_page: 15,
    page,
    propios: 1,
  });
  const lista: ViaticoConRelaciones[] = data?.data ?? [];
  const filtrado = busqueda !== "" || filtros.estado !== null || filtros.zona !== null;

  const abrir = (v: Pick<Viatico, "id" | "codigo_viatico">) =>
    router.push(ROUTES.PORTAL.VIATICO_DETALLE(v.codigo_viatico ?? v.id));

  const nuevaSolicitud = puede.solicitar && (
    <Button variant="light" leftSection={<IconPlus size={16} />} onClick={open}>
      Nueva solicitud
    </Button>
  );

  return (
    <PageShell>
      <PageHeader
        title="Mis viáticos"
        description="Sus comisiones de servicio: la solicitud, el anticipo y la liquidación"
        actions={
          <>
            {puede.veTodos && (
              <Button
                component={Link}
                href={ROUTES.PORTAL.VIATICOS_BANDEJA}
                variant="default"
                leftSection={<IconInbox size={16} />}
              >
                Bandeja de viáticos
              </Button>
            )}
            {nuevaSolicitud}
          </>
        }
      />

      <ViaticoFiltros
        filtros={filtros}
        onCambiar={(cambio) => {
          setFiltros((f) => ({ ...f, ...cambio }));
          setPage(1);
        }}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={lista.length === 0}
        emptyProps={
          filtrado
            ? {
                icon: IconPlane,
                title: "Ningún viático coincide",
                description: "Cambie o borre los filtros para ver los demás.",
              }
            : {
                icon: IconPlane,
                title: "Todavía no tiene viáticos",
                description: "Cuando lo envíen a una comisión, solicite aquí el viático antes de viajar.",
                action: nuevaSolicitud || undefined,
              }
        }
        page={page}
      >
        <SgthTable
          records={lista}
          columns={getViaticoColumns({ onVer: abrir, puede })}
          onRowClick={({ record }) => abrir(record)}
          // Las acciones a la vista aunque la tabla se desplace de lado.
          pinLastColumn
          minHeight={200}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          {...PAGINACION_ES}
        />
      </DataState>

      <ViaticoModal opened={modalAbierto} onClose={close} onCreated={abrir} />
    </PageShell>
  );
}
