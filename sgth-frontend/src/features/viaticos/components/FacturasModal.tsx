"use client";

import {
  Modal,
  Stack,
  Card,
  Text,
  Group,
  Button,
  Grid,
  TextInput,
  ActionIcon,
  Divider,
  Badge,
  Alert,
  Select,
  NumberInput,
  ThemeIcon,
} from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import "@mantine/dates/styles.css";
import {
  IconPlus,
  IconTrash,
  IconFileInvoice,
  IconInfoCircle,
  IconCheck,
} from "@tabler/icons-react";
import { useForm, useFieldArray, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useCategoriasFactura } from "../hooks/useViaticos";
import type { Viatico, CategoriaFactura } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
  onGuardar: (facturas: FacturaData[]) => void;
  valorInicial?: FacturaData[];
}

export interface FacturaData {
  categoria_factura_id: number;
  fecha_factura?: string | null;
  tipo_comprobante: "factura" | "ticket" | "recibo" | "otro";
  numero_factura?: string | null;
  numero_ticket?: string | null;
  ruc_proveedor?: string | null;
  nombre_proveedor: string;
  detalle?: string | null;
  monto: number;
}

const facturaItemSchema = z.object({
  categoria_factura_id: z.number().min(1, "Seleccione categoría"),
  fecha_factura: z.string().optional().nullable(),
  tipo_comprobante: z.enum(["factura", "ticket", "recibo", "otro"]),
  numero_factura: z.string().optional().nullable(),
  numero_ticket: z.string().optional().nullable(),
  ruc_proveedor: z.string().optional().nullable(),
  nombre_proveedor: z.string().min(1, "Requerido"),
  detalle: z.string().optional().nullable(),
  monto: z.number().min(0.01, "Mínimo $0.01"),
}).refine(
  (data) => {
    if (["factura", "recibo"].includes(data.tipo_comprobante)) {
      return !!data.ruc_proveedor && data.ruc_proveedor.trim().length > 0;
    }
    return true;
  },
  {
    message: "El RUC es obligatorio para factura y recibo",
    path: ["ruc_proveedor"],
  }
);

const schema = z.object({
  facturas: z.array(facturaItemSchema).min(1, "Agregue al menos una factura"),
});

type FormData = z.infer<typeof schema>;

const toDate = (v?: string | null): Date | null => {
  if (!v) return null;
  const [y, m, d] = v.split("-").map(Number);
  return new Date(y, m - 1, d);
};

const safeFormatDate = (v: Date | string | null | undefined): string => {
  if (!v) return "";
  const d = new Date(v);
  if (isNaN(d.getTime())) return "";

  if (d.getUTCHours() === 0 && d.getUTCMinutes() === 0) {
    return d.toISOString().slice(0, 10);
  }

  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
};

