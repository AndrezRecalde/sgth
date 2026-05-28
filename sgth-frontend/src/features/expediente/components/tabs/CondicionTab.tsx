"use client";

import {
  Stack,
  Group,
  Text,
  Badge,
  Button,
  Divider,
  Skeleton,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconPlus,
  IconTrash,
  IconHeart,
  IconCheck,
  IconX,
} from "@tabler/icons-react";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { EmptyState } from "@/components/ui/EmptyState";
import { useDiscapacidades } from "../../hooks/useDiscapacidades";
import { useEnfermedades } from "../../hooks/useEnfermedades";
import { DiscapacidadModal } from "../DiscapacidadModal";
import { EnfermedadModal } from "../EnfermedadModal";
import { expedienteService } from "../../services/expedienteService";
import type {
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
  ApiResponse,
} from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";

interface Props {
  servidorId: number;
}

export function CondicionTab({ servidorId }: Props) {
  const [discOpened, { open: openDisc, close: closeDisc }] =
    useDisclosure(false);
  const [enfOpened, { open: openEnf, close: closeEnf }] = useDisclosure(false);

  const qc = useQueryClient();

  const { data: discapacidades = [], isLoading: ldDisc } =
    useDiscapacidades(servidorId);
  const { data: enfermedades = [], isLoading: ldEnf } =
    useEnfermedades(servidorId);

  const delDisc = useMutation<ApiResponse<void>, Error, number>({
    mutationFn: (id: number) =>
      expedienteService.eliminarDiscapacidad(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: "Registro eliminado",
        color: "emerald",
        message: "La discapacidad fue eliminada del expediente.",
        icon: <IconCheck size={16} />,
      });
      qc.invalidateQueries({ queryKey: ["discapacidades", servidorId] });
    },
    onError: () => {
      notifications.show({
        title: "Error",
        color: "red",
        message: "No se pudo eliminar la discapacidad.",
        icon: <IconX size={16} />,
      });
    },
  });

  const delEnf = useMutation<ApiResponse<void>, Error, number>({
    mutationFn: (id: number) =>
      expedienteService.eliminarEnfermedad(servidorId, id),
    onSuccess: () => {
      notifications.show({
        title: "Registro eliminado",
        color: "emerald",
        message: "La enfermedad catastrófica fue eliminada del expediente.",
        icon: <IconCheck size={16} />,
      });
      qc.invalidateQueries({ queryKey: ["enfermedades", servidorId] });
    },
    onError: () => {
      notifications.show({
        title: "Error",
        color: "red",
        message: "No se pudo eliminar la enfermedad.",
        icon: <IconX size={16} />,
      });
    },
  });

  const discColumns: DataTableColumn<DiscapacidadServidor>[] = [
    {
      accessor: "tipo_discapacidad",
      title: "Tipo",
      render: ({ tipo_discapacidad }) => (
        <Text size="sm">{tipo_discapacidad ?? "-"}</Text>
      ),
    },
    {
      accessor: "porcentaje",
      title: "%",
      width: 80,
      render: ({ porcentaje }) => (
        <Badge color="orange" variant="light" size="sm">
          {porcentaje ?? "-"}%
        </Badge>
      ),
    },
    {
      accessor: "numero_carnet_conadis",
      title: "Carnet CONADIS",
      render: ({ numero_carnet_conadis }) => (
        <Text size="sm" ff="monospace">
          {numero_carnet_conadis ?? "—"}
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
              label: "Eliminar",
              icon: <IconTrash size={14} />,
              color: "red",
              onClick: () => {
                if (confirm("¿Eliminar este registro de discapacidad?"))
                  delDisc.mutate(Number(item.id));
              },
            },
          ]}
        />
      ),
    },
  ];

  const enfColumns: DataTableColumn<EnfermedadCatastroficaServidor>[] = [
    {
      accessor: "tipo_enfermedad",
      title: "Enfermedad",
      render: ({ tipo_enfermedad }) => (
        <Text size="sm" fw={500}>
          {tipo_enfermedad ?? "-"}
        </Text>
      ),
    },
    {
      accessor: "codigo_cie10",
      title: "CIE-10",
      width: 90,
      render: ({ codigo_cie10 }) => (
        <Text size="sm" ff="monospace">
          {codigo_cie10 ?? "—"}
        </Text>
      ),
    },
    {
      accessor: "fecha_diagnostico",
      title: "Diagnóstico",
      width: 110,
      render: ({ fecha_diagnostico }) => (
        <Text size="sm">
          {fecha_diagnostico
            ? new Date(fecha_diagnostico).toLocaleDateString("es-EC", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
              })
            : "—"}
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
              label: "Eliminar",
              icon: <IconTrash size={14} />,
              color: "red",
              onClick: () => {
                if (
                  confirm("¿Eliminar este registro de enfermedad catastrófica?")
                )
                  delEnf.mutate(Number(item.id));
              },
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Stack gap="lg">
      <div>
        <Group justify="space-between" mb="xs">
          <Divider
            label={
              <Text size="sm" fw={600}>
                Discapacidades
              </Text>
            }
            labelPosition="left"
            style={{ flex: 1 }}
          />
          <Button
            size="xs"
            color="emerald"
            variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={openDisc}
          >
            Registrar
          </Button>
        </Group>
        {ldDisc ? (
          <Skeleton height={80} radius="md" />
        ) : Array.isArray(discapacidades) && discapacidades.length > 0 ? (
          <SgthTable
            records={discapacidades as DiscapacidadServidor[]}
            columns={discColumns}
            fetching={false}
            minHeight={80}
          />
        ) : (
          <EmptyState icon={IconHeart} title="Sin discapacidades registradas" />
        )}
      </div>

      <div>
        <Group justify="space-between" mb="xs">
          <Divider
            label={
              <Text size="sm" fw={600}>
                Enfermedades catastróficas
              </Text>
            }
            labelPosition="left"
            style={{ flex: 1 }}
          />
          <Button
            size="xs"
            color="emerald"
            variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={openEnf}
          >
            Registrar
          </Button>
        </Group>
        {ldEnf ? (
          <Skeleton height={80} radius="md" />
        ) : Array.isArray(enfermedades) && enfermedades.length > 0 ? (
          <SgthTable
            records={enfermedades as EnfermedadCatastroficaServidor[]}
            columns={enfColumns}
            fetching={false}
            minHeight={80}
          />
        ) : (
          <EmptyState icon={IconHeart} title="Sin enfermedades registradas" />
        )}
      </div>

      <DiscapacidadModal
        opened={discOpened}
        onClose={closeDisc}
        servidorId={servidorId}
      />
      <EnfermedadModal
        opened={enfOpened}
        onClose={closeEnf}
        servidorId={servidorId}
      />
    </Stack>
  );
}
