"use client";

import {
  Stack,
  Card,
  Text,
  Group,
  MultiSelect,
  Badge,
  Divider,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import { useForm, Controller } from "react-hook-form";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  // Necesita `todos_servidores`, que sólo viene en la vista de detalle.
  viatico: ViaticoConRelaciones;
}

export function ServidoresModal({ opened, onClose, viatico }: Props) {
  const contained = useContainedInput();
  const { actualizar } = useViaticoMutations();

  const d = viatico;

  // Servidor titular
  const titular = d.todos_servidores?.find((vs) => vs.es_titular);

  // Acompañantes actuales
  const acompanantesActuales = (d.todos_servidores ?? [])
    .filter((vs) => !vs.es_titular && vs.servidor?.id)
    .map((vs) => String(vs.servidor?.id));

  const { data: servidoresData } = useServidores({ per_page: 200 });

  const servidoresOptions = (servidoresData?.data ?? [])
    .filter((s) => s.id !== titular?.servidor?.id)
    .map((s) => ({
      value: String(s.id),
      label: [s.apellido, s.nombre].filter(Boolean).join(" "),
    }));

  const {
    control,
    handleSubmit,
    formState: { isSubmitting },
  } = useForm<{ acompanantes: string[] }>({
    defaultValues: {
      acompanantes: acompanantesActuales,
    },
  });

  const onSubmit = async (values: { acompanantes: string[] }) => {
    try {
      await actualizar.mutateAsync({
        id: viatico.id,
        data: {
          servidores_acompanantes: values.acompanantes.map(Number),
        } as Parameters<typeof actualizar.mutateAsync>[0]["data"],
      });
      onClose();
    } catch {
      // El hook de mutación ya notifica el error.
    }
  };

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Servidores en comisión"
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar cambios"
      submitting={isSubmitting}
    >
      <Stack gap="sm">
        {/* Titular — no editable */}
        <Card withBorder radius="md" p="xs" bg="blue.0">
          <Group gap="xs">
            <Badge size="xs" color="blue" variant="filled">
              Titular
            </Badge>
            <Text size="sm" fw={500}>
              {[titular?.servidor?.apellido, titular?.servidor?.nombre]
                .filter(Boolean)
                .join(" ") || "—"}
            </Text>
            <Text size="xs" c="dimmed">
              {titular?.servidor?.puesto?.cargo?.nombre ?? ""}
            </Text>
          </Group>
        </Card>

        <Divider label="Acompañantes" labelPosition="left" />

        <Controller
          name="acompanantes"
          control={control}
          render={({ field }) => (
            <MultiSelect
              label="Servidores acompañantes"
              description="Seleccione los servidores que participan
                en esta comisión junto al titular"
              placeholder="Buscar servidor..."
              data={servidoresOptions}
              searchable
              clearable
              {...contained}
              value={field.value}
              onChange={field.onChange}
            />
          )}
        />
      </Stack>
    </FormModal>
  );
}
