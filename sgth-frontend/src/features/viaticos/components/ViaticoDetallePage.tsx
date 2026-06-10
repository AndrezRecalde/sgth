"use client";

import { useState } from "react";
import {
  Stack,
  Grid,
  Card,
  Text,
  Badge,
  Group,
  Button,
  Stepper,
  Alert,
  ActionIcon,
  Skeleton,
  Divider,
  ThemeIcon,
  Modal,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconArrowLeft,
  IconPencil,
  IconCurrencyDollar,
  IconPlane,
  IconRoute,
  IconClipboardList,
  IconCircleCheck,
  IconAlertCircle,
  IconUsers,
  IconFileInvoice,
  IconDownload,
  IconFileText,
  IconChecks,
  IconReceipt,
  IconX,
  IconBan,
} from "@tabler/icons-react";
import { AprobarExteriorModal } from "./AprobarExteriorModal";
import { usePdfViatico } from "../hooks/usePdfViatico";
import { useViatico, useTramos } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { useQueryClient } from "@tanstack/react-query";
import { TramosList } from "./TramosList";
import { TramoForm } from "./TramoForm";
import { LiquidacionSection } from "./LiquidacionSection";
import { ViaticoEditModal } from "./ViaticoEditModal";
import { ServidoresModal } from "./ServidoresModal";
import { useRouter } from "next/navigation";
import type { ViaticoConRelaciones, Viatico } from "@/types/api";

interface Props {
  identificador: string | number;
}

const ESTADO_COLORS: Record<string, string> = {
  solicitado: "orange",
  aprobado: "blue",
  con_anticipo: "cyan",
  en_comision: "violet",
  pendiente_liquidacion: "yellow",
  liquidado: "emerald",
  contabilizado: "gray",
  cancelado: "red",
  rechazado: "orange",
};

const ESTADO_LABELS: Record<string, string> = {
  solicitado: "Solicitado",
  aprobado: "Aprobado",
  con_anticipo: "Con anticipo",
  en_comision: "En comisión",
  pendiente_liquidacion: "Pendiente de liquidación",
  liquidado: "Liquidado",
  contabilizado: "Contabilizado",
  cancelado: "Cancelado",
  rechazado: "Rechazado",
};

const PASO_STEPPER: Record<string, number> = {
  solicitado: 0,
  aprobado: 1,
  con_anticipo: 2,
  en_comision: 3,
  pendiente_liquidacion: 4,
  liquidado: 5,
  contabilizado: 6,
  cancelado: 0,
  rechazado: 0,
};

