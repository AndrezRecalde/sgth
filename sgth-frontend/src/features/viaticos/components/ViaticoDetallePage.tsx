"use client";

import { confirmar, EmptyState, MotivoModal, PageHeader, PageShell, SgthModal, StatusBadge } from '@/components/ui'
import { useState } from "react";
import {
  Stack,
  Grid,
  Card,
  Text,
  Group,
  Button,
  Stepper,
  Skeleton,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconPlaneOff } from "@tabler/icons-react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";

import { useViatico, useTramos } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { usePdfViatico } from "../hooks/usePdfViatico";
import { useAccionesViatico } from "../hooks/useAccionesViatico";

import { ViaticoInfoCard } from "./ViaticoInfoCard";
import { ViaticoAnticipoCard } from "./ViaticoAnticipoCard";
import { ViaticoServidoresCard } from "./ViaticoServidoresCard";
import { ViaticoItinerarioCard } from "./ViaticoItinerarioCard";
import { ViaticoLiquidacionCard } from "./ViaticoLiquidacionCard";
import { ViaticoAcciones } from "./ViaticoAcciones";
import { AprobarExteriorModal } from "./AprobarExteriorModal";
import { ViaticoEditModal } from "./ViaticoEditModal";
import { ServidoresModal } from "./ServidoresModal";
import { TramoForm } from "./TramoForm";
import { TramosList } from "./TramosList";
import { ViaticoHistorialCard } from "./ViaticoHistorialCard";
import { resumenRevision } from "../utils/revisionComprobantes";

interface Props {
  identificador: string | number;
}

import {
  TONO_VIATICO,
  ESTADO_LABELS,
  PASO_STEPPER,
} from "../constants/viatico.constants";

function ViaticoDetalleSkeleton() {
  return (
    <PageShell>
      <Group justify="space-between">
        <Stack gap="xs">
          <Skeleton height={28} width={200} radius="sm" />
          <Skeleton height={16} width={140} radius="sm" />
        </Stack>
        <Group gap="xs">
          <Skeleton height={30} width={110} radius="md" />
          <Skeleton height={30} width={110} radius="md" />
        </Group>
      </Group>
      <Skeleton height={60} radius="md" />
      <Grid>
        {[160, 120, 160, 120].map((h, i) => (
          <Grid.Col key={i} span={{ base: 12, sm: 6 }}>
            <Skeleton height={h} radius="md" />
          </Grid.Col>
        ))}
      </Grid>
      <Skeleton height={44} radius="md" />
    </PageShell>
  );
}

