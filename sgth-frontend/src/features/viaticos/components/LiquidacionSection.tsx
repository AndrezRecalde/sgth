"use client";

import { useMemo } from "react";
import {
  Stack,
  Card,
  Text,
  Group,
  Button,
  Badge,
  Divider,
  Alert,
  ThemeIcon,
  Grid,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconClipboardList,
  IconFileInvoice,
  IconCheck,
  IconAlertCircle,
  IconPencil,
  IconCircleCheck,
} from "@tabler/icons-react";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { ActividadesModal } from "./ActividadesModal";
import { FacturasModal } from "./FacturasModal";
import type { ActividadData } from "./ActividadesModal";
import type { FacturaData } from "./FacturasModal";
import { useCategoriasFactura, useLiquidacion } from "../hooks/useViaticos";
import type { Viatico, CategoriaFactura, ActividadLiquidacion, FacturaViatico } from "@/types/api";

interface Props {
  viatico: Viatico;
  onSuccess: () => void;
}

function fmtMonto(v?: number | string | null): string {
  if (v == null) return "—";
  return `$${Number(v).toFixed(2)}`;
}

export function LiquidacionSection({ viatico, onSuccess }: Props) {
  const { data: categoriasData = [] } = useCategoriasFactura();
  const categoriaOptions = (categoriasData as CategoriaFactura[]).map((c) => ({
    value: String(c.id),
    label: c.nombre ?? "",
  }));

  const [actModalAbierto, { open: abrirAct, close: cerrarAct }] =
    useDisclosure(false);

  const [factModalAbierto, { open: abrirFact, close: cerrarFact }] =
    useDisclosure(false);

  const { confirmarLiquidacion } = useViaticoMutations();

  const { data: liquidacionData } = useLiquidacion(
    viatico.id
  )

  const actividades: ActividadData[] = useMemo(() => {
    if (!liquidacionData?.actividades) return [];
    return liquidacionData.actividades.map((a: ActividadLiquidacion) => ({
      fecha:       a.fecha as string,
      hora_inicio: a.hora_inicio as string ?? '',
      hora_fin:    a.hora_fin as string ?? '',
      descripcion: a.descripcion as string,
      lugar:       a.lugar as string,
    }));
  }, [liquidacionData?.actividades]);

  const facturas: FacturaData[] = useMemo(() => {
    if (!liquidacionData?.detalles_factura) return [];
    return liquidacionData.detalles_factura.map((f: FacturaViatico) => ({
      categoria_factura_id: Number(f.categoria_factura_id),
      fecha_factura:        f.fecha_factura as string ?? '',
      tipo_comprobante:     (f.tipo_comprobante as
        'factura'|'ticket'|'recibo'|'otro') ?? 'factura',
      numero_factura:  f.numero_factura as string ?? '',
      numero_ticket:   f.numero_ticket as string ?? '',
      ruc_proveedor:   f.ruc_proveedor as string ?? '',
      nombre_proveedor: f.nombre_proveedor as string,
      detalle:         f.detalle as string ?? '',
      monto:           Number(f.monto),
    }));
  }, [liquidacionData?.detalles_factura]);

  const montoAsignado = Number(viatico.monto_calculado ?? 0)
  const montoAnticipo = Number(viatico.monto_anticipo ?? 0)
  const monto70       = Math.round(montoAsignado * 0.70 * 100) / 100
  const monto30       = Math.round(montoAsignado * 0.30 * 100) / 100
  const modalidad     = (viatico.modalidad_anticipo as string)
                        ?? 'sin_anticipo'

  // IDs de categorías de viático (H&A)
  const idsViatico = (categoriasData as CategoriaFactura[])
    .filter(c => c.grupo === 'viatico')
    .map(c => Number(c.id))

  const totalHospAli = facturas
    .filter(f => idsViatico.includes(
      Number(f.categoria_factura_id)
    ))
    .reduce((sum, f) => sum + (Number(f.monto) || 0), 0)

  const totalMovilizacion = facturas
    .filter(f => !idsViatico.includes(
      Number(f.categoria_factura_id)
    ))
    .reduce((sum, f) => sum + (Number(f.monto) || 0), 0)

  const totalFacturas = totalHospAli + totalMovilizacion

  const porcentajeHA = monto70 > 0
    ? Math.min(
        Math.round((totalHospAli / monto70) * 100), 100
      )
    : 0

  const justificadoCompleto = totalHospAli >= monto70

  // Diferencia a devolver
  const diferenciaDevolver = modalidad === 'sin_anticipo'
    ? 0
    : (totalHospAli >= montoAnticipo ||
       totalFacturas >= montoAsignado)
      ? 0
      : Math.round((montoAnticipo - totalHospAli) * 100) / 100

  const puedeRegistrar = actividades.length > 0 && facturas.length > 0;

  const handleRegistrar = async () => {
    await confirmarLiquidacion.mutateAsync(viatico.id)
    onSuccess()
  }

  return (
    <Stack gap="md">
      {/* Resumen rápido si ya hay datos */}
      {(actividades.length > 0 || facturas.length > 0) && (
        <Stack gap="sm">
          {/* Resumen viático H&A */}
          <Card withBorder radius="md" p="sm">
            <Text size="xs" fw={700} c="blue" mb="xs">
              Viático diario — Hospedaje y Alimentación
            </Text>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                Monto asignado:
              </Text>
              <Text size="xs" fw={600}>
                ${montoAsignado.toFixed(2)}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                70% a justificar (H&A):
              </Text>
              <Text size="xs" fw={600}>
                ${monto70.toFixed(2)}
              </Text>
            </Group>
            {montoAnticipo > 0 && (
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Anticipo entregado:
                </Text>
                <Text size="xs" fw={600}>
                  ${montoAnticipo.toFixed(2)}
                </Text>
              </Group>
            )}
            <Divider my={4} />
            <Group justify="space-between">
              <Text size="xs" c="dimmed">
                Total H&A presentado:
              </Text>
              <Text
                size="xs"
                fw={700}
                c={justificadoCompleto ? 'teal' : 'orange'}
              >
                ${totalHospAli.toFixed(2)}
                {' '}({porcentajeHA}%)
              </Text>
            </Group>
            {diferenciaDevolver > 0 && (
              <Group justify="space-between" mt={4}>
                <Text size="xs" c="red" fw={600}>
                  A devolver a la institución:
                </Text>
                <Text size="xs" c="red" fw={700}>
                  ${diferenciaDevolver.toFixed(2)}
                </Text>
              </Group>
            )}
            {!justificadoCompleto && diferenciaDevolver === 0
              && modalidad === 'sin_anticipo' && (
              <Alert color="yellow" variant="light" p="xs" mt={4}>
                <Text size="xs">
                  Faltan{' '}
                  <strong>
                    ${(monto70 - totalHospAli).toFixed(2)}
                  </strong>
                  {' '}en H&A. Recibirás solo lo justificado
                  + el 30% devengado (${monto30.toFixed(2)}).
                </Text>
              </Alert>
            )}
            {justificadoCompleto && (
              <Alert color="teal" variant="light" p="xs" mt={4}>
                <Text size="xs">
                  Justificación completa del 70%.
                  Recibirás el 30% devengado adicional
                  (${monto30.toFixed(2)}).
                </Text>
              </Alert>
            )}
          </Card>

          {/* Resumen movilización */}
          {totalMovilizacion > 0 && (
            <Card withBorder radius="md" p="sm">
              <Text size="xs" fw={700} c="orange" mb="xs">
                Movilización (rubro independiente)
              </Text>
              <Group justify="space-between">
                <Text size="xs" c="dimmed">
                  Total movilización:
                </Text>
                <Text size="xs" fw={600} c="orange">
                  ${totalMovilizacion.toFixed(2)}
                </Text>
              </Group>
              <Text size="xs" c="dimmed" mt={4}>
                Se presenta como respaldo adicional.
                No afecta el cálculo del viático diario.
              </Text>
            </Card>
          )}
        </Stack>
      )}

      <Grid>
        {/* Sección actividades */}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon color="blue" variant="light" size="sm">
                  <IconClipboardList size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Informe de actividades
                </Text>
              </Group>
              {actividades.length > 0 && (
                <Badge color="blue" variant="light" size="sm">
                  {actividades.length}{" "}
                  {actividades.length === 1 ? "actividad" : "actividades"}
                </Badge>
              )}
            </Group>
            <Divider mb="sm" />

            {actividades.length === 0 ? (
              <Stack gap="xs" align="center" py="md">
                <Alert
                  icon={<IconAlertCircle size={14} />}
                  color="orange"
                  variant="light"
                  w="100%"
                >
                  <Text size="xs">
                    Debe registrar las actividades realizadas durante la
                    comisión.
                  </Text>
                </Alert>
                <Button
                  color="blue"
                  variant="light"
                  size="sm"
                  leftSection={<IconClipboardList size={14} />}
                  onClick={abrirAct}
                  fullWidth
                >
                  Registrar actividades
                </Button>
              </Stack>
            ) : (
              <Stack gap="xs">
                {actividades.map((a, i) => (
                  <Stack key={i} gap={2}>
                    <Group gap="xs">
                      <IconCircleCheck
                        size={14}
                        color="var(--mantine-color-emerald-6)"
                      />
                      <Text size="xs" fw={500}>
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
                    {a.descripcion && (
                      <Text size="xs" c="dimmed" ml={22}>
                        {a.descripcion}
                      </Text>
                    )}
                  </Stack>
                ))}
                <Button
                  size="xs"
                  variant="subtle"
                  color="blue"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirAct}
                >
                  Editar actividades
                </Button>
              </Stack>
            )}
          </Card>
        </Grid.Col>

        {/* Sección facturas */}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Card withBorder radius="md" h="100%">
            <Group justify="space-between" mb="sm">
              <Group gap="xs">
                <ThemeIcon color="orange" variant="light" size="sm">
                  <IconFileInvoice size={14} />
                </ThemeIcon>
                <Text fw={600} size="sm">
                  Facturas de respaldo
                </Text>
              </Group>
              {facturas.length > 0 && (
                <Badge color="orange" variant="light" size="sm">
                  {facturas.length}{" "}
                  {facturas.length === 1 ? "comprobante" : "comprobantes"}
                </Badge>
              )}
            </Group>
            <Divider mb="sm" />

            {facturas.length === 0 ? (
              <Stack gap="xs" align="center" py="md">
                <Alert
                  icon={<IconAlertCircle size={14} />}
                  color="orange"
                  variant="light"
                  w="100%"
                >
                  <Text size="xs">
                    Debe adjuntar los comprobantes de los gastos realizados.
                  </Text>
                </Alert>
                <Button
                  color="orange"
                  variant="light"
                  size="sm"
                  leftSection={<IconFileInvoice size={14} />}
                  onClick={abrirFact}
                  fullWidth
                >
                  Registrar comprobantes
                </Button>
              </Stack>
            ) : (
              <Stack gap="xs">
                {facturas.map((f, i) => (
                  <Stack key={i} gap={2}>
                    <Group key={i} gap="xs" justify="space-between">
                      <Group gap="xs" style={{ flex: 1 }}>
                        <IconCircleCheck
                          size={14}
                          color="var(--mantine-color-emerald-6)"
                        />
                        <Text size="xs" fw={500}>
                          {f.nombre_proveedor}
                        </Text>
                      </Group>
                      <Text size="xs" fw={600} c="orange">
                        ${Number(f.monto).toFixed(2)}
                      </Text>
                    </Group>
                    {f.categoria_factura_id > 0 && (
                      <Group gap={4} ml={22}>
                        <Badge size="xs" color="orange" variant="dot">
                          {categoriaOptions.find(
                            (c) => Number(c.value) === f.categoria_factura_id,
                          )?.label ?? `Categoría ${f.categoria_factura_id}`}
                        </Badge>
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
                <Button
                  size="xs"
                  variant="subtle"
                  color="orange"
                  leftSection={<IconPencil size={12} />}
                  onClick={abrirFact}
                >
                  Editar comprobantes
                </Button>
              </Stack>
            )}
          </Card>
        </Grid.Col>
      </Grid>

      {/* Validación y botón registrar */}
      {!puedeRegistrar && (
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="gray"
          variant="light"
        >
          <Text size="xs">
            Para registrar la liquidación debe completar tanto el{" "}
            <strong>informe de actividades</strong> como las{" "}
            <strong>facturas de respaldo</strong>.
          </Text>
        </Alert>
      )}

      <Button
        color="emerald"
        size="md"
        disabled={!puedeRegistrar}
        loading={confirmarLiquidacion.isPending}
        leftSection={<IconCheck size={16} />}
        onClick={handleRegistrar}
        fullWidth
      >
        Registrar liquidación
      </Button>

      {/* Modales */}
      <ActividadesModal
        opened={actModalAbierto}
        onClose={cerrarAct}
        viatico={viatico}
        valorInicial={actividades}
      />

      <FacturasModal
        opened={factModalAbierto}
        onClose={cerrarFact}
        viatico={viatico}
        valorInicial={facturas}
      />
    </Stack>
  );
}
