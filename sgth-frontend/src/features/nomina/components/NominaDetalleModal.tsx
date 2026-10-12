"use client";

import { useState } from "react";
import { TONO_NOMINA } from "../constants/estadoNomina";
import {
  Alert,
  Button,
  Group,
  Stack,
  Text,
  Skeleton,
  Card,
  Grid,
} from "@mantine/core";
import { IconDownload, IconInfoCircle } from "@tabler/icons-react";
import { SectionHeading, SgthModal, SgthTable, StatusBadge, notificar } from "@/components/ui";
import { guardarArchivo } from "@/lib/archivo";
import { getApiErrorMessage } from "@/types/api";
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
  const [descargando, setDescargando] = useState(false);

  /** Por servidor, para compararla con la de Financiero (fase 7.3c). */
  const descargar = async () => {
    if (!nomina) return;
    setDescargando(true);
    try {
      guardarArchivo(await nominaService.excel(nomina.id), `nomina_${nomina.periodo}.xlsx`);
    } catch (e) {
      notificar.error("No se pudo descargar el Excel", getApiErrorMessage(e));
    } finally {
      setDescargando(false);
    }
  };

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
          {nomina?.referencial && (
            <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />} title="Nómina referencial">
              Se calcula en paralelo con la de Financiero, que es la que paga: sobre la remuneración de cada vínculo,
              por sus días, con las novedades del mes. Al cerrarla se congela para compararla; no sale al ERP ni a
              los servidores.
            </Alert>
          )}

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

          <SectionHeading
            title="Roles de pago"
            action={
              <Group gap="xs">
                <Button
                  size="xs"
                  variant="default"
                  leftSection={<IconDownload size={14} />}
                  loading={descargando}
                  disabled={!roles.length}
                  onClick={descargar}
                >
                  Excel por servidor
                </Button>
              </Group>
            }
          />

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
