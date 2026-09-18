"use client";

import { useEffect } from "react";
import {
  Stack,
  Text,
  Button,
  Alert,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import {
  IconPlus,
  IconInfoCircle,
} from "@tabler/icons-react";
import { useForm, useFieldArray, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { useCategoriasFactura } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { FacturaItemForm } from "./FacturaItemForm";
import { CalculoViaticoCard } from "./CalculoViaticoCard";
import { proyectarCalculo } from "../utils/calculoViatico";
import type {
  Viatico,
  CalculoViatico,
  CategoriaFactura,
  EstadoRevisionComprobante,
} from "@/types/api";

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
  /** Solo para mostrar la revisión de Financiero; no se envía a guardar. */
  estado_revision?: EstadoRevisionComprobante;
  observacion_revision?: string | null;
}

const facturaItemSchema = z
  .object({
    categoria_factura_id: z.number().min(1, "Seleccione categoría"),
    fecha_factura: z.string().optional().nullable(),
    tipo_comprobante: z.enum(["factura", "ticket", "recibo", "otro"]),
    numero_factura: z.string().optional().nullable(),
    numero_ticket: z.string().optional().nullable(),
    ruc_proveedor: z.string().optional().nullable(),
    nombre_proveedor: z.string().min(1, "Requerido"),
    detalle: z.string().optional().nullable(),
    monto: z.number().min(0.01, "Mínimo $0.01"),
  })
  .refine(
    (data) => {
      if (["factura", "recibo"].includes(data.tipo_comprobante)) {
        return !!data.ruc_proveedor && data.ruc_proveedor.trim().length > 0;
      }
      return true;
    },
    {
      message: "El RUC es obligatorio para factura y recibo",
      path: ["ruc_proveedor"],
    },
  );

const schema = z.object({
  facturas: z.array(facturaItemSchema).min(1, "Agregue al menos una factura"),
});

type FormData = z.infer<typeof schema>;

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
  /** La cuenta del viático, para ver al momento cuánto queda por justificar. */
  calculo?: CalculoViatico;
  onGuardar?: (facturas: FacturaData[]) => void;
  valorInicial?: FacturaData[];
}

const FACTURA_VACIA: FacturaData = {
  categoria_factura_id: 0,
  fecha_factura: "",
  tipo_comprobante: "factura",
  numero_factura: "",
  numero_ticket: "",
  ruc_proveedor: "",
  nombre_proveedor: "",
  detalle: "",
  monto: 0,
};

export function FacturasModal({
  opened,
  onClose,
  viatico,
  calculo,
  onGuardar,
  valorInicial = [],
}: Props) {
  const { data: categorias = [] } = useCategoriasFactura();
  const { guardarFacturas } = useViaticoMutations();

  const viaticoItems = (categorias as CategoriaFactura[])
    .filter((c) => c.grupo === "viatico")
    .map((c) => ({ value: String(c.id), label: `${c.nombre} (Viático)` }));

  const movilizacionItems = (categorias as CategoriaFactura[])
    .filter((c) => c.grupo !== "viatico")
    .map((c) => ({ value: String(c.id), label: `${c.nombre} (Movilización)` }));

  const categoriaOptions = [
    ...(viaticoItems.length > 0
      ? [{ group: "Viático (hospedaje y alimentación)", items: viaticoItems }]
      : []),
    ...(movilizacionItems.length > 0
      ? [{ group: "Movilización", items: movilizacionItems }]
      : []),
  ];

  const minFecha = viatico.datetime_salida
    ? (() => {
        const d = new Date(viatico.datetime_salida as string);
        d.setHours(0, 0, 0, 0);
        return d;
      })()
    : undefined;

  const maxFecha = viatico.datetime_llegada
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
    reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      facturas: valorInicial.length > 0 ? valorInicial : [FACTURA_VACIA],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "facturas",
  });

  useEffect(() => {
    if (opened) {
      reset({
        facturas: valorInicial.length > 0 ? valorInicial : [FACTURA_VACIA],
      });
    }
  }, [opened, valorInicial, reset]);

  const facturasWatch = useWatch({ control, name: "facturas" }) || [];
  const totalFacturas = facturasWatch.reduce(
    (sum, f) => sum + (Number(f.monto) || 0),
    0,
  );

  const formatFecha = (f?: string | null) => {
    if (!f) return "—";
    return new Date(f).toLocaleDateString("es-EC", {
      timeZone: "UTC",
      day: "2-digit",
      month: "long",
      year: "numeric",
    });
  };

  const onSubmit = async (values: FormData) => {
    try {
      await guardarFacturas.mutateAsync({
        viaticoId: viatico.id,
        facturas: values.facturas,
      });
      onGuardar?.(values.facturas);
      onClose();
    } catch {
      // El hook de mutación ya notifica el error.
    }
  };

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Facturas de respaldo"
      size="xl"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar comprobantes"
      submitting={guardarFacturas.isPending}
    >
      <Stack gap="md">
        {calculo && (
          <CalculoViaticoCard
            calculo={proyectarCalculo(calculo, totalFacturas)}
            enCurso
          />
        )}

        <Alert
          icon={<IconInfoCircle size={14} />}
          color="amber"
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
              {maxFecha?.toLocaleDateString("es-EC", {
                day: "2-digit",
                month: "long",
                year: "numeric",
              }) ?? "—"}
            </strong>
          </Text>
        </Alert>

        {fields.map((field, i) => (
          <FacturaItemForm
            key={field.id}
            index={i}
            control={control}
            register={register}
            errors={errors}
            categoriaOptions={categoriaOptions}
            minFecha={minFecha}
            maxFecha={maxFecha}
            onEliminar={() => remove(i)}
            puedeEliminar={fields.length > 1}
          />
        ))}

        <Button
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={() => append({ ...FACTURA_VACIA })}
        >
          Agregar comprobante
        </Button>
      </Stack>
    </FormModal>
  );
}
