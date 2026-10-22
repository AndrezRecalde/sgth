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
import { useQuery } from "@tanstack/react-query";
import { useContainedInput } from "@/hooks/useContainedInput";
import { formatFechaMes, fromDateValue } from "@/lib/fecha";
import { useSubirResultado } from "../hooks/useResultadoMedico";
import { TIPO_RESULTADO_OPTIONS } from "../services/resultadoMedicoService";
import { consultaMedicaService } from "../services/consultaMedicaService";
import type { ConsultaMedica } from "../services/consultaMedicaService";

/** Los topes del servidor (`ResultadoMedicoController::store`). */
const ARCHIVO_MAX_MB = 10;
const DESCRIPCION_MAX = 500;

/** Valor del selector de consulta para un resultado que no es de ninguna. */
const SIN_CONSULTA = "ninguna";

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
  const subir = useSubirResultado();

  // A qué consulta pertenece. Por defecto, la abierta; pero un laboratorio
  // que llega una semana después es de la consulta que lo pidió, y antes
  // solo se podía colgar de esa abriendo su panel. Se ofrecen las últimas
  // del paciente, o ninguna.
  const { data: delPaciente } = useQuery({
    queryKey: ["consultas", "para-resultado", historiaClinicaId],
    queryFn: () =>
      consultaMedicaService.listarPorHistoria(historiaClinicaId, { per_page: 20 }),
    enabled: opened && !!historiaClinicaId,
    staleTime: 1000 * 60,
  });
  const anteriores = (delPaciente?.consultas ?? []).filter((c) => c.id !== consulta.id);
  const opcionesConsulta = [
    { value: String(consulta.id), label: `Esta consulta (${formatFechaMes(consulta.fecha_consulta)})` },
    ...anteriores.map((c) => ({
      value: String(c.id),
      label: `Consulta del ${formatFechaMes(c.fecha_consulta)}`,
    })),
    { value: SIN_CONSULTA, label: "Sin consulta" },
  ];
  const [consultaSel, setConsultaSel] = useState(String(consulta.id));

  const [tipo, setTipo] = useState("");
  const [descripcion, setDescripcion] = useState("");
  // El selector de Mantine v9 devuelve una CADENA `YYYY-MM-DD` en cuanto se
  // elige una fecha; solo el valor inicial es un `Date`.
  const [fecha, setFecha] = useState<Date | string | null>(new Date());
  const [archivo, setArchivo] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleClose = () => {
    setConsultaSel(String(consulta.id));
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
    // Se comprueba antes de enviarlo: subir 30 MB para que el servidor los
    // rechace al final hacía esperar toda la subida para nada.
    if (archivo.size > ARCHIVO_MAX_MB * 1024 * 1024) {
      setError(
        `El archivo pesa ${(archivo.size / 1024 / 1024).toFixed(1)} MB; ` +
          `el máximo es ${ARCHIVO_MAX_MB} MB.`,
      );
      return;
    }
    if (!fecha) {
      setError("Ingrese la fecha del resultado.");
      return;
    }

    const fd = new FormData();
    fd.append("historia_clinica_id", String(historiaClinicaId));
    if (consultaSel !== SIN_CONSULTA) fd.append("consulta_medica_id", consultaSel);
    fd.append("tipo", tipo);
    fd.append("descripcion", descripcion.trim());
    fd.append("fecha_resultado", fromDateValue(fecha));
    fd.append("archivo", archivo);

    // El fallo lo avisa el hook con el motivo del servidor. Aquí se pintaba
    // además «Error al subir el archivo. Intente de nuevo.», que tapaba ese
    // motivo y pedía repetir algo que iba a fallar igual.
    subir.mutate(fd, { onSuccess: handleClose });
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
          label="Consulta"
          description="Si es de lo que se pidió en otra consulta, elíjala: así aparece en ella."
          data={opcionesConsulta}
          allowDeselect={false}
          {...contained}
          value={consultaSel}
          onChange={(v) => setConsultaSel(v ?? String(consulta.id))}
        />

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
          maxLength={DESCRIPCION_MAX}
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
          onChange={(v) => setFecha(v)}
        />

        <FileInput
          label="Archivo"
          description={`PDF, JPG o PNG — máximo ${ARCHIVO_MAX_MB} MB`}
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
