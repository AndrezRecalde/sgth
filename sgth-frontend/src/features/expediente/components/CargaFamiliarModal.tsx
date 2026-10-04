"use client";

import {
  Stack,
  TextInput,
  Select,
  SimpleGrid,
  Textarea,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect } from "react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useCargaFamiliarMutations } from "../hooks/useCargaFamiliarMutations";
import {
  cargaFamiliarSchema,
  type CargaFamiliarFormData,
} from "../schemas/cargaFamiliar.schema";
import type { CargaFamiliar } from "@/types/api";
import { DatePickerInput } from "@mantine/dates";
import { toDateValue, fromDateValueOrNull } from "@/lib/fecha"
import {
  CARGA_FAMILIAR_VACIA, PARENTESCO_OPTIONS, SEXO_OPTIONS, valoresDeCarga,
} from "../constants/cargaFamiliar";

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
  initialValues?: CargaFamiliar | null;
}

export function CargaFamiliarModal({
  opened,
  onClose,
  servidorId,
  initialValues,
}: Props) {
  const contained = useContainedInput();
  const { crear, editar } = useCargaFamiliarMutations(servidorId);
  const isEditing = !!initialValues;
  const cedulaFija = Boolean(initialValues?.cedula);

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CargaFamiliarFormData>({
    resolver: zodResolver(cargaFamiliarSchema),
    defaultValues: CARGA_FAMILIAR_VACIA,
  });

  useEffect(() => {
    reset(initialValues ? valoresDeCarga(initialValues) : CARGA_FAMILIAR_VACIA);
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
    <FormModal
      opened={opened}
      onClose={onClose}
      title={isEditing ? "Editar carga familiar" : "Agregar carga familiar"}
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={isEditing ? "Guardar cambios" : "Agregar familiar"}
      submitting={initialValues ? editar.isPending : crear.isPending}
    >
      <Stack gap="sm">
        <TextInput
          label="Cédula"
          placeholder="Ej: 0801234567"
          maxLength={10}
          // Una vez registrada no cambia: numera la historia clínica del
          // Dispensario. Un familiar antiguo sin cédula la escribe aquí.
          disabled={cedulaFija}
          description={cedulaFija
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

        <SimpleGrid cols={{ base: 1, xs: 2 }} spacing="sm">
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

          {/* Lo pide el Dispensario: sin él, la morbilidad por sexo dejaba
              fuera a todos los familiares. */}
          <Controller
            name="genero"
            control={control}
            render={({ field }) => (
              <Select
                label="Sexo"
                placeholder="Seleccione"
                data={SEXO_OPTIONS}
                {...contained}
                value={field.value ?? null}
                onChange={(v) => field.onChange(v ?? undefined)}
                error={errors.genero?.message}
              />
            )}
          />
        </SimpleGrid>

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
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_nacimiento?.message}
            />
          )}
        />

        {/* Sin interruptores de discapacidad ni de enfermedad: se registran
            al desplegar la fila del familiar, y la marca sale de ahí. */}
        <Textarea
          label="Observaciones (Opcional)"
          placeholder="Observaciones adicionales"
          rows={2}
          {...contained}
          {...register("observaciones")}
          error={errors.observaciones?.message}
        />
      </Stack>
    </FormModal>
  );
}
