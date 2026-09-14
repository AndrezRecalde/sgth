"use client";

import { SgthDrawer } from "@/components/ui";
import { Tabs, Avatar, Group, Stack, Text } from "@mantine/core";
import { IconHistory, IconBriefcase } from "@tabler/icons-react";
import { MovimientosTab } from "./tabs/MovimientosTab";
import { LaboralTab } from "./tabs/LaboralTab";
import type { ServidorConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  servidor: ServidorConRelaciones | null;
}

export function AccionPersonalDrawer({ opened, onClose, servidor }: Props) {

  if (!servidor) return null;

  const servidorId = Number(servidor.id);
  const nombreCompleto = [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ]
    .filter(Boolean)
    .join(" ");

  const initials =
    [servidor.nombre?.charAt(0), servidor.apellido?.charAt(0)]
      .filter(Boolean)
      .join("")
      .toUpperCase() || "?";

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Acción de personal"
      ancho="lg"
    >
      <Stack gap="md">
        <Group
          p="md"
          style={{
            borderRadius: 12,
            border: "1px solid var(--mantine-color-default-border)",
            background: "var(--sgth-accent-light)",
          }}
        >
          <Avatar size={52} radius="xl" fw={700}>
            {initials}
          </Avatar>
          <Stack gap={2} style={{ flex: 1 }}>
            <Text fw={700} size="md">
              {nombreCompleto}
            </Text>
            <Text size="sm" c="dimmed" ff="monospace">
              CI: {servidor.cedula ?? "-"}
            </Text>
          </Stack>
        </Group>

        <Tabs defaultValue="movimientos">
          <Tabs.List>
            <Tabs.Tab
              value="movimientos"
              leftSection={<IconHistory size={13} />}
            >
              Acción de Personal
            </Tabs.Tab>
            <Tabs.Tab
              value="laboral"
              leftSection={<IconBriefcase size={13} />}
            >
              Actividad Laboral
            </Tabs.Tab>
          </Tabs.List>

          <Tabs.Panel value="movimientos" pt="md">
            <MovimientosTab
              servidorId={servidorId}
              tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
            />
          </Tabs.Panel>
          <Tabs.Panel value="laboral" pt="md">
            <LaboralTab servidorId={servidorId} />
          </Tabs.Panel>
        </Tabs>
      </Stack>
    </SgthDrawer>
  );
}
