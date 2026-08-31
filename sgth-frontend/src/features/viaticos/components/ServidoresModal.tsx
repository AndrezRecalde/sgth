"use client";

import {
  Modal,
  Stack,
  Card,
  Text,
  Group,
  Button,
  MultiSelect,
  ThemeIcon,
  Badge,
  Divider,
} from "@mantine/core";
import { IconUsers, IconCheck } from "@tabler/icons-react";
import { useForm, Controller } from "react-hook-form";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import type { Viatico, ViaticoConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
}

export function ServidoresModal({ opened, onClose, viatico }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { actualizar } = useViaticoMutations();

  const d = viatico as ViaticoConRelaciones;

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
    await actualizar.mutateAsync({
      id: viatico.id,
      data: {
        servidores_acompanantes: values.acompanantes.map(Number),
      } as Parameters<typeof actualizar.mutateAsync>[0]["data"],
    });
    onClose();
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="teal" variant="light" size="sm">
            <IconUsers size={14} />
          </ThemeIcon>
          <Text fw={600}>Servidores en comisión</Text>
        </Group>
      }
      size="md"
      radius="xl"
      fullScreen={isMobile}
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
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

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="teal"
              loading={isSubmitting}
              leftSection={<IconCheck size={14} />}
            >
              Guardar cambios
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
