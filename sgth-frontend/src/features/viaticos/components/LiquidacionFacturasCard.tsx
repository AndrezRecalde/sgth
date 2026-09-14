"use client";

import {
  Card,
  Group,
  Text,
  Button,
  Divider,
  Stack,
  Alert,
  ThemeIcon,
} from "@mantine/core";
import {
  IconFileInvoice,
  IconAlertCircle,
  IconPencil,
  IconCircleCheck,
} from "@tabler/icons-react";
import type { FacturaData } from "./FacturasModal";
import { REVISION_LABELS, TONO_REVISION } from "../utils/revisionComprobantes";
import type { CategoriaFactura } from "@/types/api";
import { StatusBadge } from "@/components/ui";

interface Props {
  facturas: FacturaData[];
  categorias: CategoriaFactura[];
  /** Sin estas dos, la tarjeta es de solo lectura. */
  onRegistrar?: () => void;
  onEditar?: () => void;
}

export function LiquidacionFacturasCard({
  facturas,
  categorias,
  onRegistrar,
  onEditar,
}: Props) {
  const categoriaOptions = categorias.map((c) => ({
    value: String(c.id),
    label: c.nombre ?? "",
  }));

  return (
    <Card withBorder radius="md" h="100%">
      <Group justify="space-between" mb="sm">
        <Group gap="xs">
          <ThemeIcon variant="light" size="sm">
            <IconFileInvoice size={14} />
          </ThemeIcon>
          <Text fw={600} size="sm">
            Facturas de respaldo
          </Text>
        </Group>
        {facturas.length > 0 && (
          <StatusBadge>
            {facturas.length}{" "}
            {facturas.length === 1 ? "comprobante" : "comprobantes"}
          </StatusBadge>
        )}
      </Group>
      <Divider mb="sm" />

      {facturas.length === 0 ? (
        <Stack gap="xs" align="center" py="md">
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="amber"
            variant="light"
            w="100%"
          >
            <Text size="xs">
              Debe adjuntar los comprobantes de los gastos realizados.
            </Text>
          </Alert>
          {onRegistrar && (
            <Button
              variant="light"
              size="sm"
              leftSection={<IconFileInvoice size={14} />}
              onClick={onRegistrar}
              fullWidth
            >
              Registrar comprobantes
            </Button>
          )}
        </Stack>
      ) : (
        <Stack gap="xs">
          {facturas.map((f, i) => (
            <Stack key={i} gap={2}>
              <Group gap="xs" justify="space-between">
                <Group gap="xs" style={{ flex: 1 }}>
                  <IconCircleCheck
                    size={14}
                    color="var(--mantine-color-emerald-6)"
                  />
                  <Text size="xs" fw={500}>
                    {f.nombre_proveedor}
                  </Text>
                </Group>
                <Group gap={6}>
                  {/* Tras una devolución, qué aceptó Financiero y qué observó. */}
                  {f.estado_revision && f.estado_revision !== "pendiente" && (
                    <StatusBadge tone={TONO_REVISION[f.estado_revision]} size="xs">
                      {REVISION_LABELS[f.estado_revision]}
                    </StatusBadge>
                  )}
                  <Text size="xs" fw={600} c="amber">
                    ${Number(f.monto).toFixed(2)}
                  </Text>
                </Group>
              </Group>
              {f.estado_revision === "observada" && f.observacion_revision && (
                <Text size="xs" c="red" ml={22}>
                  Observación: {f.observacion_revision}
                </Text>
              )}
              {f.categoria_factura_id > 0 && (
                <Group gap={4} ml={22}>
                  <StatusBadge size="xs" variant="dot">
                    {categoriaOptions.find(
                      (c) => Number(c.value) === f.categoria_factura_id,
                    )?.label ?? `Categoría ${f.categoria_factura_id}`}
                  </StatusBadge>
                  <Text size="xs" c="dimmed">
                    {f.tipo_comprobante
                      ? f.tipo_comprobante.charAt(0).toUpperCase() +
                        f.tipo_comprobante.slice(1)
                      : ""}
                  </Text>
                </Group>
              )}
            </Stack>
          ))}
          {onEditar && (
            <Button
              size="xs"
              variant="subtle"
              leftSection={<IconPencil size={12} />}
              onClick={onEditar}
            >
              Editar comprobantes
            </Button>
          )}
        </Stack>
      )}
    </Card>
  );
}
