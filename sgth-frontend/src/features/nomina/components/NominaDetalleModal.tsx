"use client";

import { TONO_NOMINA } from "../constants/estadoNomina";
import {
  Stack,
  Text,
  Skeleton,
  Card,
  Grid,
} from "@mantine/core";
import { SectionHeading, SgthModal, SgthTable, StatusBadge } from "@/components/ui";
import { columnasRolesPago, formatMonto } from "./rolesPago.columns";
import { useQuery } from "@tanstack/react-query";
import { nominaService } from "../services/nominaService";
import type { Nomina } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  nomina: Nomina | null;
}


export function NominaDetalleModal({ opened, onClose, nomina }: Props) {
  const { data: detalle, isLoading } = useQuery({
    queryKey: ["nomina-detalle", nomina?.id],
    queryFn: () => nominaService.obtener(nomina!.id),
    enabled: !!nomina?.id && opened,
    staleTime: 0,
  });

  // roles de pago del detalle
  const roles = (detalle as Nomina)?.roles_pago ?? [];

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={`Detalle de nómina — ${nomina?.periodo ?? ""}`}
      size="xl"
    >
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={80} />
          <Skeleton height={300} />
        </Stack>
      ) : (
        <Stack gap="md">
          {/* Resumen */}
          <Grid>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Card withBorder radius="md" p="sm" ta="center">
                <Text size="xs" c="dimmed">
                  Estado
                </Text>
                <StatusBadge tone={TONO_NOMINA[nomina?.estado ?? ''] ?? 'neutral'} mt={4} mx="auto">
                  {nomina?.estado ?? "—"}
                </StatusBadge>
              </Card>
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Card withBorder radius="md" p="sm" ta="center">
                <Text size="xs" c="dimmed">
                  Total ingresos
                </Text>
                <Text fw={600} c="emerald" size="sm" mt={4}>
                  {formatMonto(nomina?.total_ingresos)}
                </Text>
              </Card>
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Card withBorder radius="md" p="sm" ta="center">
                <Text size="xs" c="dimmed">
                  Total descuentos
                </Text>
                <Text fw={600} c="red" size="sm" mt={4}>
                  {formatMonto(nomina?.total_descuentos)}
                </Text>
              </Card>
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Card withBorder radius="md" p="sm" ta="center">
                <Text size="xs" c="dimmed">
                  Neto a pagar
                </Text>
                <Text fw={700} size="sm" mt={4}>
                  {formatMonto(nomina?.total_neto)}
                </Text>
              </Card>
            </Grid.Col>
          </Grid>

          <SectionHeading title="Roles de pago" />

          {roles.length === 0 ? (
            <Text size="sm" c="dimmed" ta="center">
              Sin roles de pago generados.
            </Text>
          ) : (
            <SgthTable records={roles} columns={columnasRolesPago} />
          )}
        </Stack>
      )}
    </SgthModal>
  );
}
