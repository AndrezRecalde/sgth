"use client";

import { SgthDrawer } from "@/components/ui";
import { Tabs, Stack } from "@mantine/core";
import { IconHistory, IconBriefcase } from "@tabler/icons-react";
import { MovimientosTab } from "./tabs/MovimientosTab";
import { LaboralTab } from "./tabs/LaboralTab";
import type { ServidorConRelaciones } from "@/types/api";
import { ServidorEncabezado, nombreCompletoDe } from "./ServidorEncabezado";

interface Props {
  opened: boolean;
  onClose: () => void;
  servidor: ServidorConRelaciones | null;
}

export function AccionPersonalDrawer({ opened, onClose, servidor }: Props) {

  if (!servidor) return null;

  const servidorId = Number(servidor.id);

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Acción de personal"
      description={nombreCompletoDe(servidor)}
      ancho="lg"
    >
      <Stack gap="md">
        <ServidorEncabezado servidor={servidor} />

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
