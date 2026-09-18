"use client";

import { useState } from "react";
import { Button, Grid, Paper, Skeleton, Stack, Text } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconPlaneOff, IconPlus } from "@tabler/icons-react";
import { useRouter } from "next/navigation";
import { useIsMutating, useQueryClient } from "@tanstack/react-query";
import { EmptyState, ModalFooter, PageHeader, PageShell, SgthModal, StatusBadge } from "@/components/ui";
import { useViatico, useTramos } from "../hooks/useViaticos";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import { ESTADO_LABELS, TONO_VIATICO, ZONA_LABELS } from "../constants/viatico.constants";
import { ViaticoAcciones } from "./ViaticoAcciones";
import { ViaticoAvisos } from "./ViaticoAvisos";
import { ViaticoProgreso } from "./ViaticoProgreso";
import { ViaticoInfoCard } from "./ViaticoInfoCard";
import { ViaticoAnticipoCard } from "./ViaticoAnticipoCard";
import { ViaticoItinerarioCard } from "./ViaticoItinerarioCard";
import { ViaticoLiquidacionCard } from "./ViaticoLiquidacionCard";
import { ViaticoFirmantesCard } from "./ViaticoFirmantesCard";
import { ViaticoHistorialCard } from "./ViaticoHistorialCard";
import { ViaticoEditModal } from "./ViaticoEditModal";
import { CREAR_TRAMO, TRAMO_FORM_ID, TramoForm } from "./TramoForm";
import { TramosList } from "./TramosList";
import type { TramoViatico } from "@/types/api";

/*
| La ficha del viático.
|
| Arriba, lo que se puede hacer (las acciones del flujo y los PDF en la
| cabecera) y lo que hay que saber (los avisos); debajo, los datos en
| secciones. Antes las acciones estaban al pie de la página y cada tarjeta
| armaba su propio encabezado.
*/

const CON_LIQUIDACION = ["pendiente_liquidacion", "liquidado", "contabilizado"];

interface Props {
  identificador: string | number;
}

function ViaticoDetalleSkeleton() {
  return (
    <PageShell>
      <Skeleton height={36} width={320} radius="md" />
      <Skeleton height={64} radius="lg" />
      <Grid>
        <Grid.Col span={{ base: 12, md: 7 }}>
          <Skeleton height={300} radius="lg" />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <Skeleton height={300} radius="lg" />
        </Grid.Col>
      </Grid>
      <Skeleton height={160} radius="lg" />
    </PageShell>
  );
}

export function ViaticoDetallePage({ identificador }: Props) {
  const router = useRouter();
  const qc = useQueryClient();
  const puede = useAccionesViatico();

  const { data: d, isLoading } = useViatico(identificador);
  const { data: tramos = [] } = useTramos(d?.id ?? null);

  const [editando, { open: abrirEdicion, close: cerrarEdicion }] = useDisclosure(false);
  const [itinerario, { open: abrirItinerario, close: cerrarItinerario }] = useDisclosure(false);
  // El tramo del formulario: uno nuevo, uno que se corrige, o ninguno.
  const [enForm, setEnForm] = useState<"nuevo" | TramoViatico | null>(null);
  const guardandoTramo = useIsMutating({ mutationKey: CREAR_TRAMO }) > 0;

  if (isLoading) return <ViaticoDetalleSkeleton />;
  if (!d)
    return (
      <PageShell>
        <PageHeader title="Viático" onBack={() => router.back()} />
        <EmptyState
          icon={IconPlaneOff}
          title="Viático no encontrado"
          description="El código no existe o no tiene permiso para verlo. Vuelva a la lista y ábralo desde allí."
        />
      </PageShell>
    );

  const estado = String(d.estado ?? "");
  const puedeEditar = puede.editar(d);
  const servidor = [d.servidor?.nombre, d.servidor?.apellido].filter(Boolean).join(" ");
  const conFirmas = (d.firmantes?.length ?? 0) > 0;

  const cerrarModalItinerario = () => {
    setEnForm(null);
    cerrarItinerario();
  };

  return (
    <PageShell>
      <PageHeader
        title={d.codigo_viatico ?? "Viático"}
        description={[servidor, ZONA_LABELS[d.zona ?? ""]].filter(Boolean).join(" · ")}
        estado={
          <StatusBadge tone={TONO_VIATICO[estado] ?? "neutral"}>
            {ESTADO_LABELS[estado] ?? estado}
          </StatusBadge>
        }
        onBack={() => router.back()}
        actions={<ViaticoAcciones viatico={d} puede={puede} />}
      />

      <ViaticoAvisos viatico={d} puede={puede} />
      <ViaticoProgreso viatico={d} />

      <Grid>
        <Grid.Col span={{ base: 12, md: 7 }}>
          <ViaticoInfoCard viatico={d} puedeEditar={puedeEditar} onEditar={abrirEdicion} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <ViaticoAnticipoCard viatico={d} />
        </Grid.Col>
      </Grid>

      <ViaticoItinerarioCard viatico={d} puedeEditar={puedeEditar} onGestionar={abrirItinerario} />

      {CON_LIQUIDACION.includes(estado) && (
        <ViaticoLiquidacionCard
          viatico={d}
          estadoActual={estado}
          onSuccess={() => {
            qc.invalidateQueries({ queryKey: ["viatico"] });
            qc.invalidateQueries({ queryKey: ["viaticos"] });
          }}
        />
      )}

      {(conFirmas || (d.historial?.length ?? 0) > 0) && (
        <Grid>
          {conFirmas && (
            <Grid.Col span={{ base: 12, md: 6 }}>
              <ViaticoFirmantesCard firmantes={d.firmantes ?? []} />
            </Grid.Col>
          )}
          <Grid.Col span={{ base: 12, md: conFirmas ? 6 : 12 }}>
            <ViaticoHistorialCard historial={d.historial ?? []} />
          </Grid.Col>
        </Grid>
      )}

      {editando && (
        <ViaticoEditModal opened onClose={cerrarEdicion} viatico={d} onSuccess={cerrarEdicion} />
      )}

      {itinerario && (
        <SgthModal opened onClose={cerrarModalItinerario} title="Editar itinerario" size="xl">
          <Stack gap="md">
            <TramosList viaticoId={d.id} puedeEditar onCorregir={setEnForm} />
            {enForm ? (
              <Paper withBorder radius="md" p="md">
                <Text size="sm" fw={600} mb="sm">
                  {enForm === "nuevo" ? "Nuevo tramo" : `Corregir el tramo ${enForm.orden}`}
                </Text>
                <TramoForm
                  // La clave vuelve a montar el formulario con los datos de cada tramo.
                  key={enForm === "nuevo" ? "nuevo" : enForm.id}
                  viaticoId={d.id}
                  viatico={d}
                  tramosExistentes={tramos.length}
                  tramo={enForm === "nuevo" ? null : enForm}
                  onSuccess={() => setEnForm(null)}
                />
              </Paper>
            ) : (
              <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEnForm("nuevo")}>
                Agregar tramo
              </Button>
            )}
          </Stack>
          {enForm ? (
            <ModalFooter
              onCancel={() => setEnForm(null)}
              form={TRAMO_FORM_ID}
              submitLabel={enForm === "nuevo" ? "Agregar tramo" : "Guardar cambios"}
              submitting={guardandoTramo}
            />
          ) : (
            <ModalFooter onCancel={cerrarModalItinerario} cancelLabel="Cerrar" sinPrincipal />
          )}
        </SgthModal>
      )}
    </PageShell>
  );
}