function fmt(f?: string | null): string {
  if (!f) return "—";
  const dt = new Date(f.replace(/-/g, "/"));
  if (isNaN(dt.getTime())) return "—";
  return dt.toLocaleString("es-EC", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return "—";
  return `$${Number(v).toFixed(2)}`;
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

  const [exteriorModalAbierto, { open: abrirExterior, close: cerrarExterior }] =
    useDisclosure(false);

  const estadoActual = d?.estado ?? "";
  const pasoActivo = PASO_STEPPER[estadoActual] ?? 0;
  const puedeEditarDatos = !["liquidado", "contabilizado"].includes(
    estadoActual,
  );
  const puedeEditarTramos = !["liquidado", "contabilizado"].includes(
    estadoActual,
  );

  const servidor = d?.servidor;
  const nombreCompleto = [servidor?.nombre, servidor?.apellido]
    .filter(Boolean)
    .join(" ");

  if (isLoading) {
    return (
      <Stack gap="md" p="md">
        {/* Header skeleton */}
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

        {/* Stepper skeleton */}
        <Skeleton height={60} radius="md" />

        {/* Grid de secciones */}
        <Grid>
          {/* Información general */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={150} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="xs">
                {[120, 90, 110, 100, 130].map((w, i) => (
                  <Group key={i} justify="space-between">
                    <Skeleton height={12} width={80} radius="sm" />
                    <Skeleton height={12} width={w} radius="sm" />
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>

          {/* Anticipo y monto */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={130} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="xs">
                {[100, 80, 110].map((w, i) => (
                  <Group key={i} justify="space-between">
                    <Skeleton height={12} width={70} radius="sm" />
                    <Skeleton height={12} width={w} radius="sm" />
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>

          {/* Servidores en comisión */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={170} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="xs">
                {[1, 2].map((i) => (
                  <Group key={i} gap="xs">
                    <Skeleton height={32} width={32} radius="xl" />
                    <Stack gap={4}>
                      <Skeleton height={12} width={140} radius="sm" />
                      <Skeleton height={10} width={100} radius="sm" />
                    </Stack>
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>

          {/* Itinerario del viaje */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={140} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="sm">
                {[1, 2].map((i) => (
                  <Group key={i} gap="xs">
                    <Skeleton height={24} width={24} radius="xl" />
                    <Stack gap={4} style={{ flex: 1 }}>
                      <Skeleton height={12} width="70%" radius="sm" />
                      <Skeleton height={10} width="50%" radius="sm" />
                    </Stack>
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>

          {/* Informe de actividades */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={170} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="xs">
                {[1, 2, 3].map((i) => (
                  <Group key={i} gap="xs">
                    <Skeleton height={14} width={14} radius="xl" />
                    <Skeleton height={11} width={180} radius="sm" />
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>

          {/* Facturas de respaldo */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Card withBorder radius="md" p="md">
              <Group mb="sm">
                <Skeleton height={20} width={20} radius="xl" />
                <Skeleton height={16} width={160} radius="sm" />
              </Group>
              <Divider mb="sm" />
              <Stack gap="xs">
                {[1, 2].map((i) => (
                  <Group key={i} justify="space-between">
                    <Skeleton height={11} width={150} radius="sm" />
                    <Skeleton height={11} width={60} radius="sm" />
                  </Group>
                ))}
              </Stack>
            </Card>
          </Grid.Col>
        </Grid>

        {/* Botón acción skeleton */}
        <Skeleton height={44} radius="md" />
      </Stack>
    );
  }

  if (!d) {
    return (
      <Stack p="md">
        <Text c="dimmed">No se encontró el viático.</Text>
        <Button
          variant="default"
          leftSection={<IconArrowLeft size={14} />}
          onClick={() => router.push("/viaticos")}
        >
          Volver
        </Button>
      </Stack>
    );
  }

  return (
    <Stack gap="md" p="md">
      {/* ── Header ── */}
      <Group justify="space-between">
        <Group gap="sm">
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            onClick={() => router.push("/viaticos")}
          >
            <IconArrowLeft size={18} />
          </ActionIcon>
          <div>
            <Group gap="xs">
              <Text fw={700} size="lg">
                {d.codigo_viatico ?? `Viático #${d.id}`}
              </Text>
              <Badge
                color={ESTADO_COLORS[estadoActual] ?? "gray"}
                variant="light"
                size="md"
              >
                {ESTADO_LABELS[estadoActual] ?? estadoActual}
              </Badge>

              <Group gap="xs" ml="auto">
                <Button
                  size="xs"
                  variant="light"
                  color="blue"
                  leftSection={<IconFileText size={12} />}
                  loading={loadingSolicitud}
                  onClick={() => descargarSolicitud(d.codigo_viatico ?? d.id)}
                >
                  Solicitud PDF
                </Button>

                {(estadoActual === "pendiente_liquidacion" ||
                  estadoActual === "liquidado" ||
                  estadoActual === "contabilizado") && (
                  <Button
                    size="xs"
                    variant="light"
                    color="emerald"
                    leftSection={<IconDownload size={12} />}
                    loading={loadingInforme}
                    onClick={() => descargarInforme(d.codigo_viatico ?? d.id)}
                  >
                    Informe PDF
                  </Button>
                )}

                {estadoActual === "contabilizado" && (
                  <Button
                    size="xs"
                    variant="light"
                    color="violet"
                    leftSection={<IconReceipt size={12} />}
                    loading={loadingComprobante}
                    onClick={() =>
                      descargarComprobante(d.codigo_viatico ?? d.id)
                    }
                  >
                    Comprobante Financiero
                  </Button>
                )}
              </Group>
            </Group>
            <Text size="xs" c="dimmed">
              Solicitud del{" "}
              {d.fecha_solicitud
                ? new Date(
                    d.fecha_solicitud.replace(/-/g, "/"),
                  ).toLocaleDateString("es-EC", {
                    day: "2-digit",
                    month: "long",
                    year: "numeric",
                  })
                : "—"}
            </Text>
          </div>
        </Group>
      </Group>

      {/* ── Stepper ── */}
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

      {/* ── Grid de secciones ── */}
      <Grid>
        {/* SECCIÓN 1 — Información general */}
        <Grid.Col span={{ base: 12, md: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon variant="default" size="sm">
                  <IconClipboardList size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Información general
                </Text>
              </Group>
              {puedeEditarDatos && (
                <Button
                  size="xs"
                  variant="light"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirEdit}
                >
                  Gestionar
                </Button>
              )}
            </Group>
            <Divider mb="sm" />
            <Stack gap="xs">
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Servidor
                </Text>
                <Text size="sm" fw={500}>
                  {nombreCompleto || "—"}
                </Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Cargo
                </Text>
                <Text size="sm">{servidor?.puesto?.cargo?.nombre ?? "—"}</Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Unidad
                </Text>
                <Text size="sm">
                  {servidor?.puesto?.unidad_administrativa?.nombre ?? "—"}
                </Text>
              </Group>
              <Divider />
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Zona
                </Text>
                <Text size="sm" fw={500}>
                  {{
                    dentro_provincia: "Dentro de la provincia",
                    fuera_provincia: "Fuera de la provincia",
                    exterior: "Exterior",
                  }[d.zona ?? ""] ?? d.zona}
                </Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Salida
                </Text>
                <Text size="sm">{fmt(d.datetime_salida)}</Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Regreso
                </Text>
                <Text size="sm">{fmt(d.datetime_llegada)}</Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Total días
                </Text>
                <Badge color="blue" variant="light" size="sm">
                  {Number(d.total_dias ?? 0).toFixed(1)} días
                </Badge>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Justificación
                </Text>
              </Group>
              <Text size="sm" c="dimmed">
                {d.justificacion ?? "—"}
              </Text>
            </Stack>
          </Card>
        </Grid.Col>

        {/* SECCIÓN 2 — Itinerario */}
        <Grid.Col span={{ base: 12, md: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon variant="default" size="sm">
                  <IconRoute size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Itinerario del viaje
                </Text>
              </Group>
              {puedeEditarTramos && (
                <Button
                  size="xs"
                  variant="light"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirTramos}
                >
                  Gestionar
                </Button>
              )}
            </Group>
            <Divider mb="sm" />
            <TramosList viaticoId={d.id} puedeEditar={false} />
            {!d.datetime_salida && (
              <Alert
                icon={<IconAlertCircle size={14} />}
                color="orange"
                variant="light"
                mt="sm"
              >
                <Text size="xs">
                  Aún no hay tramos registrados. Pulse{" "}
                  <strong>Gestionar</strong> para agregar el itinerario.
                </Text>
              </Alert>
            )}
          </Card>
        </Grid.Col>

        {/* SECCIÓN 3 — Servidores en comisión */}
        <Grid.Col span={{ base: 12, md: 6 }}>
          <Card withBorder radius="md">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon variant="default" size="sm">
                  <IconUsers size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Servidores en comisión
                </Text>
              </Group>
              {puedeEditarDatos && (
                <Button
                  size="xs"
                  variant="light"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirServidores}
                >
                  Editar
                </Button>
              )}
            </Group>
            <Divider mb="sm" />
            <Stack gap="xs">
              {(d.todos_servidores ?? []).length === 0 ? (
                <Text size="sm" c="dimmed">
                  Solo el servidor titular.
                </Text>
              ) : (
                (d.todos_servidores ?? []).map((vs) => (
                  <Group key={vs.id} gap="xs">
                    <Badge
                      size="xs"
                      color={vs.es_titular ? "blue" : "gray"}
                      variant="light"
                    >
                      {vs.es_titular ? "Titular" : "Acompañante"}
                    </Badge>
                    <Text size="sm">
                      {[vs.servidor?.nombre, vs.servidor?.apellido]
                        .filter(Boolean)
                        .join(" ") || "—"}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {vs.servidor?.puesto?.cargo?.nombre ?? ""}
                    </Text>
                  </Group>
                ))
              )}
            </Stack>
          </Card>
        </Grid.Col>

        {/* SECCIÓN 4 — Anticipo */}
        <Grid.Col span={{ base: 12, md: 6 }}>
          <Card withBorder radius="md">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon variant="default" size="sm">
                  <IconCurrencyDollar size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Anticipo y monto
                </Text>
              </Group>
            </Group>
            <Divider mb="sm" />
            <Stack gap="xs">
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Modalidad
                </Text>
                <Badge size="sm" color="orange" variant="light">
                  {{
                    total: "Anticipo total",
                    parcial: "Anticipo parcial",
                    sin_anticipo: "Sin anticipo",
                  }[d.modalidad_anticipo ?? ""] ?? d.modalidad_anticipo}
                </Badge>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Monto calculado
                </Text>
                <Text fw={700} c="emerald" size="md">
                  {fmtMonto(d.monto_calculado)}
                </Text>
              </Group>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Anticipo a entregar
                </Text>
                <Text fw={600} size="sm">
                  {fmtMonto(d.monto_anticipo)}
                </Text>
              </Group>
            </Stack>
          </Card>
        </Grid.Col>

        {/* SECCIÓN 5 — Liquidación (solo si aplica) */}
        {["pendiente_liquidacion", "liquidado", "contabilizado"].includes(
          estadoActual,
        ) && (
          <Grid.Col span={12}>
            <Card withBorder radius="md">
              <Group justify="space-between" mb="sm">
                <Group gap="xs">
                  <ThemeIcon color="emerald" variant="light" size="sm">
                    <IconFileInvoice size={14} />
                  </ThemeIcon>
                  <Text fw={600} size="sm">
                    Liquidación
                  </Text>
                </Group>
              </Group>
              <Divider mb="sm" />
              {estadoActual === "pendiente_liquidacion" ? (
                <LiquidacionSection
                  viatico={d}
                  onSuccess={() => {
                    qc.invalidateQueries({ queryKey: ["viatico"] });
                  }}
                />
              ) : d.liquidacion ? (
                <Stack gap="xs">
                  {(() => {
                    const montoAsignado =
                      Number(d.monto_calculado ?? 0)
                    const anticipo =
                      Number(d.monto_anticipo ?? 0)
                    const monto70 =
                      Math.round(montoAsignado * 0.70 * 100) / 100
                    const monto30 =
                      Math.round(montoAsignado * 0.30 * 100) / 100
                    const modalidad =
                      (d.modalidad_anticipo as string)
                      ?? 'sin_anticipo'

                    // Separar facturas por grupo
                    type FacturaConCategoria = {
                      monto?: number | string | null
                      categoria?: { grupo?: string } | null
                    }
                    const facturas: FacturaConCategoria[] =
                      (d.liquidacion.detalles_factura ?? []) as
                        FacturaConCategoria[]
                    const totalHospAli = facturas
                      .filter((f) =>
                        f.categoria?.grupo === 'viatico'
                      )
                      .reduce((sum, f) =>
                        sum + Number(f.monto ?? 0), 0
                      )
                    const totalMovilizacion = facturas
                      .filter((f) =>
                        f.categoria?.grupo !== 'viatico'
                      )
                      .reduce((sum, f) =>
                        sum + Number(f.monto ?? 0), 0
                      )

                    const porcentajeHA = monto70 > 0
                      ? Math.min(
                          Math.round(
                            (totalHospAli / monto70) * 100
                          ), 100
                        )
                      : 0
                    const justificadoCompleto =
                      totalHospAli >= monto70

                    const diferenciaDevolver =
                      modalidad === 'sin_anticipo'
                        ? 0
                        : totalHospAli >= anticipo
                          ? 0
                          : Math.round(
                              (anticipo - totalHospAli) * 100
                            ) / 100

                    return (
                      <>
                        {/* Viático H&A */}
                        <Text size="xs" fw={700} c="blue" mb={4}>
                          Viático diario — H&A
                        </Text>
                        <Group justify="space-between">
                          <Text size="xs" c="dimmed">
                            Monto asignado
                          </Text>
                          <Text size="xs" fw={600}>
                            {fmtMonto(montoAsignado)}
                          </Text>
                        </Group>
                        <Group justify="space-between">
                          <Text size="xs" c="dimmed">
                            70% a justificar (H&A)
                          </Text>
                          <Text size="xs" fw={600}>
                            {fmtMonto(monto70)}
                          </Text>
                        </Group>
                        {anticipo > 0 && (
                          <Group justify="space-between">
                            <Text size="xs" c="dimmed">
                              Anticipo entregado
                            </Text>
                            <Text size="xs" fw={600}>
                              {fmtMonto(anticipo)}
                            </Text>
                          </Group>
                        )}
                        <Group justify="space-between">
                          <Text size="xs" c="dimmed">
                            Total H&A presentado
                          </Text>
                          <Text
                            size="xs"
                            fw={700}
                            c={justificadoCompleto
                              ? 'teal' : 'orange'}
                          >
                            {fmtMonto(totalHospAli)}
                            {' '}({porcentajeHA}%)
                          </Text>
                        </Group>
                        <Group justify="space-between">
                          <Text size="xs" c="dimmed">
                            30% devengado
                          </Text>
                          <Text size="xs" fw={600}>
                            {fmtMonto(monto30)}
                          </Text>
                        </Group>
                        <Divider my={4} />
                        <Group justify="space-between">
                          <Text size="xs" fw={600}>
                            A devolver a la institución
                          </Text>
                          <Text
                            size="xs"
                            fw={700}
                            c={diferenciaDevolver > 0
                              ? 'red' : 'teal'}
                          >
                            {fmtMonto(diferenciaDevolver)}
                          </Text>
                        </Group>
                        {diferenciaDevolver > 0 && (
                          <Alert
                            color="red"
                            variant="light"
                            p="xs"
                            mt={4}
                          >
                            <Text size="xs">
                              Faltan{' '}
                              <strong>
                                {fmtMonto(diferenciaDevolver)}
                              </strong>
                              {' '}en H&A por justificar.
                            </Text>
                          </Alert>
                        )}
                        {justificadoCompleto && (
                          <Alert
                            color="teal"
                            variant="light"
                            p="xs"
                            mt={4}
                          >
                            <Text size="xs">
                              Justificación completa del 70%.
                              Devengado: {fmtMonto(monto30)}
                            </Text>
                          </Alert>
                        )}

                        {/* Movilización */}
                        {totalMovilizacion > 0 && (
                          <>
                            <Divider
                              my={6}
                              label="Movilización"
                              labelPosition="left"
                            />
                            <Group justify="space-between">
                              <Text size="xs" c="dimmed">
                                Total movilización
                              </Text>
                              <Text
                                size="xs"
                                fw={600}
                                c="orange"
                              >
                                {fmtMonto(totalMovilizacion)}
                              </Text>
                            </Group>
                            <Text size="xs" c="dimmed">
                              Rubro independiente —
                              no afecta el viático diario
                            </Text>
                          </>
                        )}
                      </>
                    )
                  })()}
                  <Divider />
                  {(d.liquidacion.actividades?.length ?? 0) > 0 && (
                    <Stack gap={4}>
                      <Text size="xs" fw={600} c="dimmed">
                        ACTIVIDADES REALIZADAS
                      </Text>
                      {d.liquidacion.actividades!.map((a, i) => (
                        <Group key={i} gap="xs">
                          <ThemeIcon
                            size="xs"
                            color="blue"
                            variant="light"
                            radius="xl"
                          >
                            <IconChecks size={8} />
                          </ThemeIcon>
                          <Text size="xs">
                            {a.fecha
                              ? new Date(a.fecha).toLocaleDateString("es-EC", {
                                  timeZone: "UTC",
                                  day: "2-digit",
                                  month: "2-digit",
                                })
                              : "—"}
                            {" — "}
                            {a.lugar}
                          </Text>
                        </Group>
                      ))}
                    </Stack>
                  )}
                  {(d.liquidacion.detalles_factura?.length ?? 0) > 0 && (
                    <Stack gap={4}>
                      <Text size="xs" fw={600} c="dimmed">
                        COMPROBANTES
                      </Text>
                      {d.liquidacion.detalles_factura!.map((f, i) => (
                        <Group key={i} justify="space-between">
                          <Text size="xs" style={{ flex: 1 }}>
                            {f.nombre_proveedor ?? "—"}
                          </Text>
                          <Text size="xs" fw={600} c="orange">
                            ${Number(f.monto ?? 0).toFixed(2)}
                          </Text>
                        </Group>
                      ))}
                    </Stack>
                  )}
                </Stack>
              ) : (
                <Text size="sm" c="dimmed">
                  Pendiente de registrar la liquidación.
                </Text>
              )}
            </Card>
          </Grid.Col>
        )}
      </Grid>

      {/* ── Botón acción principal ── */}
      <Card withBorder radius="md" p="sm">
        {estadoActual === "solicitado" && (
          <Stack gap="xs">
            <Button
              color="emerald"
              variant="filled"
              size="md"
              leftSection={<IconChecks size={18} />}
              loading={aprobar.isPending}
              onClick={() => {
                if (d.zona === "exterior") {
                  abrirExterior();
                } else {
                  aprobar.mutate({ id: d.id });
                }
              }}
              fullWidth
            >
              Aprobar solicitud de viático
            </Button>
            <Button
              size="sm"
              variant="light"
              color="red"
              leftSection={<IconX size={14} />}
              loading={cancelar.isPending}
              onClick={() => {
                if (confirm("¿Cancelar esta solicitud?")) {
                  cancelar.mutate(d.id);
                }
              }}
              fullWidth
            >
              Cancelar solicitud
            </Button>
          </Stack>
        )}
        {estadoActual === "aprobado" && (
          <Card withBorder radius="md" p="sm">
            <Stack gap="xs">
              <Text size="sm" fw={600}>
                Viático aprobado
              </Text>
              <Text size="xs" c="dimmed">
                {d.modalidad_anticipo === "sin_anticipo"
                  ? "Sin anticipo — marcar directamente en comisión"
                  : "Proceder con la entrega del anticipo"}
              </Text>
              {d.modalidad_anticipo === "sin_anticipo" ? (
                <Button
                  color="teal"
                  size="sm"
                  loading={marcarEnComision.isPending}
                  onClick={() => marcarEnComision.mutate(d.id)}
                >
                  Marcar en Comisión
                </Button>
              ) : (
                <Button
                  color="blue"
                  size="sm"
                  loading={entregarAnticipo.isPending}
                  onClick={() => entregarAnticipo.mutate(d.id)}
                >
                  Entregar Anticipo
                </Button>
              )}
              {/* Solo visible para gestores */}
              <Button
                size="xs"
                variant="subtle"
                color="red"
                leftSection={<IconBan size={12} />}
                loading={rechazar.isPending}
                onClick={() => {
                  if (confirm("¿Rechazar este viático?")) {
                    rechazar.mutate(d.id);
                  }
                }}
              >
                Rechazar
              </Button>
            </Stack>
          </Card>
        )}
        {estadoActual === "con_anticipo" && (
          <Stack gap="xs">
            <Button
              color="violet"
              variant="filled"
              size="md"
              leftSection={<IconPlane size={18} />}
              loading={marcarEnComision.isPending}
              onClick={() => {
                marcarEnComision.mutate(d.id);
                qc.invalidateQueries({ queryKey: ["viatico"] });
              }}
              fullWidth
            >
              El servidor está en comisión
            </Button>
            {/* Solo visible para gestores */}
            <Button
              size="xs"
              variant="subtle"
              color="red"
              leftSection={<IconBan size={12} />}
              loading={rechazar.isPending}
              onClick={() => {
                if (confirm("¿Rechazar este viático?")) {
                  rechazar.mutate(d.id);
                }
              }}
            >
              Rechazar
            </Button>
          </Stack>
        )}
        {estadoActual === "en_comision" && (
          <Stack gap="xs">
            <Button
              color="yellow"
              variant="filled"
              size="md"
              leftSection={<IconClipboardList size={18} />}
              loading={marcarPendienteLiquidacion.isPending}
              onClick={() => marcarPendienteLiquidacion.mutate(d.id)}
              fullWidth
            >
              Iniciar liquidación
            </Button>
            {/* Solo visible para gestores */}
            <Button
              size="xs"
              variant="subtle"
              color="red"
              leftSection={<IconBan size={12} />}
              loading={rechazar.isPending}
              onClick={() => {
                if (confirm("¿Rechazar este viático?")) {
                  rechazar.mutate(d.id);
                }
              }}
            >
              Rechazar
            </Button>
          </Stack>
        )}
        {estadoActual === "liquidado" && (
          <Button
            color="gray"
            variant="filled"
            size="md"
            leftSection={<IconCircleCheck size={18} />}
            loading={contabilizar.isPending}
            onClick={() => {
              contabilizar.mutate(d.id);
              qc.invalidateQueries({ queryKey: ["viatico"] });
            }}
            fullWidth
          >
            Contabilizar y cerrar viático
          </Button>
        )}
        {estadoActual === "contabilizado" && (
          <Alert
            icon={<IconCircleCheck size={14} />}
            color="gray"
            variant="light"
          >
            <Text size="sm">Este viático está contabilizado y cerrado.</Text>
          </Alert>
        )}
      </Card>

      {/* ── Modales de edición ── */}

      {/* Modal editar información general */}
      <ViaticoEditModal
        opened={editModalAbierto}
        onClose={cerrarEdit}
        viatico={d}
      />

      {/* Modal gestionar tramos */}
      <Modal
        opened={tramosAbierto}
        onClose={cerrarTramos}
        title="Gestionar itinerario del viaje"
        size="xl"
        radius="xl"
      >
        <Stack gap="sm">
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="blue"
            variant="light"
          >
            <Text size="xs">
              El primer tramo debe salir el{" "}
              <strong>{fmt(d.datetime_salida)}</strong> y el último tramo debe
              llegar el <strong>{fmt(d.datetime_llegada)}</strong>.
            </Text>
          </Alert>

          <TramosList viaticoId={d.id} puedeEditar={true} />

          {!mostrarTramoForm ? (
            <Button
              variant="light"
              color="blue"
              leftSection={<IconRoute size={14} />}
              onClick={() => setMostrarTramoForm(true)}
            >
              Agregar tramo al itinerario
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

      <ServidoresModal
        opened={servidoresModalAbierto}
        onClose={cerrarServidores}
        viatico={d as Viatico}
      />


      {exteriorModalAbierto && (
        <AprobarExteriorModal
          opened={exteriorModalAbierto}
          onClose={cerrarExterior}
          viatico={d as Viatico}
        />
      )}
    </Stack>
  );
}
