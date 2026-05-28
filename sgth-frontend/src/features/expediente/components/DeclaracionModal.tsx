"use client";

import {
  Modal,
  Button,
  Group,
  Stack,
  TextInput,
  Select,
  Textarea,
} from "@mantine/core";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useDeclaracionMutations } from "../hooks/useDeclaracionMutations";
import {
  declaracionSchema,
  type DeclaracionFormData,
} from "../schemas/declaracion.schema";
import { DatePickerInput } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import { IconCheck } from "@tabler/icons-react";
import React, { useEffect } from "react";
import { expedienteService } from "../services/expedienteService";

const TIPO_OPTIONS = [
  { value: "inicio_gestion", label: "Inicio de gestión" },
  { value: "periodica",      label: "Periódica" },
  { value: "fin_gestion",    label: "Fin de gestión" },
];

interface Props {
  opened: boolean;
  onClose: () => void;
  servidorId: number;
  initialValues?: {
    id:               number;
    tipo_declaracion: string;
    fecha_declaracion: string;
    codigo_barras:     string;
    observaciones?:   string | null;
  } | null;
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

export function DeclaracionModal({ opened, onClose, servidorId, initialValues }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { crear } = useDeclaracionMutations(servidorId);
  const qc = useQueryClient();

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<DeclaracionFormData>({
    resolver: zodResolver(declaracionSchema),
    defaultValues: {
      tipo_declaracion: "inicio_gestion",
      fecha_declaracion: "",
      codigo_barras: "",
      observaciones: "",
    },
  });

  useEffect(() => {
    if (initialValues) {
      reset({
        tipo_declaracion:  initialValues.tipo_declaracion as DeclaracionFormData["tipo_declaracion"],
        fecha_declaracion: initialValues.fecha_declaracion
          ? initialValues.fecha_declaracion.split("T")[0] : "",
        codigo_barras:     initialValues.codigo_barras ?? "",
        observaciones:     initialValues.observaciones ?? "",
      });
    } else {
      reset({
        tipo_declaracion:  "inicio_gestion",
        fecha_declaracion: "",
        codigo_barras:     "",
        observaciones:     "",
      });
    }
  }, [initialValues, reset]);

  const handleClose = () => {
    reset();
    onClose();
  };

  const isEditing = !!initialValues;

  const onSubmit = (values: DeclaracionFormData) => {
    const mutation = isEditing
      ? expedienteService.editarDeclaracion(
          servidorId, initialValues!.id,
          values as Record<string, unknown>
        ).then(() => {
          qc.invalidateQueries({
            queryKey: ["declaraciones", servidorId]
          });
          notifications.show({
            title:   "Declaración actualizada",
            message: "La declaración fue actualizada correctamente.",
            color:   "emerald",
            icon:    React.createElement(IconCheck, { size: 16 }),
          });
          handleClose();
        })
      : crear.mutateAsync(values as Record<string, unknown>)
          .then(() => { reset(); onClose(); });

    mutation.catch(() => {});
  };

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? "Editar declaración" : "Registrar declaración juramentada"}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
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
                valueFormat="YYYY-MM-DD"
                clearable
                {...contained}
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
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
          <Textarea
            label="Observaciones"
            placeholder="Opcional"
            rows={3}
            {...contained}
            {...register("observaciones")}
            error={errors.observaciones?.message}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={crear.isPending}
            >
              {initialValues ? "Actualizar" : "Registrar declaración"}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}

