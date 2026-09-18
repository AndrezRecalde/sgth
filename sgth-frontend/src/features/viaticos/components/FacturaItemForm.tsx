"use client";

import { Grid, NumberInput, Paper, Select, TextInput } from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import {
  Controller,
  useWatch,
  type Control,
  type FieldErrors,
  type UseFormRegister,
} from "react-hook-form";
import { useContainedInput } from "@/hooks/useContainedInput";
import { fromDateValueOrNull, toDateValue } from "@/lib/fecha";
import type { ComprobantesFormData } from "../schemas/liquidacion.schema";
import { ItemCabecera } from "./ItemCabecera";

type Opcion = { value: string; label: string };
type GrupoOpcion = { group: string; items: Opcion[] };

const TIPOS = [
  { value: "factura", label: "Factura" },
  { value: "ticket", label: "Ticket" },
  { value: "recibo", label: "Recibo" },
  { value: "otro", label: "Otro" },
];

interface Props {
  index: number;
  control: Control<ComprobantesFormData>;
  register: UseFormRegister<ComprobantesFormData>;
  errors: FieldErrors<ComprobantesFormData>;
  categoriaOptions: (Opcion | GrupoOpcion)[];
  minFecha?: Date;
  maxFecha?: Date;
  /** Sin esto no se puede quitar: queda al menos uno. */
  onEliminar?: () => void;
}

/** Un comprobante: qué es, de quién, cuándo y cuánto. */
export function FacturaItemForm({
  index,
  control,
  register,
  errors,
  categoriaOptions,
  minFecha,
  maxFecha,
  onEliminar,
}: Props) {
  const contained = useContainedInput();
  const tipo = useWatch({ control, name: `facturas.${index}.tipo_comprobante` });
  const conRuc = tipo === "factura" || tipo === "recibo";
  const err = errors.facturas?.[index];

  return (
    <Paper withBorder radius="md" p="md">
      <ItemCabecera
        titulo={`Comprobante ${index + 1}`}
        onEliminar={onEliminar}
        etiquetaEliminar="Quitar este comprobante"
      />

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name={`facturas.${index}.categoria_factura_id`}
            control={control}
            render={({ field }) => (
              <Select
                label="Categoría"
                data={categoriaOptions}
                searchable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : 0)}
                error={err?.categoria_factura_id?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name={`facturas.${index}.tipo_comprobante`}
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de comprobante"
                data={TIPOS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? "factura")}
                error={err?.tipo_comprobante?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            // Lo opcional lo dice la etiqueta, como en «Detalle (opcional)».
            label={conRuc ? "RUC del proveedor" : "RUC o identificación (opcional)"}
            placeholder="0000000000001"
            {...contained}
            {...register(`facturas.${index}.ruc_proveedor`)}
            error={err?.ruc_proveedor?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Proveedor"
            placeholder="Ej: Hotel Quito"
            {...contained}
            {...register(`facturas.${index}.nombre_proveedor`)}
            error={err?.nombre_proveedor?.message}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          {tipo === "ticket" ? (
            <TextInput
              label="Número de ticket"
              placeholder="Ej: T-001"
              {...contained}
              {...register(`facturas.${index}.numero_ticket`)}
              error={err?.numero_ticket?.message}
            />
          ) : (
            <TextInput
              label="Número de comprobante"
              placeholder="Ej: 001-001-000001"
              {...contained}
              {...register(`facturas.${index}.numero_factura`)}
              error={err?.numero_factura?.message}
            />
          )}
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 3 }}>
          <Controller
            name={`facturas.${index}.fecha_factura`}
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                valueFormat="DD/MM/YYYY"
                minDate={minFecha}
                maxDate={maxFecha}
                {...contained}
                value={toDateValue(field.value)}
                onChange={(v) => field.onChange(fromDateValueOrNull(v))}
                error={err?.fecha_factura?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 6, sm: 3 }}>
          <Controller
            name={`facturas.${index}.monto`}
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Monto"
                prefix="$"
                decimalScale={2}
                min={0.01}
                hideControls
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === "number" ? v : 0)}
                error={err?.monto?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <TextInput
            label="Detalle (opcional)"
            placeholder="Ej: Noche del 08/06/2026"
            {...contained}
            {...register(`facturas.${index}.detalle`)}
          />
        </Grid.Col>
      </Grid>
    </Paper>
  );
}
