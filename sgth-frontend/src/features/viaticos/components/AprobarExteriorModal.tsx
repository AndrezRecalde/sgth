"use client";

import {
  Modal,
  Stack,
  Text,
  Group,
  Button,
  NumberInput,
  TextInput,
  Card,
  ThemeIcon,
  Divider,
} from "@mantine/core";
import { IconCheck, IconWorld } from "@tabler/icons-react";
import { useForm, Controller, useWatch } from "react-hook-form";
import { useQueryClient } from "@tanstack/react-query";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import type { Viatico } from "@/types/api";

const schema = z.object({
  pais_destino: z.string().min(1, "Requerido"),
  coeficiente_exterior: z.number().min(0.1, "Mínimo 0.1").max(5, "Máximo 5.0"),
});

type FormData = z.infer<typeof schema>;

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
}

const TARIFA_DIGNATARIO = 220.0;
const TARIFA_SERVIDOR = 185.0;

export function AprobarExteriorModal({ opened, onClose, viatico }: Props) {
  const qc = useQueryClient();
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { aprobar } = useViaticoMutations();

  type ViaticoConServidor = Viatico & {
    puesto?:    { rol_puesto?: string } | null
    servidor?:  {
      puesto?: { rol_puesto?: string } | null
    } | null
  }
  const v = viatico as ViaticoConServidor
  const esDignatario =
    v.puesto?.rol_puesto === 'dignatario' ||
    v.servidor?.puesto?.rol_puesto === 'dignatario'

  const tarifaBase = esDignatario ? TARIFA_DIGNATARIO : TARIFA_SERVIDOR;

  const totalDias = Number(viatico.total_dias ?? 1);

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
  const montoCalculado = Math.round(tarifaBase * coef * totalDias * 100) / 100;

  const onSubmit = async (values: FormData) => {
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
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="sm">
            <IconWorld size={14} />
          </ThemeIcon>
          <Text fw={600}>Aprobar viático internacional</Text>
        </Group>
      }
      size="md"
      radius="xl"
      fullScreen={isMobile}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Card withBorder radius="md" p="sm" bg="blue.0">
            <Text size="xs" c="dimmed">
              Tarifa base aplicable
            </Text>
            <Text size="sm" fw={700} c="blue">
              {esDignatario ? "Dignatario" : "Servidor"}: $
              {tarifaBase.toFixed(2)}/día
            </Text>
            <Text size="xs" c="dimmed" mt={4}>
              {totalDias} día(s) de comisión
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
              ${tarifaBase.toFixed(2)} ×{coef.toFixed(4)} ×{totalDias} días
            </Text>
          </Card>

          <Group justify="flex-end">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              loading={isSubmitting || aprobar.isPending}
              leftSection={<IconCheck size={14} />}
            >
              Aprobar viático
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
