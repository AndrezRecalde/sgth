"use client";

import { Stack, Group, Text, Badge, Button } from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconPlus,
  IconDownload,
  IconTrash,
  IconFileDescription,
} from "@tabler/icons-react";
import { EmptyState } from "@/components/ui/EmptyState";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { useDeclaraciones } from "../../hooks/useDeclaraciones";
import { useDeclaracionMutations } from "../../hooks/useDeclaracionMutations";
import { DeclaracionModal } from "@/features/expediente/components/DeclaracionModal";
import type { DeclaracionJuramentada } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";

const TIPO_COLORS: Record<string, string> = {
  inicio_gestion: "blue",
  periodica: "orange",
  fin_gestion: "red",
};

const TIPO_LABELS: Record<string, string> = {
  inicio_gestion: "Inicio de gestión",
  periodica: "Periódica",
  fin_gestion: "Fin de gestión",
};

interface Props {
  servidorId: number;
}

export function DeclaracionesTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const { data: declaraciones = [], isLoading } = useDeclaraciones(servidorId);
  const { eliminar, exportar } = useDeclaracionMutations(servidorId);

  const columns: DataTableColumn<DeclaracionJuramentada>[] = [
    {
      accessor: "tipo_declaracion",
      title: "Tipo",
      width: 110,
      render: ({ tipo_declaracion }) => (
        <Badge
          color={TIPO_COLORS[tipo_declaracion ?? ""] ?? "gray"}
          variant="light"
          size="sm"
        >
          {TIPO_LABELS[tipo_declaracion ?? ""] ?? tipo_declaracion ?? "-"}
        </Badge>
      ),
    },
    {
      accessor: "fecha_declaracion",
      title: "Fecha",
      width: 110,
      render: ({ fecha_declaracion }) => (
        <Text size="sm">
          {fecha_declaracion
            ? new Date(fecha_declaracion).toLocaleDateString("es-EC", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                timeZone: "UTC",
              })
            : "-"}
        </Text>
      ),
    },
    {
      accessor: "codigo_barras",
      title: "Código",
      render: ({ codigo_barras }) => (
        <Text size="sm" ff="monospace">
          {codigo_barras ?? "-"}
        </Text>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 50,
      render: (item) => (
        <TableActions
          actions={[
            {
              label: "Descargar documento",
              icon: <IconDownload size={14} />,
              color: "blue",
              onClick: () => {},
            },
            {
              label: "Eliminar",
              icon: <IconTrash size={14} />,
              color: "red",
              onClick: () => {
                if (confirm("¿Eliminar esta declaración?"))
                  eliminar.mutate(Number(item.id));
              },
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Button
          size="xs"
          variant="light"
          color="gray"
          leftSection={<IconDownload size={14} />}
          onClick={exportar}
        >
          Exportar todas
        </Button>
        <Button
          size="xs"
          color="emerald"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nueva declaración
        </Button>
      </Group>

      {!isLoading &&
      (declaraciones as DeclaracionJuramentada[]).length === 0 ? (
        <EmptyState
          icon={IconFileDescription}
          title="Sin declaraciones juramentadas"
          description="Registra las declaraciones juramentadas del servidor."
        />
      ) : (
        <SgthTable
          records={declaraciones as DeclaracionJuramentada[]}
          columns={columns}
          fetching={isLoading}
          minHeight={100}
        />
      )}
      <DeclaracionModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
      />
    </Stack>
  );
}
