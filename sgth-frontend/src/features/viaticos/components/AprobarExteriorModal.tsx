"use client";

import { Group, NumberInput, Paper, Select, Stack, Text } from "@mantine/core";
import { FormModal } from "@/components/ui";
import { Controller, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { PAISES_OPTIONS } from "../constants/viatico.constants";
import { aprobarExteriorSchema, type AprobarExteriorFormData } from "../schemas/aprobacion.schema";
import { dolares } from "../utils/monto";
import type { ViaticoConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: ViaticoConRelaciones;
}

/*
| Aprobar un viaje al exterior: Financiero fija el coeficiente del país.
|
| La tarifa base y el nivel del servidor los resuelve el backend, que es quien
| fija el monto al aprobar; aquí solo se multiplica por el coeficiente para
| anticipar el resultado. El país se elige de la misma lista que en la
| solicitud: antes se escribía a mano.
*/
export function AprobarExteriorModal({ opened, onClose, viatico }: Props) {
  const contained = useContainedInput();
  const { aprobar } = useViaticoMutations();

  const tarifaBase = Number(viatico.calculo?.tarifa_diaria ?? 0);
  const noches = Number(viatico.noches ?? 1);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<AprobarExteriorFormData>({
    resolver: zodResolver(aprobarExteriorSchema),
    defaultValues: {
      pais_destino: (viatico.pais_destino as string | null) ?? "",
      coeficiente_exterior: 1,
    },
  });

  const coef = useWatch({ control, name: "coeficiente_exterior" }) ?? 1;
  const monto = Math.round(tarifaBase * coef * noches * 100) / 100;

  const onSubmit = (values: AprobarExteriorFormData) =>
    aprobar.mutate({ id: viatico.id, data: values }, { onSuccess: onClose });

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Aprobar viaje al exterior"
      size="md"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Aprobar viático"
      submitting={aprobar.isPending}
    >
      <Stack gap="md">
        <Controller
          name="pais_destino"
          control={control}
          render={({ field }) => (
            <Select
              label="País de destino"
              data={PAISES_OPTIONS}
              searchable
              {...contained}
              value={field.value || null}
              onChange={(v) => field.onChange(v ?? "")}
              error={errors.pais_destino?.message}
            />
          )}
        />

        <Controller
          name="coeficiente_exterior"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Coeficiente del país"
              description="Multiplica la tarifa base del exterior. Ej: 1.5"
              decimalScale={4}
              min={0.1}
              max={5}
              hideControls
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(typeof v === "number" ? v : 1)}
              error={errors.coeficiente_exterior?.message}
            />
          )}
        />

        <Paper withBorder radius="md" p="md" bg="var(--sgth-surface-sunken)">
          <Group justify="space-between">
            <Text size="sm" c="dimmed">Monto del viático</Text>
            <Text size="lg" fw={700}>{dolares(monto)}</Text>
          </Group>
          <Text size="xs" c="dimmed" mt={4}>
            {dolares(tarifaBase)} por noche × {coef.toFixed(4)} × {noches} {noches === 1 ? "noche" : "noches"}
          </Text>
        </Paper>
      </Stack>
    </FormModal>
  );
}
