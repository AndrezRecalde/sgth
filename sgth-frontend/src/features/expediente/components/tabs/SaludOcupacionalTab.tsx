"use client";

import {
  Stack,
  Group,
  Text,
  Badge,
  Button,
  Skeleton,
} from "@mantine/core";
import { IconDownload, IconStethoscope } from "@tabler/icons-react";
import { EmptyState, SgthTable, StatusBadge } from "@/components/ui";
import { useSolicitudesCertificacion } from "@/features/dispensario/hooks/useSolicitudCertificacion";
import { usePdfFemo } from "@/features/dispensario/hooks/usePdfFemo";
import {
  TIPO_EVENTO_OPTIONS,
  TONO_ESTADO_SOLICITUD,
  TONO_DICTAMEN,
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
} from "@/features/dispensario/services/solicitudCertificacionService";
import type { SolicitudCertificacion } from "@/features/dispensario/services/solicitudCertificacionService";
import type { DataTableColumn } from "mantine-datatable";

interface Props {
  servidorId: number;
}

export function SaludOcupacionalTab({ servidorId }: Props) {
  const { data, isLoading } = useSolicitudesCertificacion({
    servidor_id: servidorId,
    per_page: 50,
  });
  const solicitudes = data?.data ?? [];
  const { descargarFemo, loading: descargando } = usePdfFemo();

  const getLabelTipo = (tipo: string) =>
    TIPO_EVENTO_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo;

  const columns: DataTableColumn<SolicitudCertificacion>[] = [
    {
      accessor: "tipo_evento",
      title: "Tipo de evaluación",
      render: (s) => (
        <Badge size="sm" variant="light" color="blue">
          {getLabelTipo(s.tipo_evento)}
        </Badge>
      ),
    },
    {
      accessor: "created_at",
      title: "Fecha de solicitud",
      width: 130,
      render: (s) => (
        <Text size="sm">
          {new Date(s.created_at).toLocaleDateString("es-EC", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
          })}
        </Text>
      ),
    },
    {
      accessor: "estado",
      title: "Estado",
      width: 140,
      render: (s) => (
        <Stack gap={4}>
          <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? "neutral"}>
            {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
          </StatusBadge>
          {s.dictamen && (
            <StatusBadge size="xs" tone={TONO_DICTAMEN[s.dictamen] ?? "neutral"}>
              {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
            </StatusBadge>
          )}
        </Stack>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 90,
      render: (s) =>
        s.ficha_femo_id ? (
          <Button
            size="xs"
            variant="light"
            color="blue"
            leftSection={<IconDownload size={13} />}
            loading={descargando}
            onClick={() =>
              descargarFemo(
                s.ficha_femo_id!,
                `femo-${s.cedula_paciente}-${s.id}.pdf`
              )
            }
          >
            PDF
          </Button>
        ) : (
          <Text size="xs" c="dimmed">
            —
          </Text>
        ),
    },
  ];

  return (
    <Stack gap="md">
      <Group gap="xs">
        <Text size="sm" c="dimmed">
          Historial de certificaciones médicas ocupacionales (FEMO)
          solicitadas para este servidor.
        </Text>
      </Group>

      {isLoading ? (
        <Skeleton height={100} radius="md" />
      ) : solicitudes.length === 0 ? (
        <EmptyState
          icon={IconStethoscope}
          title="Sin certificaciones registradas"
          description="Este servidor no tiene solicitudes de certificación médica."
        />
      ) : (
        <SgthTable
          records={solicitudes}
          columns={columns}
          fetching={isLoading}
          minHeight={100}
        />
      )}
    </Stack>
  );
}
