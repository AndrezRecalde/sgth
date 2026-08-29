"use client";

import {
  Modal,
  Button,
  Group,
  Stack,
  TextInput,
  Select,
  Switch,
  Textarea,
} from "@mantine/core";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect } from "react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useCargaFamiliarMutations } from "../hooks/useCargaFamiliarMutations";
import {
  cargaFamiliarSchema,
  type CargaFamiliarFormData,
} from "../schemas/cargaFamiliar.schema";
import type { CargaFamiliar } from "@/types/api";
import { DatePickerInput } from "@mantine/dates";

const PARENTESCO_OPTIONS = [
  { value: "conyugue", label: "Cónyuge / Conviviente" },
  { value: "hijo", label: "Hijo/a" },
];

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
  initialValues?: CargaFamiliar | null;
}

const toDate = (v?: string | null): Date | null => {
  if (!v) return null;
  const datePart = v.split("T")[0];
  const [year, month, day] = datePart.split("-").map(Number);
  return new Date(year, month - 1, day);
};
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null;
  const date = typeof d === "string" ? toDate(d) : d;
  if (!date || isNaN(date.getTime())) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

export function CargaFamiliarModal({
  opened,
  onClose,
  servidorId,
  initialValues,
}: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { crear, editar } = useCargaFamiliarMutations(servidorId);
  const isEditing = !!initialValues;

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CargaFamiliarFormData>({
    resolver: zodResolver(cargaFamiliarSchema),
    defaultValues: {
      cedula: "",
      nombres: "",
      apellidos: "",
      parentesco: "hijo",
      fecha_nacimiento: "",
      persona_con_discapacidad: false,
      posee_enfermedad_catastrofica: false,
      observaciones: "",
    },
  });

  useEffect(() => {
    if (initialValues) {
      reset({
        cedula: initialValues.cedula ?? "",
        nombres: initialValues.nombres ?? "",
        apellidos: initialValues.apellidos ?? "",
        parentesco: initialValues.parentesco ?? "hijo",
        fecha_nacimiento: initialValues.fecha_nacimiento
          ? initialValues.fecha_nacimiento.split("T")[0]
          : "",
        persona_con_discapacidad:
          initialValues.persona_con_discapacidad ?? false,
        posee_enfermedad_catastrofica:
          initialValues.posee_enfermedad_catastrofica ?? false,
        observaciones: initialValues.observaciones ?? "",
      });
    } else {
      reset({
        cedula: "",
        nombres: "",
        apellidos: "",
        parentesco: "hijo",
        fecha_nacimiento: "",
        persona_con_discapacidad: false,
        posee_enfermedad_catastrofica: false,
        observaciones: "",
      });
    }
  }, [initialValues, reset]);

  const onSubmit = (values: CargaFamiliarFormData) => {
    const payload = {
      ...values,
      observaciones: values.observaciones || null,
    };
    const promise = initialValues
      ? editar.mutateAsync({
          id: initialValues.id,
          data: payload,
        })
      : crear.mutateAsync(payload);

    promise
      .then(() => {
        reset();
        onClose();
      })
      .catch(() => {});
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? "Editar carga familiar" : "Agregar carga familiar"}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput
            label="Cédula"
            placeholder="Ej: 0801234567"
            maxLength={10}
            disabled={isEditing}
            description={isEditing
              ? 'La cédula no se puede modificar'
              : undefined}
            {...contained}
            {...register("cedula")}
            error={errors.cedula?.message}
          />

          <TextInput
            label="Nombres"
            placeholder="Nombres del familiar"
            {...contained}
            {...register("nombres")}
            error={errors.nombres?.message}
          />

          <TextInput
            label="Apellidos"
            placeholder="Apellidos del familiar"
            {...contained}
            {...register("apellidos")}
            error={errors.apellidos?.message}
          />

          <Controller
            name="parentesco"
            control={control}
            render={({ field }) => (
              <Select
                label="Parentesco"
                data={PARENTESCO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? "hijo")}
                error={errors.parentesco?.message}
              />
            )}
          />

          <Controller
            name="fecha_nacimiento"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de nacimiento"
                placeholder="Seleccionar fecha"
                valueFormat="YYYY-MM-DD"
                clearable
                {...contained}
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
                error={errors.fecha_nacimiento?.message}
              />
            )}
          />

          <Controller
            name="persona_con_discapacidad"
            control={control}
            render={({ field }) => (
              <Switch
                label="Persona con discapacidad"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                color="emerald"
              />
            )}
          />

          <Controller
            name="posee_enfermedad_catastrofica"
            control={control}
            render={({ field }) => (
              <Switch
                label="Posee enfermedad catastrófica"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                color="emerald"
              />
            )}
          />

          <Textarea
            label="Observaciones (Opcional)"
            placeholder="Observaciones adicionales"
            rows={2}
            {...contained}
            {...register("observaciones")}
            error={errors.observaciones?.message}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={initialValues ? editar.isPending : crear.isPending}
            >
              {isEditing ? "Guardar cambios" : "Agregar familiar"}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
