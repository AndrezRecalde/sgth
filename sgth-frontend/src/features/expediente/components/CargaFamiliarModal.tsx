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
import { useContainedInput } from "@/hooks/useContainedInput";
import { erroresAlFormulario } from "@/lib/erroresAlFormulario";
import { useCargaFamiliarMutations } from "../hooks/useCargaFamiliarMutations";
import {
  cargaFamiliarSchema,
  type CargaFamiliarFormData,
} from "../schemas/cargaFamiliar.schema";
import type { CargaFamiliar } from "@/types/api";
import { DatePickerInput } from "@mantine/dates";
import { toDateValue, fromDateValue } from "@/lib/fecha"
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

  /*
   * Los valores salen de `initialValues` al montar. `FamiliaTab` monta el
   * modal con `key` por familiar y vuelve a 'nueva' al cerrar, así que cada
   * edición parte del registro vigente. El efecto que además reseteaba al
   * cambiar `initialValues` repetía ese trabajo.
   */
  const {
    register,
    control,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<CargaFamiliarFormData>({
    resolver: zodResolver(cargaFamiliarSchema),
    defaultValues: initialValues ? valoresDeCarga(initialValues) : CARGA_FAMILIAR_VACIA,
  });

  // El modal de alta no se vuelve a montar entre una apertura y otra (su
  // `key` es siempre 'nueva'): sin este reset, lo escrito y cancelado
  // reaparecía al pulsar otra vez «Agregar carga familiar».
  const cerrar = () => {
    reset();
    onClose();
  };

  // El backend pide una fecha anterior a hoy (`before:today`).
  const ayer = new Date();
  ayer.setDate(ayer.getDate() - 1);

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
      .catch((e) => erroresAlFormulario(
        e, setError, Object.keys(cargaFamiliarSchema.shape), "No se pudo guardar la carga familiar",
      ));
  };

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
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
              // `allowDeselect={false}`: pulsar «Cónyuge» ya marcado lo quitaba, y
              // el `?? "hijo"` que había aquí lo convertía en «Hijo/a» sin
              // que nadie lo eligiera.
              <Select
                label="Parentesco"
                data={PARENTESCO_OPTIONS}
                allowDeselect={false}
                {...contained}
                value={field.value}
                onChange={field.onChange}
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
                allowDeselect={false}
                {...contained}
                value={field.value ?? null}
                onChange={field.onChange}
                error={errors.genero?.message}
              />
            )}
          />
        </SimpleGrid>

        <Controller
          name="fecha_nacimiento"
          control={control}
          render={({ field }) => (
            // Obligatoria y sin `clearable`: borrarla dejaba `null` y el
            // esquema respondía en inglés que esperaba una cadena.
            <DatePickerInput
              label="Fecha de nacimiento"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              maxDate={ayer}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_nacimiento?.message}
            />
          )}
        />

        {/* Sin interruptores de discapacidad ni de enfermedad: se registran
            al desplegar la fila del familiar, y la marca sale de ahí. */}
        <Textarea
          label="Observaciones (opcional)"
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
