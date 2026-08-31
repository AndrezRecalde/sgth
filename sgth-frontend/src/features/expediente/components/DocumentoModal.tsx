"use client";

import { useState } from "react";
import {
  Modal,
  Button,
  Group,
  Stack,
  Select,
  Text,
  Textarea,
} from "@mantine/core";
import { Dropzone } from "@mantine/dropzone";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { IconUpload, IconX, IconFile } from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useDocumentoMutations } from "../hooks/useDocumentoMutations";
import { DatePickerInput } from "@mantine/dates";

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

const schema = z.object({
  tipo_documento: z.string().min(1, "Seleccione el tipo"),
  descripcion: z.string().optional(),
  fecha_vencimiento: z.string().optional(),
});

type FormData = z.infer<typeof schema>;

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
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

export function DocumentoModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint();
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
  } = useForm<FormData>({
    resolver: zodResolver(schema),
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

  const onSubmit = (values: FormData) => {
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
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Subir documento al expediente"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
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
            onReject={() => setArchivoError("Archivo no válido")}
            maxSize={5 * 1024 * 1024}
            accept={[
              "application/pdf",
              "image/jpeg",
              "image/png",
              "application/msword",
              "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            ]}
          >
            <Group justify="center" gap="xl" mih={80}>
              <Dropzone.Accept>
                <IconUpload size={28} color="var(--mantine-color-emerald-6)" />
              </Dropzone.Accept>
              <Dropzone.Reject>
                <IconX size={28} color="var(--mantine-color-red-6)" />
              </Dropzone.Reject>
              <Dropzone.Idle>
                <IconFile size={28} color="var(--mantine-color-dimmed)" />
              </Dropzone.Idle>
              <div>
                {archivo ? (
                  <Text size="sm" fw={500} c="emerald">
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
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
                description="Útil para pasaportes y documentos con caducidad"
                error={errors.fecha_vencimiento?.message}
              />
            )}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={subir.isPending}
              leftSection={<IconUpload size={14} />}
            >
              Subir documento
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
