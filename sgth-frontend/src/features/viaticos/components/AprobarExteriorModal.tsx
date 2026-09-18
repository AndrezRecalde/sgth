"use client";

import {
  Stack,
  Text,
  Group,
  NumberInput,
  TextInput,
  Card,
  Divider,
} from "@mantine/core";
import { FormModal } from "@/components/ui";
import { useForm, Controller, useWatch } from "react-hook-form";
import { useQueryClient } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import type { ViaticoConRelaciones } from "@/types/api";

const schema = z.object({
  pais_destino: z.string().min(1, "Requerido"),
  coeficiente_exterior: z.number().min(0.1, "Mínimo 0.1").max(5, "Máximo 5.0"),
});

type FormData = z.infer<typeof schema>;

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: ViaticoConRelaciones;
}

export function AprobarExteriorModal({ opened, onClose, viatico }: Props) {
  const qc = useQueryClient();
  const contained = useContainedInput();
  const { aprobar } = useViaticoMutations();

  // La tarifa base del exterior y el nivel del servidor los resuelve el
  // backend, que es quien fija el monto al aprobar: aquí solo se multiplica por
  // el coeficiente para anticipar el resultado.
  const tarifaBase = Number(viatico.calculo?.tarifa_diaria ?? 0);

  const noches = Number(viatico.noches ?? 1);

  const {
    control,
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      pais_destino: (viatico.pais_destino as string) ?? "",
      coeficiente_exterior: 1.0,
    },
  });

  const coef = useWatch({ control, name: "coeficiente_exterior" }) ?? 1;
  const montoCalculado = Math.round(tarifaBase * coef * noches * 100) / 100;

  const onSubmit = async (values: FormData) => {
    try {
      await aprobar.mutateAsync({
        id: viatico.id,
        data: {
          coeficiente_exterior: values.coeficiente_exterior,
          pais_destino: values.pais_destino,
        },
      });
      // Invalidar explícitamente el query del viático
      // por id numérico Y por codigo_viatico string
      qc.invalidateQueries({ queryKey: ["viatico"] });
      qc.invalidateQueries({ queryKey: ["viaticos"] });
      onClose();
    } catch {
      // El hook de mutación ya notifica el error.
    }
  };

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Aprobar viático internacional"
      size="md"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Aprobar viático"
      submitting={isSubmitting || aprobar.isPending}
    >
      <Stack gap="sm">
        <Card withBorder radius="md" p="sm" bg="var(--sgth-surface-sunken)">
          <Text size="xs" c="dimmed">
            Tarifa base aplicable
          </Text>
          <Text size="sm" fw={700} c="ocean">
            ${tarifaBase.toFixed(2)} por noche
          </Text>
          <Text size="xs" c="dimmed" mt={4}>
            {noches} noche(s) de comisión
          </Text>
        </Card>

        <TextInput
          label="País de destino"
          placeholder="Ej: Colombia"
          {...contained}
          {...register("pais_destino")}
          error={errors.pais_destino?.message}
        />

        <Controller
          name="coeficiente_exterior"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Coeficiente"
              description="Factor multiplicador según el país (Ej: 1.5)"
              placeholder="1.0"
              decimalScale={4}
              min={0.1}
              max={5}
              step={0.1}
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(typeof v === "number" ? v : 1)}
              error={errors.coeficiente_exterior?.message}
            />
          )}
        />

        <Divider />

        <Card withBorder radius="md" p="sm">
          <Group justify="space-between">
            <Text size="sm" c="dimmed">
              Monto calculado:
            </Text>
            <Text size="lg" fw={700} c="emerald">
              ${montoCalculado.toFixed(2)}
            </Text>
          </Group>
          <Text size="xs" c="dimmed" mt={4}>
            ${tarifaBase.toFixed(2)} ×{coef.toFixed(4)} ×{noches} noches
          </Text>
        </Card>
      </Stack>
    </FormModal>
  );
}
