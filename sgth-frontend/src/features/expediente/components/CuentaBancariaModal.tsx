"use client";

import { Select, TextInput, Grid, Switch } from "@mantine/core";
import { useForm, Controller, type DefaultValues } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useEntidadesFinancieras } from "../hooks/useEntidadesFinancieras";
import { useCuentaBancariaMutations } from "../hooks/useCuentaBancariaMutations";
import {
  cuentaBancariaSchema,
  type CuentaBancariaFormData,
} from "../schemas/cuentaBancaria.schema";
import type { CuentaBancariaConRelaciones } from "@/types/api";

const TIPO_CUENTA_OPTIONS = [
  { value: "ahorros", label: "Ahorros" },
  { value: "corriente", label: "Corriente" },
];

const PROPOSITO_OPTIONS = [
  { value: "sueldo", label: "Nómina" },
  { value: "viaticos", label: "Viáticos" },
  { value: "ambos", label: "Nómina y viáticos" },
];

// Sin entidad: todavía no se elige (DefaultValues admite omitir la clave).
const VACIO: DefaultValues<CuentaBancariaFormData> = {
  numero_cuenta: "",
  tipo_cuenta: "ahorros",
  proposito: "sueldo",
  es_principal_sueldo: false,
  es_principal_viatico: false,
  estado: true,
};

function valoresDe(c: CuentaBancariaConRelaciones): CuentaBancariaFormData {
  return {
    entidad_financiera_id: Number(c.entidad_financiera_id),
    numero_cuenta: c.numero_cuenta ?? "",
    tipo_cuenta: c.tipo_cuenta === "corriente" ? "corriente" : "ahorros",
    proposito: c.proposito === "viaticos" || c.proposito === "ambos" ? c.proposito : "sueldo",
    es_principal_sueldo: c.es_principal_sueldo ?? false,
    es_principal_viatico: c.es_principal_viatico ?? false,
    estado: c.estado ?? true,
  };
}

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
  initialValues?: CuentaBancariaConRelaciones | null;
}

/**
 * El propósito es un campo propio. Antes lo recalculaba un efecto a partir de
 * los interruptores de «principal», también al abrir para editar: una cuenta
 * de viáticos que no era principal se guardaba como de nómina.
 *
 * El padre lo monta con `key` por cuenta: los valores iniciales bastan.
 */
export function CuentaBancariaModal({ opened, onClose, servidorId, initialValues }: Props) {
  const contained = useContainedInput();
  const { crear, editar } = useCuentaBancariaMutations(servidorId);
  const { data: entidades = [], isLoading: loadingEntidades } = useEntidadesFinancieras();
  const isEditing = !!initialValues;

  const entidadOptions = entidades.map((e) => ({
    value: String(e.id),
    label: e.nombre ?? `Entidad ${e.id}`,
  }));

  const { register, control, handleSubmit, reset, setValue, getValues, formState: { errors } } =
    useForm<CuentaBancariaFormData>({
      resolver: zodResolver(cuentaBancariaSchema),
      defaultValues: initialValues ? valoresDe(initialValues) : VACIO,
    });

  const handleClose = () => {
    reset(VACIO);
    onClose();
  };

  /** Marcar como principal de algo que la cuenta no paga amplía su propósito. */
  const alMarcarPrincipal = (de: "sueldo" | "viaticos", marcada: boolean) => {
    const proposito = getValues("proposito");
    if (marcada && proposito !== de && proposito !== "ambos") {
      setValue("proposito", "ambos", { shouldValidate: true });
    }
  };

  const onSubmit = (values: CuentaBancariaFormData) => {
    const guardado = initialValues
      ? editar.mutateAsync({ id: Number(initialValues.id), data: values })
      : crear.mutateAsync(values);
    guardado.then(handleClose).catch(() => {}); // el hook ya notificó
  };

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? "Editar cuenta bancaria" : "Nueva cuenta bancaria"}
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={isEditing ? "Guardar cambios" : "Registrar cuenta"}
      submitting={crear.isPending || editar.isPending}
    >
      <Grid>
        <Grid.Col span={12}>
          <Controller
            name="entidad_financiera_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Entidad financiera"
                placeholder={loadingEntidades ? "Cargando entidades..." : "Buscar banco o cooperativa"}
                data={entidadOptions}
                searchable
                disabled={loadingEntidades}
                nothingFoundMessage="No se encontró la entidad"
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                error={errors.entidad_financiera_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 7 }}>
          <TextInput
            label="Número de cuenta"
            placeholder="Número completo de la cuenta"
            {...contained}
            {...register("numero_cuenta")}
            error={errors.numero_cuenta?.message}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller
            name="tipo_cuenta"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de cuenta"
                data={TIPO_CUENTA_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v === "corriente" ? "corriente" : "ahorros")}
                error={errors.tipo_cuenta?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Controller
            name="proposito"
            control={control}
            render={({ field }) => (
              <Select
                label="Se usa para"
                data={PROPOSITO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? "sueldo")}
                error={errors.proposito?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="es_principal_sueldo"
            control={control}
            render={({ field }) => (
              <Switch
                label="Principal para nómina"
                description="Aquí se deposita el sueldo"
                checked={field.value ?? false}
                onChange={(e) => {
                  field.onChange(e.currentTarget.checked);
                  alMarcarPrincipal("sueldo", e.currentTarget.checked);
                }}
                error={errors.es_principal_sueldo?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="es_principal_viatico"
            control={control}
            render={({ field }) => (
              <Switch
                label="Principal para viáticos"
                description="Aquí se pagan viáticos y comisiones"
                checked={field.value ?? false}
                onChange={(e) => {
                  field.onChange(e.currentTarget.checked);
                  alMarcarPrincipal("viaticos", e.currentTarget.checked);
                }}
                error={errors.es_principal_viatico?.message}
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </FormModal>
  );
}
