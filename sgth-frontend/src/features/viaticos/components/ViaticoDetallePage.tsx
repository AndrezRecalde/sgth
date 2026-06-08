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
} from "@tabler/icons-react";
import { usePdfViatico } from "../hooks/usePdfViatico";
import { useViatico } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { useQueryClient } from "@tanstack/react-query";
import { TramosList } from "./TramosList";
import { TramoForm } from "./TramoForm";
import { LiquidacionSection } from "./LiquidacionSection";
import { ViaticoEditModal } from "./ViaticoEditModal";
import { ServidoresModal } from "./ServidoresModal";
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
};

const ESTADO_LABELS: Record<string, string> = {
  solicitado: "Solicitado",
  aprobado: "Aprobado",
  con_anticipo: "Con anticipo",
  en_comision: "En comisión",
  pendiente_liquidacion: "Pendiente de liquidación",
  liquidado: "Liquidado",
  contabilizado: "Contabilizado",
};

const PASO_STEPPER: Record<string, number> = {
  solicitado: 0,
  aprobado: 1,
  con_anticipo: 2,
  en_comision: 3,
  pendiente_liquidacion: 4,
  liquidado: 5,
  contabilizado: 6,
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
  const qc = useQueryClient();
  const { data: detalle, isLoading } = useViatico(identificador);
  const d = detalle as ViaticoConRelaciones | undefined;

  const [editModalAbierto, { open: abrirEdit, close: cerrarEdit }] =
    useDisclosure(false);
  const [tramosAbierto, { open: abrirTramos, close: cerrarTramos }] =
    useDisclosure(false);
  const [
    servidoresModalAbierto,
    { open: abrirServidores, close: cerrarServidores },
  ] = useDisclosure(false);

  const [mostrarTramoForm, setMostrarTramoForm] = useState(false);
  const [
    liquidacionModalAbierto,
    { open: abrirLiquidacion, close: cerrarLiquidacion },
  ] = useDisclosure(false);

  const {
    descargarSolicitud,
    descargarInforme,
    loadingSolicitud,
    loadingInforme,
  } = usePdfViatico();

  const {
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
  } = useViaticoMutations();

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
        <Skeleton height={40} />
        <Skeleton height={60} />
        <Grid>
          <Grid.Col span={6}>
            <Skeleton height={160} />
          </Grid.Col>
          <Grid.Col span={6}>
            <Skeleton height={160} />
          </Grid.Col>
          <Grid.Col span={6}>
            <Skeleton height={160} />
          </Grid.Col>
          <Grid.Col span={6}>
            <Skeleton height={160} />
          </Grid.Col>
        </Grid>
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
          onClick={() => qc.invalidateQueries({ queryKey: ["viatico"] })}
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
            onClick={() => qc.invalidateQueries({ queryKey: ["viatico"] })}
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
                <ThemeIcon color="blue" variant="light" size="sm">
                  <IconClipboardList size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Información general
                </Text>
              </Group>
              {puedeEditarDatos && (
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  color="blue"
                  onClick={abrirEdit}
                >
                  <IconPencil size={14} />
                </ActionIcon>
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
                <ThemeIcon color="violet" variant="light" size="sm">
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
                  color="violet"
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
                <ThemeIcon color="teal" variant="light" size="sm">
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
                  color="teal"
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
                <ThemeIcon color="orange" variant="light" size="sm">
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
              {estadoActual === "pendiente_liquidacion" && !d.liquidacion ? (
                <LiquidacionSection
                  viatico={d}
                  onSuccess={() => {
                    qc.invalidateQueries({ queryKey: ["viatico"] });
                  }}
                />
              ) : d.liquidacion ? (
                <Stack gap="xs">
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      Total facturas
                    </Text>
                    <Text fw={600}>
                      {fmtMonto(d.liquidacion.total_facturas)}
                    </Text>
                  </Group>
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      Anticipo recibido
                    </Text>
                    <Text fw={600}>{fmtMonto(d.monto_anticipo)}</Text>
                  </Group>
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      {Number(d.liquidacion.diferencia_devolver) >= 0
                        ? "A devolver"
                        : "A cobrar"}
                    </Text>
                    <Text
                      fw={600}
                      c={
                        Number(d.liquidacion.diferencia_devolver) >= 0
                          ? "orange"
                          : "emerald"
                      }
                    >
                      {fmtMonto(
                        Math.abs(
                          Number(d.liquidacion.diferencia_devolver ?? 0),
                        ),
                      )}
                    </Text>
                  </Group>
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
                  {estadoActual === "pendiente_liquidacion" && (
                    <Button
                      size="xs"
                      variant="subtle"
                      color="blue"
                      leftSection={<IconPencil size={12} />}
                      onClick={abrirLiquidacion}
                    >
                      Modificar liquidación
                    </Button>
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
          <Button
            color="emerald"
            variant="filled"
            size="md"
            leftSection={<IconChecks size={18} />}
            loading={aprobar.isPending}
            onClick={() => {
              aprobar.mutate(d.id);
              qc.invalidateQueries({ queryKey: ["viatico"] });
            }}
            fullWidth
          >
            Aprobar solicitud de viático
          </Button>
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
            </Stack>
          </Card>
        )}
        {estadoActual === "con_anticipo" && (
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
        )}
        {estadoActual === "en_comision" && (
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

      {liquidacionModalAbierto && (
        <Modal
          opened={liquidacionModalAbierto}
          onClose={cerrarLiquidacion}
          title="Modificar liquidación"
          size="xl"
          radius="xl"
        >
          <LiquidacionSection
            viatico={d}
            onSuccess={() => {
              qc.invalidateQueries({ queryKey: ["viatico"] });
              cerrarLiquidacion();
            }}
          />
        </Modal>
      )}
    </Stack>
  );
}
