"use client";

import { useState } from "react";
import {
  Group,
  Stack,
  Select,
  Text,
  Textarea,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import { Dropzone } from "@mantine/dropzone";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { IconUpload, IconX, IconFile } from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useDocumentoMutations } from "../hooks/useDocumentoMutations";
import { documentoSchema, type DocumentoFormData } from "../schemas/documento.schema";
import { DatePickerInput } from "@mantine/dates";
import { toDateValue, fromDateValueOrNull } from "@/lib/fecha"

const TIPO_OPTIONS = [
  {
    group: 'Identificación',
    items: [
      { value: 'cedula_identidad',    label: 'Cédula de identidad' },
      { value: 'papeleta_votacion',   label: 'Papeleta de votación' },
      { value: 'carnet_conadis',      label: 'Carnet CONADIS' },
    ],
  },
  {
    group: 'Académico',
    items: [
      { value: 'titulo_tercer_nivel', label: 'Título de tercer nivel' },
      { value: 'titulo_cuarto_nivel', label: 'Título de cuarto nivel (posgrado)' },
    ],
  },
  {
    group: 'Laboral',
    items: [
      { value: 'contrato_laboral',    label: 'Contrato laboral' },
      { value: 'nombramiento',        label: 'Nombramiento' },
      { value: 'certificado_trabajo_anterior', label: 'Certificado trabajo anterior' },
    ],
  },
  {
    group: 'Médico',
    items: [
      { value: 'certificado_medico',              label: 'Certificado médico' },
      { value: 'certificado_enfermedad_catastrofica', label: 'Certificado enfermedad catastrófica' },
    ],
  },
  {
    group: 'Otros',
    items: [
      { value: 'otro', label: 'Otro documento' },
    ],
  },
]

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
}

export function DocumentoModal({ opened, onClose, servidorId }: Props) {
  const contained = useContainedInput();
  const { subir } = useDocumentoMutations(servidorId);
  const [archivo, setArchivo] = useState<File | null>(null);
  const [archivoError, setArchivoError] = useState("");

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<DocumentoFormData>({
    resolver: zodResolver(documentoSchema),
    defaultValues: {
      tipo_documento: "",
      descripcion: "",
      fecha_vencimiento: "",
    },
  });

  const handleClose = () => {
    reset();
    setArchivo(null);
    setArchivoError("");
    onClose();
  };

  const onSubmit = (values: DocumentoFormData) => {
    if (!archivo) {
      setArchivoError("Seleccione un archivo para subir");
      return;
    }
    const formData = new FormData();
    formData.append("archivo", archivo);
    formData.append("tipo_documento", values.tipo_documento);
    if (values.descripcion) formData.append("descripcion", values.descripcion);
    if (values.fecha_vencimiento)
      formData.append("fecha_vencimiento", values.fecha_vencimiento);

    subir
      .mutateAsync(formData)
      .then(handleClose)
      .catch(() => {});
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
              data={TIPO_OPTIONS}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? "")}
              error={errors.tipo_documento?.message}
            />
          )}
        />

        <Dropzone
          onDrop={(files) => {
            setArchivo(files[0]);
            setArchivoError("");
          }}
          onReject={() =>
            setArchivoError("Solo se aceptan archivos PDF, JPG o PNG de hasta 5 MB")
          }
          maxSize={5 * 1024 * 1024}
          // Los mismos formatos que valida el backend (StoreDocumentoServidorRequest):
          // aceptaba Word aquí y el servidor lo rechazaba después de subirlo.
          accept={["application/pdf", "image/jpeg", "image/png"]}
        >
          <Group justify="center" gap="xl" mih={80}>
            <Dropzone.Accept>
              <IconUpload size={28} color="var(--sgth-accent)" />
            </Dropzone.Accept>
            <Dropzone.Reject>
              <IconX size={28} color="var(--mantine-color-red-6)" />
            </Dropzone.Reject>
            <Dropzone.Idle>
              <IconFile size={28} color="var(--mantine-color-dimmed)" />
            </Dropzone.Idle>
            <div>
              {archivo ? (
                <Text size="sm" fw={500}>
                  {archivo.name}
                </Text>
              ) : (
                <>
                  <Text size="sm" fw={500}>
                    Arrastra el archivo aquí o haz clic para seleccionar
                  </Text>
                  <Text size="xs" c="dimmed" mt={4}>
                    PDF, JPG, PNG — máx. 5MB
                  </Text>
                </>
              )}
            </div>
          </Group>
        </Dropzone>
        {archivoError && (
          <Text size="xs" c="red">
            {archivoError}
          </Text>
        )}

        <Textarea
          label="Descripción"
          placeholder="Descripción opcional del documento"
          rows={2}
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
              valueFormat="YYYY-MM-DD"
              clearable
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              description="Útil para pasaportes y documentos con caducidad"
              error={errors.fecha_vencimiento?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  );
}
