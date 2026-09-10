"use client";

import { Card, Stack, Text } from "@mantine/core";
import { IconHourglassEmpty } from "@tabler/icons-react";
import type { DataTableColumn } from "mantine-datatable";
import {
  DataState,
  SgthTable,
  StatusBadge,
  TableActions,
  confirmar,
} from "@/components/ui";
import { useAuth } from "@/hooks/useAuth";
import { REGIMEN_LABELS, REGIMEN_TONOS } from "@/lib/regimen";
import { useServidoresSobreTope } from "../hooks/useServidoresSobreTope";
import { usePeriodosMutations } from "../hooks/usePeriodosMutations";
import type { ServidorSobreTope } from "@/types/api";

/*
| Quién está cerca de su tope de acumulación o lo pasa.
|
| LOSEP (art. 29): 60 días. Código del Trabajo (art. 75): tres años de lo que
| genera. Nada vence solo: el excedente lo vence Talento Humano, servidor por
| servidor, y queda en la bitácora.
*/

const dias = (n: number) => `${n.toFixed(2)} días`;

export function TopeAcumulacionCard() {
  const { hasPermiso } = useAuth();
  const puedeVencer = hasPermiso("gestionar-vacaciones");

  const { data, isLoading, error } = useServidoresSobreTope();
  const { vencerExcedente } = usePeriodosMutations();
  const filas = data ?? [];

  const pedirVencimiento = (f: ServidorSobreTope) =>
    confirmar({
      title: "Vencer el excedente",
      message: (
        <>
          <b>{f.nombre}</b> tiene <b>{dias(f.saldo)}</b> y su tope es de{" "}
          {dias(f.tope)}. Vencerán <b>{dias(f.excedente)}</b>, tomados de sus
          períodos más antiguos, y el saldo quedará en {dias(f.tope)}. Los
          días vencidos no se recuperan con una regeneración. Queda registrado
          en la bitácora.
        </>
      ),
      confirmLabel: "Vencer",
      destructiva: true,
      onConfirm: () => vencerExcedente.mutate(f.servidor_id),
    });

  const columns: DataTableColumn<ServidorSobreTope>[] = [
    {
      accessor: "nombre",
      title: "Servidor",
      render: ({ nombre, cedula, unidad }) => (
        <Stack gap={0}>
          <Text size="sm">{nombre}</Text>
          <Text size="xs" c="dimmed">
            {cedula}
            {unidad ? ` · ${unidad}` : ""}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: "regimen",
      title: "Régimen",
      width: 150,
      render: ({ regimen }) => (
        <StatusBadge tone={REGIMEN_TONOS[regimen] ?? "neutral"}>
          {REGIMEN_LABELS[regimen] ?? regimen}
        </StatusBadge>
      ),
    },
    {
      accessor: "saldo",
      title: "Saldo",
      width: 100,
      render: ({ saldo }) => <Text size="sm">{saldo.toFixed(2)}</Text>,
    },
    {
      accessor: "tope",
      title: "Tope",
      width: 90,
      render: ({ tope }) => <Text size="sm">{tope.toFixed(2)}</Text>,
    },
    {
      accessor: "excedente",
      title: "Excedente",
      width: 110,
      render: ({ excedente }) => (
        <Text size="sm" fw={600} c={excedente > 0 ? "red" : "dimmed"}>
          {excedente > 0 ? excedente.toFixed(2) : "—"}
        </Text>
      ),
    },
    {
      accessor: "acciones",
      title: "",
      width: 50,
      render: (fila) => (
        <TableActions
          actions={[
            {
              label: "Vencer excedente",
              icon: <IconHourglassEmpty size={14} />,
              color: "red",
              hidden: !puedeVencer || fila.excedente <= 0,
              onClick: () => pedirVencimiento(fila),
            },
          ]}
        />
      ),
    },
  ];

  return (
    <Card withBorder radius="md" p="md">
      <Stack gap={4} mb="sm">
        <Text fw={600} size="sm">
          Tope de acumulación
        </Text>
        <Text size="xs" c="dimmed">
          LOSEP: 60 días. Código del Trabajo: tres años de lo que genera el
          servidor. Se listan desde el 75 % del tope; el excedente no vence
          hasta que Talento Humano lo decide.
        </Text>
      </Stack>

      <DataState
        loading={isLoading}
        error={error}
        empty={!filas.length}
        emptyProps={{
          icon: IconHourglassEmpty,
          title: "Nadie está cerca de su tope",
          description: "Ningún servidor activo llega al 75 % de su tope de acumulación.",
        }}
      >
        <SgthTable records={filas} columns={columns} idAccessor="servidor_id" minHeight={120} />
      </DataState>
    </Card>
  );
}