export function FacturasModal({
  opened,
  onClose,
  viatico,
  onGuardar,
  valorInicial = [],
}: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();

  const { data: categorias = [] } = useCategoriasFactura();
  const categoriaOptions = (categorias as CategoriaFactura[]).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));

  // Opción B: facturas hasta 5 días después de llegada
  const minFecha = viatico.datetime_salida
    ? (() => {
        const d = new Date(viatico.datetime_salida as string);
        d.setHours(0, 0, 0, 0);
        return d;
      })()
    : undefined;

  const maxFactura = viatico.datetime_llegada
    ? (() => {
        const d = new Date(viatico.datetime_llegada as string);
        d.setDate(d.getDate() + 5);
        d.setHours(23, 59, 59, 999);
        return d;
      })()
    : undefined;

  const {
    control,
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      facturas:
        valorInicial.length > 0
          ? valorInicial
          : [
              {
                categoria_factura_id: 0,
                fecha_factura: "",
                tipo_comprobante: "factura" as const,
                numero_factura: "",
                numero_ticket: "",
                ruc_proveedor: "",
                nombre_proveedor: "",
                detalle: "",
                monto: 0,
              },
            ],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "facturas",
  });

  const facturasWatch = useWatch({ control, name: "facturas" }) || [];
  const totalFacturas = facturasWatch.reduce(
    (sum, f) => sum + (Number(f.monto) || 0),
    0,
  );
  const anticipo = Number(viatico.monto_anticipo ?? 0);
  const diferencia = anticipo - totalFacturas;

  const onSubmit = (values: FormData) => {
    onGuardar(values.facturas);
    onClose();
  };

  const formatFecha = (f?: string | null) => {
    if (!f) return "—";
    return new Date(f).toLocaleDateString("es-EC", {
      timeZone: "UTC",
      day: "2-digit",
      month: "long",
      year: "numeric",
    });
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="orange" variant="light" size="sm">
            <IconFileInvoice size={14} />
          </ThemeIcon>
          <Text fw={600}>Facturas de respaldo</Text>
        </Group>
      }
      size="xl"
      radius="xl"
      fullScreen={isMobile}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">
          {/* Resumen financiero */}
          <Card withBorder radius="md" p="sm" bg="gray.0">
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Anticipo recibido:
              </Text>
              <Text size="sm" fw={600}>
                ${anticipo.toFixed(2)}
              </Text>
            </Group>
            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                Total comprobantes:
              </Text>
              <Text size="sm" fw={600} c="blue">
                ${totalFacturas.toFixed(2)}
              </Text>
            </Group>
            <Divider my={4} />
            <Group justify="space-between">
              <Text size="sm" fw={600}>
                A devolver a la institución:
              </Text>
              <Text
                size="sm"
                fw={700}
                c={diferencia >= 0 ? 'orange' : 'gray'}
              >
                {diferencia >= 0
                  ? `$${diferencia.toFixed(2)}`
                  : '$0.00'}
              </Text>
            </Group>
            {diferencia < 0 && (
              <Alert color="orange" variant="light" p="xs">
                <Text size="xs">
                  ⚠️ Los comprobantes superan el anticipo.
                  La diferencia de{' '}
                  <strong>
                    ${Math.abs(diferencia).toFixed(2)}
                  </strong>
                  {' '}es responsabilidad del servidor.
                </Text>
              </Alert>
            )}
          </Card>

          {/* Alerta de rango */}
          <Alert
            icon={<IconInfoCircle size={14} />}
            color="orange"
            variant="light"
          >
            <Text size="xs" fw={500}>
              Período válido para comprobantes
            </Text>
            <Text size="xs" mt={2}>
              Desde el{" "}
              <strong>{formatFecha(viatico.datetime_salida as string)}</strong>{" "}
              hasta 5 días después del regreso:{" "}
              <strong>
                {maxFactura?.toLocaleDateString("es-EC", {
                  day: "2-digit",
                  month: "long",
                  year: "numeric",
                })}
              </strong>
              .
            </Text>
          </Alert>

          {/* Lista de facturas */}
          <Stack gap="sm">
            {fields.map((field, i) => {
              const tipoComp = facturasWatch[i]?.tipo_comprobante ?? "factura";

              return (
                <Card key={field.id} withBorder radius="md" p="sm">
                  <Group justify="space-between" mb="xs">
                    <Group gap="xs">
                      <Badge size="sm" color="orange" variant="light" circle>
                        {i + 1}
                      </Badge>
                      <Text size="sm" fw={600}>
                        Comprobante {i + 1}
                      </Text>
                    </Group>
                    {fields.length > 1 && (
                      <ActionIcon
                        size="sm"
                        color="red"
                        variant="subtle"
                        onClick={() => remove(i)}
                      >
                        <IconTrash size={13} />
                      </ActionIcon>
                    )}
                  </Group>

                  <Grid>
                    {/* Categoría */}
                    <Grid.Col span={{ base: 12, sm: 4 }}>
                      <Controller
                        name={`facturas.${i}.categoria_factura_id`}
                        control={control}
                        render={({ field: f }) => (
                          <Select
                            label="Categoría"
                            placeholder="Seleccionar"
                            data={categoriaOptions}
                            searchable
                            {...contained}
                            value={f.value ? String(f.value) : null}
                            onChange={(v) => f.onChange(v ? Number(v) : 0)}
                            error={
                              errors.facturas?.[i]?.categoria_factura_id
                                ?.message
                            }
                          />
                        )}
                      />
                    </Grid.Col>

                    {/* Tipo comprobante */}
                    <Grid.Col span={{ base: 12, sm: 4 }}>
                      <Controller
                        name={`facturas.${i}.tipo_comprobante`}
                        control={control}
                        render={({ field: f }) => (
                          <Select
                            label="Tipo de comprobante"
                            data={[
                              { value: "factura", label: "🧾 Factura" },
                              { value: "ticket", label: "🎫 Ticket / Pasaje" },
                              { value: "recibo", label: "📄 Recibo" },
                              { value: "otro", label: "📎 Otro" },
                            ]}
                            {...contained}
                            value={f.value}
                            onChange={(v) => f.onChange(v ?? "factura")}
                            error={
                              errors.facturas?.[i]?.tipo_comprobante?.message
                            }
                          />
                        )}
                      />
                    </Grid.Col>

                    {/* Fecha del comprobante */}
                    <Grid.Col span={{ base: 12, sm: 4 }}>
                      <Controller
                        name={`facturas.${i}.fecha_factura`}
                        control={control}
                        render={({ field: f }) => (
                          <DatePickerInput
                            label="Fecha del comprobante"
                            placeholder="Seleccionar"
                            valueFormat="DD/MM/YYYY"
                            clearable
                            minDate={minFecha}
                            maxDate={maxFactura}
                            popoverProps={{ withinPortal: true }}
                            {...contained}
                            value={toDate(f.value)}
                            onChange={(v) => f.onChange(safeFormatDate(v))}
                          />
                        )}
                      />
                    </Grid.Col>

                    {/* N° documento condicional */}
                    <Grid.Col span={{ base: 12, sm: 6 }}>
                      {tipoComp === "ticket" ? (
                        <TextInput
                          label="N° Ticket / Pasaje"
                          placeholder="Ej: T-001234"
                          {...contained}
                          {...register(`facturas.${i}.numero_ticket`)}
                          error={errors.facturas?.[i]?.numero_ticket?.message}
                        />
                      ) : (
                        <TextInput
                          label="N° Factura"
                          placeholder="001-001-000000001"
                          {...contained}
                          {...register(`facturas.${i}.numero_factura`)}
                          error={errors.facturas?.[i]?.numero_factura?.message}
                        />
                      )}
                    </Grid.Col>

                    {/* RUC */}
                    <Grid.Col span={{ base: 12, sm: 6 }}>
                      <TextInput
                        label={
                          ["factura", "recibo"].includes(tipoComp)
                            ? "RUC del proveedor *"
                            : "RUC / Identificación (opcional)"
                        }
                        placeholder="0000000000001"
                        required={["factura", "recibo"].includes(tipoComp)}
                        {...contained}
                        {...register(`facturas.${i}.ruc_proveedor`)}
                        error={errors.facturas?.[i]?.ruc_proveedor?.message}
                      />
                    </Grid.Col>

                    {/* Proveedor */}
                    <Grid.Col span={{ base: 12, sm: 8 }}>
                      <TextInput
                        label="Nombre del proveedor"
                        placeholder="Ej: Hotel Quito Palace"
                        {...contained}
                        {...register(`facturas.${i}.nombre_proveedor`)}
                        error={errors.facturas?.[i]?.nombre_proveedor?.message}
                      />
                    </Grid.Col>

                    {/* Monto */}
                    <Grid.Col span={{ base: 12, sm: 4 }}>
                      <Controller
                        name={`facturas.${i}.monto`}
                        control={control}
                        render={({ field: f }) => (
                          <NumberInput
                            label="Monto (USD)"
                            prefix="$"
                            decimalScale={2}
                            min={0}
                            {...contained}
                            value={f.value}
                            onChange={(v) =>
                              f.onChange(typeof v === "number" ? v : 0)
                            }
                            error={errors.facturas?.[i]?.monto?.message}
                          />
                        )}
                      />
                    </Grid.Col>

                    {/* Detalle opcional */}
                    <Grid.Col span={12}>
                      <TextInput
                        label="Detalle (opcional)"
                        placeholder="Descripción adicional del gasto"
                        {...contained}
                        {...register(`facturas.${i}.detalle`)}
                      />
                    </Grid.Col>
                  </Grid>
                </Card>
              );
            })}
          </Stack>

          <Button
            variant="light"
            color="orange"
            size="sm"
            leftSection={<IconPlus size={14} />}
            onClick={() =>
              append({
                categoria_factura_id: 0,
                fecha_factura: "",
                tipo_comprobante: "factura" as const,
                numero_factura: "",
                numero_ticket: "",
                ruc_proveedor: "",
                nombre_proveedor: "",
                detalle: "",
                monto: 0,
              })
            }
          >
            Agregar otro comprobante
          </Button>

          <Divider />

          <Group justify="flex-end">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="orange"
              leftSection={<IconCheck size={14} />}
            >
              Guardar comprobantes ({fields.length})
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
