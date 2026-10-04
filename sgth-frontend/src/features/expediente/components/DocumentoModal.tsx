"use client";

import { Stack, Select, Textarea } from "@mantine/core";
import { DatePickerInput } from "@mantine/dates";
import { Controller, useForm, type DefaultValues } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useContainedInput } from "@/hooks/useContainedInput";
import { erroresAlFormulario } from "@/lib/erroresAlFormulario";
import { toDateValue, fromDateValueOrNull } from "@/lib/fecha";
import { useDocumentoMutations } from "../hooks/useDocumentoMutations";
import { documentoSchema, type DocumentoFormData } from "../schemas/documento.schema";
import { ARCHIVO_DOCUMENTO, TIPO_DOCUMENTO_OPTIONS } from "../constants/documentos";
import { ZonaArchivo } from "./ZonaArchivo";

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
}

const VACIO: DefaultValues<DocumentoFormData> = {
  tipo_documento: "",
  descripcion: "",
  fecha_vencimiento: "",
};

export function DocumentoModal({ opened, onClose, servidorId }: Props) {
  const contained = useContainedInput();
  const { subir } = useDocumentoMutations(servidorId);

  const {
    control,
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<DocumentoFormData>({
    resolver: zodResolver(documentoSchema),
    defaultValues: VACIO,
  });

  const handleClose = () => {
    reset(VACIO);
    onClose();
  };

  const onSubmit = (values: DocumentoFormData) => {
    const formData = new FormData();
    formData.append("archivo", values.archivo);
    formData.append("tipo_documento", values.tipo_documento);
    if (values.descripcion) formData.append("descripcion", values.descripcion);
    if (values.fecha_vencimiento)
      formData.append("fecha_vencimiento", values.fecha_vencimiento);

    subir
      .mutateAsync(formData)
      .then(handleClose)
      .catch((e) => erroresAlFormulario(
        e, setError, Object.keys(documentoSchema.shape), "No se pudo subir el documento",
      ));
  };

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title="Subir documento al expediente"
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Subir documento"
      submitting={subir.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="tipo_documento"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de documento"
              placeholder="Seleccionar tipo"
              data={TIPO_DOCUMENTO_OPTIONS}
              allowDeselect={false}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? "")}
              error={errors.tipo_documento?.message}
            />
          )}
        />

        <Controller
          name="archivo"
          control={control}
          render={({ field }) => (
            <ZonaArchivo
              {...ARCHIVO_DOCUMENTO}
              value={field.value}
              onChange={field.onChange}
              onRechazo={(mensaje) => setError("archivo", { message: mensaje })}
              error={errors.archivo?.message}
            />
          )}
        />

        <Textarea
          label="Descripción"
          placeholder="Ej: Cédula renovada en 2026"
          autosize
          minRows={2}
          {...contained}
          {...register("descripcion")}
          error={errors.descripcion?.message}
        />

        <Controller
          name="fecha_vencimiento"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de vencimiento"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              clearable
              // Un documento ya vencido no se anexa (Talento Humano,
              // 2026-10-03); el que vence hoy, sí.
              minDate={new Date()}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              description="Para pasaportes y documentos con caducidad. Uno ya vencido no se anexa."
              error={errors.fecha_vencimiento?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  );
}
