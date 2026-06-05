"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
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
  IconCheck,
  IconCurrencyDollar,
  IconPlane,
  IconRoute,
  IconClipboardList,
  IconCircleCheck,
  IconAlertCircle,
  IconUsers,
  IconFileInvoice,
} from "@tabler/icons-react";
import { useViatico } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { TramosList } from "./TramosList";
import { TramoForm } from "./TramoForm";
import { LiquidacionSection } from "./LiquidacionSection";
import { ViaticoEditModal } from "./ViaticoEditModal";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  viaticoId: number;
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
  return new Date(f).toLocaleString("es-EC", {
    timeZone: "UTC",
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

export function ViaticoDetallePage({ viaticoId }: Props) {
  const router = useRouter();
  const { data: detalle, isLoading } = useViatico(viaticoId);
  const d = detalle as ViaticoConRelaciones | undefined;

  const [editModalAbierto, { open: abrirEdit, close: cerrarEdit }] =
    useDisclosure(false);
  const [tramosAbierto, { open: abrirTramos, close: cerrarTramos }] =
    useDisclosure(false);

  const [mostrarTramoForm, setMostrarTramoForm] = useState(false);

  const {
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
  } = useViaticoMutations();

  const estadoActual = d?.estado ?? "";
  const pasoActivo = PASO_STEPPER[estadoActual] ?? 0;
  const esEditable = !["contabilizado"].includes(estadoActual);
  const puedeEditarDatos = ["solicitado"].includes(estadoActual);
  const puedeEditarTramos = ["solicitado", "aprobado"].includes(estadoActual);

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
            </Group>
            <Text size="xs" c="dimmed">
              Solicitud del{" "}
              {d.fecha_solicitud
                ? new Date(d.fecha_solicitud).toLocaleDateString("es-EC", {
                    timeZone: "UTC",
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
                    router.refresh();
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
                      Diferencia a devolver
                    </Text>
                    <Text
                      fw={600}
                      c={
                        Number(d.liquidacion.diferencia_devolver) >= 0
                          ? "orange"
                          : "emerald"
                      }
                    >
                      {fmtMonto(d.liquidacion.diferencia_devolver)}
                    </Text>
                  </Group>
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      Actividades registradas
                    </Text>
                    <Badge size="sm" color="blue" variant="light">
                      {d.liquidacion.actividades?.length ?? 0}
                    </Badge>
                  </Group>
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      Facturas registradas
                    </Text>
                    <Badge size="sm" color="orange" variant="light">
                      {d.liquidacion.detalles_factura?.length ?? 0}
                    </Badge>
                  </Group>
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
            leftSection={<IconCheck size={18} />}
            loading={aprobar.isPending}
            onClick={() => {
              aprobar.mutate(d.id);
              router.push("/viaticos");
            }}
            fullWidth
          >
            ✓ Aprobar solicitud de viático
          </Button>
        )}
        {estadoActual === "aprobado" && (
          <Button
            color="cyan"
            variant="filled"
            size="md"
            leftSection={<IconCurrencyDollar size={18} />}
            loading={entregarAnticipo.isPending}
            onClick={() => {
              entregarAnticipo.mutate(d.id);
              router.push("/viaticos");
            }}
            fullWidth
          >
            Confirmar entrega del anticipo
          </Button>
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
              router.push("/viaticos");
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
            El servidor regresó — iniciar liquidación
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
              router.push("/viaticos");
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


    </Stack>
  );
}
