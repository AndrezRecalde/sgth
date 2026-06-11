"use client";

import {
  Modal,
  Stack,
  Grid,
  Select,
  Textarea,
  Button,
  Group,
  Text,
  ThemeIcon,
} from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import "@mantine/dates/styles.css";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { IconPencil } from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { viaticoSchema, type ViaticoFormData } from "../schemas/viatico.schema";
import type { Viatico } from "@/types/api";

const ZONA_OPTIONS = [
  { value: "dentro_provincia", label: "Dentro de la provincia" },
  { value: "fuera_provincia", label: "Fuera de la provincia" },
  { value: "exterior", label: "Exterior (internacional)" },
];

const MODALIDAD_OPTIONS = [
  { value: "total", label: "Anticipo (70% del monto calculado)" },
  { value: "sin_anticipo", label: "Sin anticipo" },
];

const fromDateTime = (d: Date | null | string): string => {
  if (!d) return "";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (isNaN(dt.getTime())) return "";

  const year = dt.getFullYear();
  const month = String(dt.getMonth() + 1).padStart(2, "0");
  const day = String(dt.getDate()).padStart(2, "0");
  const hours = String(dt.getHours()).padStart(2, "0");
  const minutes = String(dt.getMinutes()).padStart(2, "0");

  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
  onSuccess?: () => void;
}

export function ViaticoEditModal({
  opened,
  onClose,
  viatico,
  onSuccess,
}: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { actualizar } = useViaticoMutations();

  const {
    control,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: {
      zona:
        (viatico.zona as "dentro_provincia" | "fuera_provincia" | "exterior") ??
        "fuera_provincia",
      datetime_salida: (viatico.datetime_salida as string) ?? "",
      datetime_llegada: (viatico.datetime_llegada as string) ?? "",
      justificacion: (viatico.justificacion as string) ?? "",
      modalidad_anticipo:
        (viatico.modalidad_anticipo as "total" | "parcial" | "sin_anticipo") ??
        "total",
      monto_calculado: null,
      tipo_viaje: null,
      pais_destino: null,
      servidores_acompanantes: [],
    },
  });

  const onSubmit = async (values: ViaticoFormData) => {
    await actualizar.mutateAsync({
      id: viatico.id,
      data: {
        zona: values.zona,
        datetime_salida: values.datetime_salida,
        datetime_llegada: values.datetime_llegada,
        justificacion: values.justificacion,
        modalidad_anticipo: values.modalidad_anticipo,
      },
    });
    onSuccess?.();
    onClose();
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="sm">
            <IconPencil size={14} />
          </ThemeIcon>
          <Text fw={600}>Editar información del viático</Text>
        </Group>
      }
      size="lg"
      radius="xl"
      fullScreen={isMobile}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="zona"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Zona geográfica"
                    data={ZONA_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? "fuera_provincia")}
                    error={errors.zona?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="modalidad_anticipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Modalidad de anticipo"
                    data={MODALIDAD_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) => field.onChange(v ?? "total")}
                    error={errors.modalidad_anticipo?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="datetime_salida"
                control={control}
                render={({ field }) => (
                  <DateTimePicker
                    label="Fecha y hora de salida"
                    description="¿Cuándo sale de Esmeraldas?"
                    valueFormat="DD/MM/YYYY HH:mm"
                    timePickerProps={{
                      withDropdown: true,
                      popoverProps: { withinPortal: false },
                      format: "24h",
                    }}
                    {...contained}
                    value={field.value ? new Date(field.value) : null}
                    onChange={(v) => field.onChange(fromDateTime(v))}
                    error={errors.datetime_salida?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="datetime_llegada"
                control={control}
                render={({ field }) => (
                  <DateTimePicker
                    label="Fecha y hora de regreso"
                    description="¿Cuándo regresa a Esmeraldas?"
                    valueFormat="DD/MM/YYYY HH:mm"
                    timePickerProps={{
                      withDropdown: true,
                      popoverProps: { withinPortal: false },
                      format: "24h",
                    }}
                    {...contained}
                    value={field.value ? new Date(field.value) : null}
                    onChange={(v) => field.onChange(fromDateTime(v))}
                    error={errors.datetime_llegada?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          <Controller
            name="justificacion"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Justificación del viaje"
                description="Explique el objetivo de la comisión"
                autosize
                minRows={3}
                maxRows={6}
                {...contained}
                value={field.value}
                onChange={(e) => field.onChange(e.currentTarget.value)}
                error={errors.justificacion?.message}
              />
            )}
          />

          <Group justify="flex-end" mt="xs">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" color="blue" loading={isSubmitting}>
              Guardar cambios
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
