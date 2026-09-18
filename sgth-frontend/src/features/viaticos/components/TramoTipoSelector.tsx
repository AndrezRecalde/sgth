"use client";

import { Alert, Grid, Group, Radio, Stack, Text } from "@mantine/core";
import { Controller, type Control, type FieldErrors } from "react-hook-form";
import type { TramoFormData } from "../schemas/viatico.schema";

interface Props {
  control: Control<TramoFormData>;
  errors: FieldErrors<TramoFormData>;
  esPrimerTramo: boolean;
  /** El tramo vuelve al lugar de donde salió la ida: es el regreso. */
  esRegreso: boolean;
  /** Adónde llega el tramo, para la pregunta. */
  destino: string;
  /** De donde salió el viaje. */
  base: string;
}

const OPCIONES = [
  { value: "destino", label: "Sí", description: "Aquí realiza actividades de la comisión." },
  { value: "escala", label: "No", description: "Solo pasa por aquí, sin actividades." },
];

/*
| Lo único que se pregunta sobre el tipo del tramo.
|
| La ida (el primero) y el regreso (el último, si vuelve al lugar de salida)
| los deduce el sistema. De los demás, en un viaje a varios lugares, solo
| quien viaja sabe si trabajó ahí o solo pasó: eso decide qué lugares salen
| como destino en la solicitud. Antes se elegía entre «destino», «parada o
| escala» y «regreso», una distinción que se podía contradecir con el resto
| del itinerario.
*/
export function TramoTipoSelector({ control, errors, esPrimerTramo, esRegreso, destino, base }: Props) {
  if (esPrimerTramo) {
    return (
      <Alert color="ocean" variant="light" p="xs">
        Es el primer tramo: la ida del viaje.
      </Alert>
    );
  }

  if (esRegreso) {
    return (
      <Alert color="ocean" variant="light" p="xs">
        Vuelve a {base}: es el regreso del viaje.
      </Alert>
    );
  }

  return (
    <Controller
      name="tipo_tramo"
      control={control}
      render={({ field }) => (
        <Radio.Group
          label={destino ? `¿Realiza actividades en ${destino}?` : "¿Realiza actividades en este lugar?"}
          value={field.value === "escala" ? "escala" : "destino"}
          onChange={field.onChange}
          error={errors.tipo_tramo?.message}
        >
          <Grid mt="xs">
            {OPCIONES.map((o) => (
              <Grid.Col key={o.value} span={{ base: 12, sm: 6 }}>
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