export function ViaticoDetallePage({ identificador }: Props) {
  const router = useRouter();
  const qc = useQueryClient();

  const { data: detalle, isLoading } = useViatico(identificador);
  const d = detalle;

  const { data: tramosData = [] } = useTramos(detalle?.id ?? null);
  const puede = useAccionesViatico();

  const [editModalAbierto, { open: abrirEdit, close: cerrarEdit }] =
    useDisclosure(false);
  const [tramosAbierto, { open: abrirTramos, close: cerrarTramos }] =
    useDisclosure(false);
  const [
    servidoresModalAbierto,
    { open: abrirServidores, close: cerrarServidores },
  ] = useDisclosure(false);
  const [exteriorModalAbierto, { open: abrirExterior, close: cerrarExterior }] =
    useDisclosure(false);

  const [mostrarTramoForm, setMostrarTramoForm] = useState(false);
  const [conMotivo, setConMotivo] = useState<"rechazar" | "devolver" | null>(
    null,
  );

  const {
    descargarSolicitud,
    descargarInforme,
    descargarComprobante,
    loadingSolicitud,
    loadingInforme,
    loadingComprobante,
  } = usePdfViatico();

  const {
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
    cancelar,
    rechazar,
    devolverCorreccion,
  } = useViaticoMutations();

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ["viatico"] });
    qc.invalidateQueries({ queryKey: ["viaticos"] });
  };

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

  const estadoActual = d.estado ?? "";
  const pasoActivo = PASO_STEPPER[estadoActual] ?? 0;
  const puedeEditarDatos = puede.editar(d);
  const puedeEditarTramos = puedeEditarDatos;

  const handleAprobar = () => {
    if (d.zona === "exterior") {
      abrirExterior();
    } else {
      aprobar.mutate({ id: d.id });
    }
  };

  return (
    <PageShell>
      <PageHeader
        title={d.codigo_viatico ?? "Viático"}
        estado={
          <StatusBadge tone={TONO_VIATICO[estadoActual] ?? 'neutral'}>
            {ESTADO_LABELS[estadoActual] ?? estadoActual}
          </StatusBadge>
        }
        onBack={() => router.back()}
      />

      {/* Stepper */}
      <Card withBorder radius="md" p="sm">
        <Stepper active={pasoActivo} size="xs">
          <Stepper.Step label="Solicitud" />
          <Stepper.Step label="Aprobado" />
          <Stepper.Step label="Anticipo" />
          <Stepper.Step label="Comisión" />
          <Stepper.Step label="Liquidar" />
          <Stepper.Step label="Liquidado" />
          <Stepper.Step label="Cerrado" />
        </Stepper>
      </Card>

      {/* Grid de secciones */}
      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoInfoCard
            viatico={d}
            puedeEditar={puedeEditarDatos}
            onEditar={abrirEdit}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoItinerarioCard
            viatico={d}
            puedeEditar={puedeEditarTramos}
            onGestionar={abrirTramos}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoAnticipoCard viatico={d} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoServidoresCard
            viatico={d}
            puedeEditar={puedeEditarDatos}
            onEditar={abrirServidores}
          />
        </Grid.Col>

        {["pendiente_liquidacion", "liquidado", "contabilizado"].includes(
          estadoActual,
        ) && (
          <Grid.Col span={12}>
            <ViaticoLiquidacionCard
              viatico={d}
              estadoActual={estadoActual}
              onSuccess={invalidar}
            />
          </Grid.Col>
        )}
      </Grid>

      <ViaticoHistorialCard historial={d.historial ?? []} />

      {/* Acciones — al final de la página */}
      <Card withBorder radius="md" p="md">
        <ViaticoAcciones
          viatico={d}
          estadoActual={estadoActual}
          puede={puede}
          onAprobar={handleAprobar}
          onEntregar={() => entregarAnticipo.mutate(d.id)}
          onComision={() => marcarEnComision.mutate(d.id)}
          onPendiente={() =>
            marcarPendienteLiquidacion.mutate(d.id)
          }
          onContabilizar={() => contabilizar.mutate(d.id)}
          onDevolverCorreccion={() => setConMotivo('devolver')}
          onCancelar={() => confirmar({
            title:   'Cancelar solicitud',
            message: 'Se cancelará esta solicitud de viático. No se puede deshacer.',
            destructiva: true,
            confirmLabel: 'Cancelar solicitud',
            cancelLabel:  'Volver',
            onConfirm: () => cancelar.mutate(d.id),
          })}
          onRechazar={() => setConMotivo('rechazar')}
          onSolicitud={() =>
            descargarSolicitud(d.codigo_viatico ?? d.id)
          }
          onInforme={() =>
            descargarInforme(d.codigo_viatico ?? d.id)
          }
          onComprobante={() =>
            descargarComprobante(d.codigo_viatico ?? d.id)
          }
          loadings={{
            aprobar:      aprobar.isPending,
            anticipo:     entregarAnticipo.isPending,
            comision:     marcarEnComision.isPending,
            pendiente:    marcarPendienteLiquidacion.isPending,
            contabilizar: contabilizar.isPending,
            devolverCorreccion: devolverCorreccion.isPending,
            cancelar:     cancelar.isPending,
            rechazar:     rechazar.isPending,
            solicitud:    loadingSolicitud,
            informe:      loadingInforme,
            comprobante:  loadingComprobante,
          }}
        />
      </Card>

      {/* Modales */}
      <MotivoModal
        opened={conMotivo !== null}
        onClose={() => setConMotivo(null)}
        title={conMotivo === "devolver" ? "Devolver para correcciones" : "Rechazar viático"}
        confirmLabel={conMotivo === "devolver" ? "Devolver" : "Rechazar"}
        destructiva={conMotivo !== "devolver"}
        cargando={rechazar.isPending || devolverCorreccion.isPending}
        valorInicial={
          conMotivo === "devolver"
            ? resumenRevision(d.liquidacion?.detalles_factura).motivoDevolucion
            : ""
        }
        descripcion={
          conMotivo === "devolver"
            ? `La liquidación de ${d.codigo_viatico} vuelve al servidor. El motivo le dice qué corregir.`
            : `El viático ${d.codigo_viatico} queda rechazado y no se puede reabrir.`
        }
        onConfirm={(motivo) => {
          const cerrar = { onSuccess: () => setConMotivo(null) };
          if (conMotivo === "devolver") {
            devolverCorreccion.mutate({ id: d.id, motivo }, cerrar);
          } else {
            rechazar.mutate({ id: d.id, motivo }, cerrar);
          }
        }}
      />

      {editModalAbierto && (
        <ViaticoEditModal
          opened={editModalAbierto}
          onClose={cerrarEdit}
          viatico={d}
          onSuccess={cerrarEdit}
        />
      )}

      {servidoresModalAbierto && (
        <ServidoresModal
          opened={servidoresModalAbierto}
          onClose={cerrarServidores}
          viatico={d}
        />
      )}

      {exteriorModalAbierto && (
        <AprobarExteriorModal
          opened={exteriorModalAbierto}
          onClose={cerrarExterior}
          viatico={d}
        />
      )}

      {/* Modal Tramos */}
      {tramosAbierto && (
        <SgthModal
          opened={tramosAbierto}
          onClose={() => {
            setMostrarTramoForm(false)
            cerrarTramos()
          }}
          title="Gestionar itinerario"
          size="xl"
        >
          <Stack gap="md">
            <TramosList viaticoId={d.id} puedeEditar={true} />
            {!mostrarTramoForm ? (
              <Button
                variant="light"
                onClick={() => setMostrarTramoForm(true)}
              >
                Agregar tramo
              </Button>
            ) : (
              <Card withBorder radius="md" p="md">
                <Text size="sm" fw={600} mb="sm">
                  Nuevo tramo
                </Text>
                <TramoForm
                  viaticoId={d.id}
                  viatico={d}
                  tramosExistentes={tramosData.length}
                  onSuccess={() => {
                    setMostrarTramoForm(false)
                  }}
                  onCancel={() => setMostrarTramoForm(false)}
                />
              </Card>
            )}
          </Stack>
        </SgthModal>
      )}
    </PageShell>
  );
}
