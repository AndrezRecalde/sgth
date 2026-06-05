"use client";

import {
  Stack,
  Group,
  Button,
  Text,
  Card,
  TextInput,
  Textarea,
  NumberInput,
  ActionIcon,
  Divider,
  Badge,
  Grid,
  Select,
} from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { IconPlus, IconTrash } from "@tabler/icons-react";
import { useForm, useFieldArray, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { useCategoriasFactura } from "../hooks/useViaticos";
import {
  liquidacionSchema,
  type LiquidacionFormData,
} from "../schemas/viatico.schema";
import type { Viatico, CategoriaFactura } from "@/types/api";

interface Props {
  viatico: Viatico;
  onSuccess: () => void;
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null;
  const [y, m, d] = v.split("-").map(Number);
  return new Date(y, m - 1, d);
};

const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null;
  const dt = typeof d === "string" ? new Date(d + "T00:00:00") : d;
  if (!(dt instanceof Date) || isNaN(dt.getTime())) return null;
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(dt.getDate()).padStart(2, "0")}`;
};

export function LiquidacionForm({ viatico, onSuccess }: Props) {
  const contained = useContainedInput();
  const { liquidar } = useViaticoMutations();

  const { data: categorias = [] } = useCategoriasFactura();

  const categoriaOptions = (categorias as CategoriaFactura[]).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));

  const {
    control,
    handleSubmit,
    register,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<LiquidacionFormData>({
    resolver: zodResolver(liquidacionSchema),
    defaultValues: {
      fecha_retorno: "",
      observaciones: "",
      facturas: [
        {
          categoria_factura_id: 0,
          tipo_comprobante: "factura" as const,
          fecha_factura: "",
          numero_factura: "",
          numero_ticket: "",
          ruc_proveedor: "",
          nombre_proveedor: "",
          detalle: "",
          monto: 0,
        },
      ],
      actividades: [
        {
          fecha: "",
          hora_inicio: "08:00",
          hora_fin: "17:00",
          descripcion: "",
          lugar: "",
        },
      ],
    },
  });

  const {
    fields: facturaFields,
    append: appendFactura,
    remove: removeFactura,
  } = useFieldArray({ control, name: "facturas" });

  const {
    fields: actividadFields,
    append: appendActividad,
    remove: removeActividad,
  } = useFieldArray({ control, name: "actividades" });

  const facturasWatch = watch("facturas");
  const totalFacturas = facturasWatch.reduce(
    (sum, f) => sum + (Number(f.monto) || 0),
    0,
  );
  const anticipo = Number(viatico.monto_anticipo ?? 0);
  const diferencia = anticipo - totalFacturas;

  const onSubmit = async (values: LiquidacionFormData) => {
    await liquidar.mutateAsync({
      viaticoId: viatico.id,
      data: values,
    });
    onSuccess();
  };

  return (
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
              Total facturas:
            </Text>
            <Text size="sm" fw={600} c="blue">
              ${totalFacturas.toFixed(2)}
            </Text>
          </Group>
          <Divider my={4} />
          <Group justify="space-between">
            <Text size="sm" fw={600}>
              {diferencia >= 0 ? "A devolver:" : "Diferencia a cobrar:"}
            </Text>
            <Text size="sm" fw={700} c={diferencia >= 0 ? "orange" : "emerald"}>
              ${Math.abs(diferencia).toFixed(2)}
            </Text>
          </Group>
        </Card>

        {/* Fecha de retorno */}
        <Controller
          name="fecha_retorno"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de retorno"
              placeholder="Seleccionar fecha"
              valueFormat="YYYY-MM-DD"
              {...contained}
              value={toDate(field.value)}
              onChange={(v) => field.onChange(fromDate(v as Date | null) ?? "")}
              error={errors.fecha_retorno?.message}
            />
          )}
        />

        {/* Actividades */}
        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600}>
                Informe de actividades
              </Text>
              <Badge size="xs" color="blue">
                {actividadFields.length}
              </Badge>
            </Group>
          }
          labelPosition="left"
        />

        <Stack gap="xs">
          {actividadFields.map((field, i) => (
            <Card key={field.id} withBorder radius="md" p="sm">
              <Group justify="space-between" mb="xs">
                <Text size="xs" fw={600} c="dimmed">
                  Actividad {i + 1}
                </Text>
                {actividadFields.length > 1 && (
                  <ActionIcon
                    size="xs"
                    color="red"
                    variant="subtle"
                    onClick={() => removeActividad(i)}
                  >
                    <IconTrash size={12} />
                  </ActionIcon>
                )}
              </Group>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name={`actividades.${i}.fecha`}
                    control={control}
                    render={({ field: f }) => (
                      <DatePickerInput
                        label="Fecha"
                        placeholder="Seleccionar"
                        valueFormat="YYYY-MM-DD"
                        {...contained}
                        value={toDate(f.value)}
                        onChange={(v) => f.onChange(fromDate(v as Date | null) ?? "")}
                        error={errors.actividades?.[i]?.fecha?.message}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 4 }}>
                  <TextInput
                    label="Hora inicio"
                    type="time"
                    {...contained}
                    {...register(`actividades.${i}.hora_inicio`)}
                    error={errors.actividades?.[i]?.hora_inicio?.message}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 4 }}>
                  <TextInput
                    label="Hora fin"
                    type="time"
                    {...contained}
                    {...register(`actividades.${i}.hora_fin`)}
                    error={errors.actividades?.[i]?.hora_fin?.message}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 8 }}>
                  <TextInput
                    label="Lugar"
                    placeholder="Ciudad / Institución"
                    {...contained}
                    {...register(`actividades.${i}.lugar`)}
                    error={errors.actividades?.[i]?.lugar?.message}
                  />
                </Grid.Col>
              </Grid>
              <Textarea
                label="Descripción de la actividad"
                placeholder="Detalle las actividades realizadas"
                autosize
                minRows={2}
                maxRows={4}
                mt="xs"
                {...contained}
                {...register(`actividades.${i}.descripcion`)}
                error={errors.actividades?.[i]?.descripcion?.message}
              />
            </Card>
          ))}

          <Button
            size="xs"
            variant="light"
            color="blue"
            leftSection={<IconPlus size={12} />}
            onClick={() =>
              appendActividad({
                fecha: "",
                hora_inicio: "08:00",
                hora_fin: "17:00",
                descripcion: "",
                lugar: "",
              })
            }
          >
            Agregar actividad
          </Button>
        </Stack>

        {/* Facturas */}
        <Divider
          label={
            <Group gap={4}>
              <Text size="xs" fw={600}>
                Facturas de respaldo
              </Text>
              <Badge size="xs" color="orange">
                {facturaFields.length}
              </Badge>
            </Group>
          }
          labelPosition="left"
        />

        <Stack gap="xs">
          {facturaFields.map((field, i) => {
            const tipoComp = facturasWatch?.[i]?.tipo_comprobante ?? "factura";
            return (
              <Card key={field.id} withBorder radius="md" p="sm">
                <Group justify="space-between" mb="xs">
                  <Text size="xs" fw={600} c="dimmed">
                    Factura {i + 1}
                  </Text>
                  {facturaFields.length > 1 && (
                    <ActionIcon
                      size="xs"
                      color="red"
                      variant="subtle"
                      onClick={() => removeFactura(i)}
                    >
                      <IconTrash size={12} />
                    </ActionIcon>
                  )}
                </Group>
                <Grid>
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name={`facturas.${i}.categoria_factura_id`}
                      control={control}
                      render={({ field: f }) => (
                        <Select
                          label="Categoría"
                          data={categoriaOptions}
                          searchable
                          {...contained}
                          value={f.value ? String(f.value) : null}
                          onChange={(v) => f.onChange(v ? Number(v) : 0)}
                          error={
                            errors.facturas?.[i]?.categoria_factura_id?.message
                          }
                        />
                      )}
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name={`facturas.${i}.tipo_comprobante`}
                      control={control}
                      render={({ field: f }) => (
                        <Select
                          label="Tipo de comprobante"
                          data={[
                            { value: "factura", label: "Factura" },
                            { value: "ticket", label: "Ticket / Pasaje" },
                            { value: "recibo", label: "Recibo" },
                            { value: "otro", label: "Otro" },
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
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name={`facturas.${i}.fecha_factura`}
                      control={control}
                      render={({ field: f }) => (
                        <DatePickerInput
                          label="Fecha del comprobante"
                          placeholder="Seleccionar"
                          valueFormat="YYYY-MM-DD"
                          clearable
                          {...contained}
                          value={toDate(f.value)}
                          onChange={(v) =>
                            f.onChange(fromDate(v as Date | null) ?? "")
                          }
                        />
                      )}
                    />
                  </Grid.Col>
                  {tipoComp === "ticket" ? (
                    <Grid.Col span={{ base: 12, sm: 6 }}>
                      <TextInput
                        label="N° Ticket / Pasaje"
                        placeholder="Ej: T-001234"
                        {...contained}
                        {...register(`facturas.${i}.numero_ticket`)}
                        error={errors.facturas?.[i]?.numero_ticket?.message}
                      />
                    </Grid.Col>
                  ) : (
                    <Grid.Col span={{ base: 12, sm: 6 }}>
                      <TextInput
                        label="N° Factura"
                        placeholder="001-001-000000001"
                        {...contained}
                        {...register(`facturas.${i}.numero_factura`)}
                        error={errors.facturas?.[i]?.numero_factura?.message}
                      />
                    </Grid.Col>
                  )}
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <TextInput
                      label={
                        tipoComp === "ticket"
                          ? "RUC proveedor (opcional)"
                          : "RUC proveedor"
                      }
                      placeholder="0000000000001"
                      {...contained}
                      {...register(`facturas.${i}.ruc_proveedor`)}
                      error={errors.facturas?.[i]?.ruc_proveedor?.message}
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <TextInput
                      label="Proveedor"
                      placeholder="Nombre del proveedor"
                      {...contained}
                      {...register(`facturas.${i}.nombre_proveedor`)}
                      error={errors.facturas?.[i]?.nombre_proveedor?.message}
                    />
                  </Grid.Col>
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <Controller
                      name={`facturas.${i}.monto`}
                      control={control}
                      render={({ field: f }) => (
                        <NumberInput
                          label="Monto"
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
                  <Grid.Col span={{ base: 12, sm: 6 }}>
                    <TextInput
                      label="Detalle (opcional)"
                      placeholder="Descripción adicional"
                      {...contained}
                      {...register(`facturas.${i}.detalle`)}
                    />
                  </Grid.Col>
                </Grid>
              </Card>
            );
          })}

          <Button
            size="xs"
            variant="light"
            color="orange"
            leftSection={<IconPlus size={12} />}
            onClick={() =>
              appendFactura({
                categoria_factura_id: 0,
                tipo_comprobante: "factura",
                fecha_factura: "",
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
        </Stack>

        <Controller
          name="observaciones"
          control={control}
          render={({ field }) => (
            <Textarea
              label="Observaciones (opcional)"
              placeholder="Observaciones generales de la comisión"
              autosize
              minRows={2}
              {...contained}
              value={field.value ?? ""}
              onChange={(e) => field.onChange(e.currentTarget.value)}
            />
          )}
        />

        <Group justify="flex-end" mt="md">
          <Button type="submit" color="emerald" loading={isSubmitting}>
            Registrar liquidación
          </Button>
        </Group>
      </Stack>
    </form>
  );
}
