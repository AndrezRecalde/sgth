"use client";

import { useEffect } from "react";
import { Alert, Button, Stack } from "@mantine/core";
import { IconInfoCircle, IconPlus } from "@tabler/icons-react";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useCategoriasFactura } from "../hooks/useViaticos";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { proyectarCalculo } from "../utils/calculoViatico";
import { rangoDelViaje } from "../utils/rangoViaje";
import {
  comprobantesSchema,
  type ComprobanteForm,
  type ComprobantesFormData,
  type FacturaData,
} from "../schemas/liquidacion.schema";
import { FacturaItemForm } from "./FacturaItemForm";
import { CalculoViaticoCard } from "./CalculoViaticoCard";
import type { CalculoViatico, CategoriaFactura, Viatico } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
  /** La cuenta del viático, para ver al momento cuánto queda por justificar. */
  calculo?: CalculoViatico;
  valorInicial?: FacturaData[];
}

const COMPROBANTE_VACIO: ComprobanteForm = {
  categoria_factura_id: 0,
  fecha_factura: null,
  tipo_comprobante: "factura",
  numero_factura: "",
  numero_ticket: "",
  ruc_proveedor: "",
  nombre_proveedor: "",
  detalle: "",
  monto: 0,
};

/** Los comprobantes de los gastos del viaje, con la cuenta al momento. */
export function FacturasModal({ opened, onClose, viatico, calculo, valorInicial = [] }: Props) {
  const { data: categorias = [] } = useCategoriasFactura();
  const { guardarFacturas } = useViaticoMutations();
  const rango = rangoDelViaje(viatico);

  const grupo = (viaticos: boolean) =>
    (categorias as CategoriaFactura[])
      .filter((c) => (c.grupo === "viatico") === viaticos)
      .map((c) => ({ value: String(c.id), label: c.nombre ?? "" }));

  // El grupo ya dice de qué es cada categoría: antes cada opción lo repetía
  // entre paréntesis.
  const categoriaOptions = [
    { group: "Hospedaje y alimentación", items: grupo(true) },
    { group: "Movilización", items: grupo(false) },
  ].filter((g) => g.items.length > 0);

  const inicial = valorInicial.length > 0 ? valorInicial : [COMPROBANTE_VACIO];

  const { control, register, handleSubmit, reset, formState: { errors } } =
    useForm<ComprobantesFormData>({
      resolver: zodResolver(comprobantesSchema),
      defaultValues: { facturas: inicial },
    });

  const { fields, append, remove } = useFieldArray({ control, name: "facturas" });

  // El modal vive montado: al abrirlo se parte de lo guardado.
  useEffect(() => {
    if (opened) reset({ facturas: valorInicial.length > 0 ? valorInicial : [COMPROBANTE_VACIO] });
  }, [opened, valorInicial, reset]);

  const facturas = useWatch({ control, name: "facturas" }) ?? [];
  const total = facturas.reduce((suma, f) => suma + (Number(f.monto) || 0), 0);

  const onSubmit = (values: ComprobantesFormData) =>
    guardarFacturas.mutate(
      { viaticoId: viatico.id, facturas: values.facturas },
      { onSuccess: onClose },
    );

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Comprobantes"
      size="xl"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar comprobantes"
      submitting={guardarFacturas.isPending}
    >
      <Stack gap="md">
        {calculo && <CalculoViaticoCard calculo={proyectarCalculo(calculo, total)} enCurso />}

        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Solo valen los comprobantes fechados dentro del viaje: del <strong>{rango.desde}</strong> al{" "}
          <strong>{rango.hasta}</strong>.
        </Alert>

        {fields.map((field, i) => (
          <FacturaItemForm
            key={field.id}
            index={i}
            control={control}
            register={register}
            errors={errors}
            categoriaOptions={categoriaOptions}
            minFecha={rango.min}
            maxFecha={rango.max}
            onEliminar={fields.length > 1 ? () => remove(i) : undefined}
          />
        ))}

        <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => append({ ...COMPROBANTE_VACIO })}>
          Agregar comprobante
        </Button>
      </Stack>
    </FormModal>
  );
}
