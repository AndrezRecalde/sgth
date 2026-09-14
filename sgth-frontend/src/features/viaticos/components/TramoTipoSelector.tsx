"use client";

import {
  Stack,
  Grid,
  Card,
  Text,
  Group,
  Alert,
  UnstyledButton,
  Box,
} from "@mantine/core";
import { Controller, type Control, type FieldErrors } from "react-hook-form";
import type { TramoFormData } from "../schemas/viatico.schema";

interface Props {
  control: Control<TramoFormData>;
  errors: FieldErrors<TramoFormData>;
  esPrimerTramo: boolean;
}

const OPCIONES = [
  {
    value: "destino",
    label: "DESTINO",
    description: "Realizas actividades de la comisión en esta ciudad.",
  },
  {
    value: "escala",
    label: "PARADA / ESCALA",
    description: "Solo pasas por esta ciudad, no realizas actividades.",
  },
  {
    value: "regreso",
    label: "REGRESO",
    description: "Último tramo de vuelta a tu ciudad base.",
  },
];

export function TramoTipoSelector({
  control,
  errors,
  esPrimerTramo,
}: Props) {
  if (esPrimerTramo) {
    return (
      <Alert color="ocean" variant="light" p="xs">
        <Group gap="xs">
          <Text size="xs" fw={600} c="ocean">
            Tramo de IDA
          </Text>
          <Text size="xs" c="dimmed">
            — se asigna automáticamente como el primer tramo
          </Text>
        </Group>
      </Alert>
    );
  }

  return (
    <Controller
      name="tipo_tramo"
      control={control}
      rules={{ required: "Debe seleccionar el tipo de tramo" }}
      render={({ field }) => (
        <Stack gap="xs">
          <Text size="xs" c="dimmed">
            Selecciona el rol de este tramo en tu itinerario:
          </Text>
          <Grid>
            {OPCIONES.map((opt) => {
              const selected = field.value === opt.value;
              return (
                <Grid.Col key={opt.value} span={{ base: 12, sm: 4 }}>
                  <UnstyledButton
                    onClick={() => field.onChange(opt.value)}
                    style={{ width: "100%" }}
                  >
                    <Card
                      withBorder
                      radius="md"
                      p="sm"
                      style={{
                        // El tipo de tramo es una categoría: la selección se marca con el acento, no con un color por tipo.
                        borderColor: selected ? "var(--sgth-accent)" : undefined,
                        borderWidth: selected ? 2 : 1,
                        background: selected ? "var(--sgth-accent-light)" : undefined,
                        cursor: "pointer",
                        transition: "all 0.15s ease",
                      }}
                    >
                      <Group justify="flex-end" mb={4}>
                        <Box
                          style={{
                            width: 18,
                            height: 18,
                            borderRadius: 4,
                            border: selected
                              ? "none"
                              : "2px solid var(--sgth-border-strong)",
                            background: selected
                              ? "var(--sgth-accent)"
                              : "var(--mantine-color-body)",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            flexShrink: 0,
                          }}
                        >
                          {selected && (
                            <Text
                              size="xs"
                              c="white"
                              fw={700}
                              style={{ lineHeight: 1 }}
                            >
                              V
                            </Text>
                          )}
                        </Box>
                      </Group>
                      <Text
                        size="xs"
                        fw={700}
                        c={selected ? "var(--sgth-accent-text)" : undefined}
                        mb={4}
                      >
                        {opt.label}
                      </Text>
                      <Text size="xs" c="dimmed" lh={1.4}>
                        {opt.description}
                      </Text>
                    </Card>
                  </UnstyledButton>
                </Grid.Col>
              );
            })}
          </Grid>
          {errors.tipo_tramo && (
            <Text size="xs" c="red">
              {errors.tipo_tramo.message as string}
            </Text>
          )}
        </Stack>
      )}
    />
  );
}
