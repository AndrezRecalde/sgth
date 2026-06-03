"use client";

import {
  Modal,
  Stack,
  Text,
  Badge,
  Table,
  Divider,
  Skeleton,
  ScrollArea,
  Card,
  Grid,
} from "@mantine/core";
import { useQuery } from "@tanstack/react-query";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { nominaService } from "../services/nominaService";
import type { Nomina } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  nomina: Nomina | null;
}

const ESTADO_COLORS: Record<string, string> = {
  borrador: "gray",
  en_proceso: "blue",
  cerrada: "orange",
  contabilizada: "violet",
  pagada: "emerald",
};

function formatMonto(v?: number | string | null): string {
  if (v === null || v === undefined) return "—";
  return `$${Number(v).toLocaleString("es-EC", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function NominaDetalleModal({ opened, onClose, nomina }: Props) {
  const { isMobile } = useMobileBreakpoint();

  const { data: detalle, isLoading } = useQuery({
    queryKey: ["nomina-detalle", nomina?.id],
    queryFn: () => nominaService.obtener(nomina!.id),
    enabled: !!nomina?.id && opened,
    staleTime: 0,
  });

  // roles de pago del detalle
  const roles = (detalle as Nomina)?.roles_pago ?? [];

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={`Detalle de nómina — ${nomina?.periodo ?? ""}`}
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : "xl"}
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
                <Badge
                  color={ESTADO_COLORS[nomina?.estado ?? ""] ?? "gray"}
                  variant="light"
                  size="sm"
                  mt={4}
                >
                  {nomina?.estado ?? "—"}
                </Badge>
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

          <Divider label="Roles de pago" labelPosition="left" />

          {roles.length === 0 ? (
            <Text size="sm" c="dimmed" ta="center">
              Sin roles de pago generados.
            </Text>
          ) : (
            <ScrollArea>
              <Table striped highlightOnHover withTableBorder>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>Cédula</Table.Th>
                    <Table.Th>Servidor</Table.Th>
                    <Table.Th ta="right">Ingresos</Table.Th>
                    <Table.Th ta="right">Descuentos</Table.Th>
                    <Table.Th ta="right">Neto</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {roles.map((r) => (
                    <Table.Tr key={r.id}>
                      <Table.Td>
                        <Text size="sm" ff="monospace">
                          {r.servidor?.cedula ?? "—"}
                        </Text>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm">
                          {[r.servidor?.apellido, r.servidor?.nombre]
                            .filter(Boolean)
                            .join(" ") || "—"}
                        </Text>
                      </Table.Td>
                      <Table.Td ta="right">
                        <Text size="sm" ff="monospace" c="emerald">
                          {formatMonto(r.total_ingresos)}
                        </Text>
                      </Table.Td>
                      <Table.Td ta="right">
                        <Text size="sm" ff="monospace" c="red">
                          {formatMonto(r.total_descuentos)}
                        </Text>
                      </Table.Td>
                      <Table.Td ta="right">
                        <Text size="sm" ff="monospace" fw={600}>
                          {formatMonto(r.total_neto)}
                        </Text>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </ScrollArea>
          )}
        </Stack>
      )}
    </Modal>
  );
}
