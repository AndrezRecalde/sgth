"use client";

import { Alert, Grid, Group, Radio, Stack, Text } from "@mantine/core";
import { Controller, type Control, type FieldErrors } from "react-hook-form";
import type { TramoFormData } from "../schemas/viatico.schema";

interface Props {
  control: Control<TramoFormData>;
  errors: FieldErrors<TramoFormData>;
  esPrimerTramo: boolean;
}

const OPCIONES = [
  { value: "destino", label: "Destino", description: "Aquí se hacen actividades de la comisión." },
  { value: "escala", label: "Parada o escala", description: "Solo se pasa por aquí, sin actividades." },
  { value: "regreso", label: "Regreso", description: "El último tramo, de vuelta a Esmeraldas." },
];

/*
| Qué es el tramo en el viaje. El primero siempre es la ida.
|
| Con `Radio.Card`: se elige con el teclado, se anuncia como opción y marca
| la selección con el color del tema. Antes eran tarjetas con una casilla
| dibujada a mano y una «V» como marca.
*/
export function TramoTipoSelector({ control, errors, esPrimerTramo }: Props) {
  if (esPrimerTramo) {
    return (
      <Alert color="ocean" variant="light" p="xs">
        Es el primer tramo: se registra como la ida.
      </Alert>
    );
  }

  return (
    <Controller
      name="tipo_tramo"
      control={control}
      render={({ field }) => (
        <Radio.Group value={field.value ?? null} onChange={field.onChange} error={errors.tipo_tramo?.message}>
          <Grid>
            {OPCIONES.map((o) => (
              <Grid.Col key={o.value} span={{ base: 12, sm: 4 }}>
                <Radio.Card value={o.value} radius="md" p="sm" h="100%">
                  <Group wrap="nowrap" align="flex-start" gap="sm">
                    <Radio.Indicator />
                    <Stack gap={2}>
                      <Text size="sm" fw={600}>{o.label}</Text>
                      <Text size="xs" c="dimmed">{o.description}</Text>
                    </Stack>
                  </Group>
                </Radio.Card>
              </Grid.Col>
            ))}
          </Grid>
        </Radio.Group>
      )}
    />
  );
}
