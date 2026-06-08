"use client";

import { useState } from "react";
import {
  Drawer,
  Stack,
  Tabs,
  Text,
  Badge,
  Group,
  Grid,
  Card,
  Skeleton,
  Button,
  Stepper,
  Alert,
} from "@mantine/core";
import {
  IconPlane,
  IconRoute,
  IconCheck,
  IconCurrencyDollar,
  IconPlus,
  IconAlertCircle,
  IconCircleCheck,
  IconClipboardList,
} from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useViatico } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { TramosList } from "./TramosList";
import { TramoForm } from "./TramoForm";
import { LiquidacionSection } from "./LiquidacionSection";
import type { Viatico, ViaticoConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico | null;
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

// Paso activo del Stepper según estado
const PASO_STEPPER: Record<string, number> = {
  solicitado: 0,
  aprobado: 1,
  con_anticipo: 2,
  en_comision: 3,
  pendiente_liquidacion: 4,
  liquidado: 5,
  contabilizado: 6,
};

function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return "—";
  return `$${Number(v).toFixed(2)}`;
}

function formatDateTime(f?: string | null): string {
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

export function ViaticoDetalle({ opened, onClose, viatico }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const [mostrarForm, setMostrarForm] = useState(false);
  const [tabActiva, setTabActiva] = useState("itinerario");

  const { data: detalle, isLoading } = useViatico(
    opened ? (viatico?.id ?? null) : null,
  );

  const {
    aprobar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
  } = useViaticoMutations();

  const d = detalle as ViaticoConRelaciones | undefined;
  const estadoActual = (d?.estado ?? viatico?.estado ?? "") as string;
  const pasoActivo = PASO_STEPPER[estadoActual] ?? 0;

  const puedeAgregarTramos = ["solicitado", "aprobado"].includes(estadoActual);

  const sinTramos = !d?.datetime_salida && estadoActual === "solicitado";

  // Cambiar a tab liquidación automáticamente
  const handleLiquidarClick = () => {
    setTabActiva("liquidacion");
    marcarPendienteLiquidacion.mutate(d!.id);
  };

  return (
    <Drawer
      opened={opened}
      onClose={() => {
        setMostrarForm(false);
        setTabActiva("itinerario");
        onClose();
      }}
      title={
        <Group gap="xs">
          <Text fw={600} size="sm">
            {d?.codigo_viatico ?? viatico?.codigo_viatico ?? "..."}
          </Text>
          <Badge
            color={ESTADO_COLORS[estadoActual] ?? "gray"}
            variant="light"
            size="sm"
          >
            {ESTADO_LABELS[estadoActual] ?? estadoActual}
          </Badge>
        </Group>
      }
      position="right"
      size={isMobile ? "100%" : "50rem"}
    >
      {isLoading ? (
        <Stack gap="sm" p="md">
          <Skeleton height={60} radius="md" />
          <Skeleton height={100} radius="md" />
          <Skeleton height={200} radius="md" />
        </Stack>
      ) : !d ? (
        <Text c="dimmed" size="sm" p="md">
          No se pudo cargar el detalle.
        </Text>
      ) : (
        <Stack gap="md" p="md">
          {/* ── Stepper de progreso ── */}
          <Stepper
            active={pasoActivo}
            size="xs"
            color="emerald"
            styles={{
              stepBody: { display: "none" },
              separator: { margin: "0 4px" },
            }}
          >
            <Stepper.Step label="Solicitud" />
            <Stepper.Step label="Aprobado" />
            <Stepper.Step label="Anticipo" />
            <Stepper.Step label="Comisión" />
            <Stepper.Step label="Liquidar" />
            <Stepper.Step label="Liquidado" />
            <Stepper.Step label="Contabilizado" />
          </Stepper>

          {/* ── Alerta si no hay tramos ── */}
          {sinTramos && (
            <Alert
              icon={<IconAlertCircle size={14} />}
              color="orange"
              variant="light"
            >
              <Text size="xs" fw={500}>
                Falta registrar el itinerario
              </Text>
              <Text size="xs" mt={2}>
                Para completar tu solicitud debes agregar los tramos del viaje
                (ida y vuelta) en la pestaña <strong>Itinerario</strong> que
                está debajo.
              </Text>
            </Alert>
          )}

          {/* ── Resumen compacto ── */}
          <Card withBorder radius="md" p="sm">
            <Grid>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Zona
                </Text>
                <Text size="sm" fw={500}>
                  {{
                    dentro_provincia: "Dentro de la provincia",
                    fuera_provincia: "Fuera de la provincia",
                    exterior: "Exterior",
                  }[d.zona as string] ?? (d.zona as string)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Total días
                </Text>
                <Text size="sm" fw={600}>
                  {d.total_dias
                    ? `${Number(d.total_dias).toFixed(1)} días`
                    : "Pendiente itinerario"}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Salida
                </Text>
                <Text size="sm">
                  {d.datetime_salida
                    ? formatDateTime(d.datetime_salida as string)
                    : "Se calcula del itinerario"}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Llegada
                </Text>
                <Text size="sm">
                  {d.datetime_llegada
                    ? formatDateTime(d.datetime_llegada as string)
                    : "Se calcula del itinerario"}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Monto
                </Text>
                <Text size="sm" fw={600} c="emerald">
                  {formatMonto(d.monto_calculado)}
                </Text>
              </Grid.Col>
              <Grid.Col span={6}>
                <Text size="xs" c="dimmed">
                  Anticipo
                </Text>
                <Text size="sm" fw={500}>
                  {formatMonto(d.monto_anticipo)}
                </Text>
              </Grid.Col>
              {d.justificacion && (
                <Grid.Col span={12}>
                  <Text size="xs" c="dimmed">
                    Justificación
                  </Text>
                  <Text size="sm" lineClamp={3}>
                    {d.justificacion as string}
                  </Text>
                </Grid.Col>
              )}
            </Grid>
          </Card>

          {/* ── Tabs principales ── */}
          <Tabs
            value={tabActiva}
            onChange={(v) => setTabActiva(v ?? "itinerario")}
          >
            <Tabs.List>
              <Tabs.Tab
                value="itinerario"
                leftSection={<IconRoute size={14} />}
              >
                Itinerario
                {sinTramos && (
                  <Badge size="xs" color="orange" variant="filled" ml={4}>
                    !
                  </Badge>
                )}
              </Tabs.Tab>
              {(estadoActual === "pendiente_liquidacion" ||
                estadoActual === "liquidado" ||
                estadoActual === "contabilizado") && (
                <Tabs.Tab
                  value="liquidacion"
                  leftSection={
                    estadoActual === "pendiente_liquidacion" ? (
                      <IconCurrencyDollar size={14} />
                    ) : (
                      <IconCircleCheck size={14} />
                    )
                  }
                >
                  {estadoActual === "pendiente_liquidacion"
                    ? "Liquidar"
                    : "Liquidación"}
                </Tabs.Tab>
              )}
            </Tabs.List>

            {/* Tab Itinerario */}
            <Tabs.Panel value="itinerario" pt="md">
              <Stack gap="sm">
                <TramosList viaticoId={d.id} puedeEditar={puedeAgregarTramos} />

                {puedeAgregarTramos && (
                  <>
                    <Button
                      size="sm"
                      variant={mostrarForm ? "default" : "light"}
                      color="blue"
                      leftSection={<IconPlus size={14} />}
                      onClick={() => setMostrarForm((v) => !v)}
                    >
                      {mostrarForm ? "Cancelar" : "Agregar tramo al itinerario"}
                    </Button>

                    {mostrarForm && (
                      <Card withBorder radius="md" p="md">
                        <Text size="sm" fw={600} mb="sm">
                          Nuevo tramo
                        </Text>
                        <TramoForm
                          viaticoId={d.id}
                          onSuccess={() => setMostrarForm(false)}
                          onCancel={() => setMostrarForm(false)}
                        />
                      </Card>
                    )}
                  </>
                )}
              </Stack>
            </Tabs.Panel>

            {/* Tab Liquidación */}
            <Tabs.Panel value="liquidacion" pt="md">
              <LiquidacionSection viatico={d as Viatico} onSuccess={onClose} />
            </Tabs.Panel>
          </Tabs>

          {/* ── Botón de acción contextual al fondo ── */}
          <Stack gap="xs" mt="auto" pt="md">
            {estadoActual === "solicitado" && (
              <Button
                color="emerald"
                variant="filled"
                size="md"
                leftSection={<IconCheck size={16} />}
                loading={aprobar.isPending}
                onClick={() => {
                  aprobar.mutate(d.id);
                  onClose();
                }}
                fullWidth
              >
                Aprobar solicitud de viático
              </Button>
            )}

            {estadoActual === "aprobado" && (
              <Button
                color="cyan"
                variant="filled"
                size="md"
                leftSection={<IconCurrencyDollar size={16} />}
                loading={entregarAnticipo.isPending}
                onClick={() => {
                  entregarAnticipo.mutate(d.id);
                  onClose();
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
                leftSection={<IconPlane size={16} />}
                loading={marcarEnComision.isPending}
                onClick={() => {
                  marcarEnComision.mutate(d.id);
                  onClose();
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
                leftSection={<IconClipboardList size={16} />}
                loading={marcarPendienteLiquidacion.isPending}
                onClick={handleLiquidarClick}
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
                leftSection={<IconCircleCheck size={16} />}
                loading={contabilizar.isPending}
                onClick={() => {
                  contabilizar.mutate(d.id);
                  onClose();
                }}
                fullWidth
              >
                Contabilizar y cerrar
              </Button>
            )}
          </Stack>
        </Stack>
      )}
    </Drawer>
  );
}
