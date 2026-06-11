"use client";

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
  Badge,
  Modal,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { IconArrowLeft } from "@tabler/icons-react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";

import { useViatico, useTramos } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { usePdfViatico } from "../hooks/usePdfViatico";

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

import type { ViaticoConRelaciones, Viatico } from "@/types/api";

interface Props {
  identificador: string | number;
}

import {
  ESTADO_COLORS,
  ESTADO_LABELS,
  PASO_STEPPER,
} from "../constants/viatico.constants";

function ViaticoDetalleSkeleton() {
  return (
    <Stack gap="md" p="md">
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
    </Stack>
  );
}

export function ViaticoDetallePage({ identificador }: Props) {
  const router = useRouter();
  const qc = useQueryClient();

  const { data: detalle, isLoading } = useViatico(identificador);
  const d = detalle as ViaticoConRelaciones | undefined;

  const { data: tramosData = [] } = useTramos(detalle?.id ?? null);

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
  } = useViaticoMutations();

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ["viatico"] });
    qc.invalidateQueries({ queryKey: ["viaticos"] });
  };

  if (isLoading) return <ViaticoDetalleSkeleton />;
  if (!d)
    return (
      <Stack p="md">
        <Text c="dimmed">Viático no encontrado.</Text>
      </Stack>
    );

  const estadoActual = d.estado ?? "";
  const pasoActivo = PASO_STEPPER[estadoActual] ?? 0;
  const puedeEditarDatos = !["liquidado", "contabilizado"].includes(
    estadoActual,
  );
  const puedeEditarTramos = !["liquidado", "contabilizado"].includes(
    estadoActual,
  );

  const handleAprobar = () => {
    if (d.zona === "exterior") {
      abrirExterior();
    } else {
      aprobar.mutate({ id: d.id });
    }
  };

  return (
    <Stack gap="md" p="md">
      {/* Header */}
      <Group justify="space-between">
        <Group gap="xs">
          <Button
            size="xs"
            variant="subtle"
            leftSection={<IconArrowLeft size={14} />}
            onClick={() => router.back()}
          >
            Volver
          </Button>
          <div>
            <Text fw={700} size="lg">
              {d.codigo_viatico ?? "—"}
            </Text>
            <Badge
              color={ESTADO_COLORS[estadoActual] ?? "gray"}
              variant="light"
              size="sm"
            >
              {ESTADO_LABELS[estadoActual] ?? estadoActual}
            </Badge>
          </div>
        </Group>
      </Group>

      {/* Stepper */}
      <Card withBorder radius="md" p="sm">
        <Stepper active={pasoActivo} size="xs" color="emerald">
          <Stepper.Step label="Solicitud" />
          <Stepper.Step label="Aprobado" />
          <Stepper.Step label="Anticipo" />
          <Stepper.Step label="Comisión" />
          <Stepper.Step label="Liquidar" />
          <Stepper.Step label="Liquidado" />
          <Stepper.Step label="Cerrado" />
        </Stepper>
      </Card>

      {/* Acciones */}
      <ViaticoAcciones
        viatico={d}
        estadoActual={estadoActual}
        onAprobar={handleAprobar}
        onEntregar={() => entregarAnticipo.mutate(d.id)}
        onComision={() => marcarEnComision.mutate(d.id)}
        onPendiente={() => marcarPendienteLiquidacion.mutate(d.id)}
        onContabilizar={() => contabilizar.mutate(d.id)}
        onCancelar={() => {
          if (confirm("Cancelar esta solicitud?")) cancelar.mutate(d.id);
        }}
        onRechazar={() => {
          if (confirm("Rechazar este viático?")) rechazar.mutate(d.id);
        }}
        onSolicitud={() => descargarSolicitud(d.codigo_viatico ?? d.id)}
        onInforme={() => descargarInforme(d.codigo_viatico ?? d.id)}
        onComprobante={() => descargarComprobante(d.codigo_viatico ?? d.id)}
        loadings={{
          aprobar: aprobar.isPending,
          anticipo: entregarAnticipo.isPending,
          comision: marcarEnComision.isPending,
          pendiente: marcarPendienteLiquidacion.isPending,
          contabilizar: contabilizar.isPending,
          cancelar: cancelar.isPending,
          rechazar: rechazar.isPending,
          solicitud: loadingSolicitud,
          informe: loadingInforme,
          comprobante: loadingComprobante,
        }}
      />

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
          <ViaticoAnticipoCard viatico={d} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoServidoresCard
            viatico={d}
            puedeEditar={puedeEditarDatos}
            onEditar={abrirServidores}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <ViaticoItinerarioCard
            viatico={d}
            puedeEditar={puedeEditarTramos}
            onGestionar={abrirTramos}
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

      {/* Modales */}
      {editModalAbierto && (
        <ViaticoEditModal
          opened={editModalAbierto}
          onClose={cerrarEdit}
          viatico={d as unknown as Viatico}
          onSuccess={cerrarEdit}
        />
      )}

      {servidoresModalAbierto && (
        <ServidoresModal
          opened={servidoresModalAbierto}
          onClose={cerrarServidores}
          viatico={d as unknown as Viatico}
        />
      )}

      {exteriorModalAbierto && (
        <AprobarExteriorModal
          opened={exteriorModalAbierto}
          onClose={cerrarExterior}
          viatico={d as unknown as Viatico}
        />
      )}

      {/* Modal Tramos */}
      <Modal
        opened={tramosAbierto}
        onClose={cerrarTramos}
        title="Gestionar itinerario"
        size="xl"
        radius="xl"
      >
        <Stack gap="md">
          <TramosList viaticoId={d.id} puedeEditar={true} />
          {!mostrarTramoForm ? (
            <Button
              variant="light"
              color="blue"
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
                tramosExistentes={
                  (tramosData as import("@/types/api").TramoViatico[]).length
                }
                onSuccess={() => setMostrarTramoForm(false)}
                onCancel={() => setMostrarTramoForm(false)}
              />
            </Card>
          )}
        </Stack>
      </Modal>
    </Stack>
  );
}
