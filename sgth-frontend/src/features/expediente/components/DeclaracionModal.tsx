"use client";

import { useState } from "react";
import { FileInput, Stack, TextInput, Select } from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import { fromDateValue, toDateValue } from "@/lib/fecha";
import { useDeclaracionMutations } from "../hooks/useDeclaracionMutations";
import {
  declaracionSchema,
  type DeclaracionFormData,
} from "../schemas/declaracion.schema";
import type { DeclaracionJuramentada } from "@/types/api";

const TIPO_OPTIONS = [
  { value: "inicio_gestion", label: "Inicio de gestión" },
  { value: "periodica",      label: "Periódica" },
  { value: "fin_gestion",    label: "Fin de gestión" },
];

const VACIO: DeclaracionFormData = {
  tipo_declaracion: "inicio_gestion",
  fecha_declaracion: "",
  codigo_barras: "",
};

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
  initialValues?: DeclaracionJuramentada | null;
}

// El padre lo monta con `key` por declaración: los valores iniciales bastan.
export function DeclaracionModal({ opened, onClose, servidorId, initialValues }: Props) {
  const contained = useContainedInput();
  const { crear, editar } = useDeclaracionMutations(servidorId);
  const [documento, setDocumento] = useState<File | null>(null);
  const isEditing = !!initialValues;

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<DeclaracionFormData>({
    resolver: zodResolver(declaracionSchema),
    defaultValues: initialValues
      ? {
          tipo_declaracion: initialValues.tipo_declaracion,
          fecha_declaracion: initialValues.fecha_declaracion?.split("T")[0] ?? "",
          codigo_barras: initialValues.codigo_barras ?? "",
        }
      : VACIO,
  });

  const handleClose = () => {
    reset(VACIO);
    setDocumento(null);
    onClose();
  };

  const onSubmit = (data: DeclaracionFormData) => {
    const guardado = initialValues
      ? editar.mutateAsync({ id: initialValues.id, data, documento })
      : crear.mutateAsync({ data, documento });
    guardado.then(handleClose).catch(() => {}); // el hook ya notificó
  };

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? "Editar declaración" : "Registrar declaración juramentada"}
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={isEditing ? "Guardar cambios" : "Registrar declaración"}
      submitting={crear.isPending || editar.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="tipo_declaracion"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de declaración"
              data={TIPO_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? "inicio_gestion")}
              error={errors.tipo_declaracion?.message}
            />
          )}
        />
        <Controller
          name="fecha_declaracion"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de declaración"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              clearable
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d))}
              error={errors.fecha_declaracion?.message}
            />
          )}
        />
        <TextInput
          label="Código de barras / Número"
          placeholder="Número de la declaración"
          {...contained}
          {...register("codigo_barras")}
          error={errors.codigo_barras?.message}
        />
        <FileInput
          label={initialValues?.documento_nombre_archivo
            ? "Reemplazar documento (PDF)"
            : "Documento escaneado (PDF)"}
          placeholder={initialValues?.documento_nombre_archivo ?? "Opcional, hasta 10 MB"}
          accept="application/pdf"
          clearable
          {...contained}
          value={documento}
          onChange={setDocumento}
        />
      </Stack>
    </FormModal>
  );
}
