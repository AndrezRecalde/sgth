"use client";

import {
  Stack,
  Select,
  Textarea,
  Text,
  FileInput,
} from "@mantine/core";
import { ModalFooter, SgthModal } from "@/components/ui";
import { DatePickerInput } from "@mantine/dates";
import { useState } from "react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { fromDateValue } from "@/lib/fecha";
import { useSubirResultado } from "../hooks/useResultadoMedico";
import { TIPO_RESULTADO_OPTIONS } from "../services/resultadoMedicoService";
import type { ConsultaMedica } from "../services/consultaMedicaService";

interface Props {
  opened: boolean;
  onClose: () => void;
  consulta: ConsultaMedica;
  historiaClinicaId: number;
}

export function SubirResultadoModal({
  opened,
  onClose,
  consulta,
  historiaClinicaId,
}: Props) {
  const contained = useContainedInput();
  const subir = useSubirResultado(consulta.id);

  const [tipo, setTipo] = useState("");
  const [descripcion, setDescripcion] = useState("");
  const [fecha, setFecha] = useState<Date | null>(new Date());
  const [archivo, setArchivo] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleClose = () => {
    setTipo("");
    setDescripcion("");
    setFecha(new Date());
    setArchivo(null);
    setError(null);
    onClose();
  };

  const handleSubmit = () => {
    if (!tipo) {
      setError("Seleccione el tipo de resultado.");
      return;
    }
    if (!descripcion.trim()) {
      setError("Ingrese una descripción.");
      return;
    }
    if (!archivo) {
      setError("Seleccione un archivo.");
      return;
    }
    if (!fecha) {
      setError("Ingrese la fecha del resultado.");
      return;
    }

    const fd = new FormData();
    fd.append("historia_clinica_id", String(historiaClinicaId));
    fd.append("consulta_medica_id", String(consulta.id));
    fd.append("tipo", tipo);
    fd.append("descripcion", descripcion.trim());
    fd.append("fecha_resultado", fromDateValue(fecha));
    fd.append("archivo", archivo);

    subir.mutate(fd, {
      onSuccess: handleClose,
      onError: () => setError("Error al subir el archivo. Intente de nuevo."),
    });
  };

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="Subir resultado médico"
      size="md"
    >
      <Stack gap="sm">
        <Select
          label="Tipo de resultado"
          placeholder="Seleccione"
          data={TIPO_RESULTADO_OPTIONS}
          {...contained}
          value={tipo}
          onChange={(v) => {
            setTipo(v ?? "");
            setError(null);
          }}
        />

        <Textarea
          label="Descripción"
          placeholder="Ej: Hemograma completo, Radiografía de tórax AP..."
          autosize
          minRows={2}
          {...contained}
          value={descripcion}
          onChange={(e) => {
            setDescripcion(e.currentTarget.value);
            setError(null);
          }}
        />

        <DatePickerInput
          label="Fecha del resultado"
          valueFormat="DD/MM/YYYY"
          {...contained}
          value={fecha}
          onChange={(v) => setFecha(v as Date | null)}
        />

        <FileInput
          label="Archivo"
          description="PDF, JPG o PNG — máximo 10 MB"
          placeholder="Seleccionar archivo..."
          accept="application/pdf,image/jpeg,image/png"
          {...contained}
          value={archivo}
          onChange={(f) => {
            setArchivo(f);
            setError(null);
          }}
        />

        {error && (
          <Text size="xs" c="red">
            {error}
          </Text>
        )}
      </Stack>
      <ModalFooter
        onCancel={handleClose}
        submitLabel="Subir resultado"
        submitting={subir.isPending}
        onSubmit={handleSubmit}
      />
    </SgthModal>
  );
}
