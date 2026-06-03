"use client";

import { Stack, Tabs } from "@mantine/core";
import { PageHeader } from "@/components/ui/PageHeader";
import {
  IconClock,
  IconClipboardList,
  IconBeach,
  IconFingerprint,
  IconCalendarStats,
  IconReportAnalytics,
} from "@tabler/icons-react";
import { IconCalendarTime } from "@tabler/icons-react";
import { PermisosTab } from "@/features/asistencia/components/PermisosTab";
import { VacacionesTab } from "@/features/asistencia/components/VacacionesTab";
import { MarcacionesTab } from "@/features/asistencia/components/MarcacionesTab";
import { MarcacionOnlineTab } from "@/features/asistencia/components/MarcacionOnlineTab";
import { PeriodosVacacionesTab } from "@/features/asistencia/components/PeriodosVacacionesTab";
import { ConsolidadoPermisosTab } from "@/features/asistencia/components/ConsolidadoPermisosTab";

export function AsistenciaView() {
  return (
    <Stack gap="md">
      <PageHeader
        title="Asistencia"
        subtitle="Marcaciones, permisos y vacaciones del personal"
        icon={<IconCalendarTime size={24} />}
      />
      <Tabs defaultValue="marcaciones" keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="marcaciones" leftSection={<IconClock size={16} />}>
            Marcaciones
          </Tabs.Tab>
          <Tabs.Tab
            value="permisos"
            leftSection={<IconClipboardList size={16} />}
          >
            Permisos
          </Tabs.Tab>
          <Tabs.Tab value="vacaciones" leftSection={<IconBeach size={16} />}>
            Vacaciones
          </Tabs.Tab>
          <Tabs.Tab
            value="periodos"
            leftSection={<IconCalendarStats size={16} />}
          >
            Períodos
          </Tabs.Tab>
          <Tabs.Tab
            value="consolidado"
            leftSection={<IconReportAnalytics size={16} />}
          >
            Consolidado
          </Tabs.Tab>
          <Tabs.Tab value="online" leftSection={<IconFingerprint size={16} />}>
            Marcación Online
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="marcaciones">
          <MarcacionesTab />
        </Tabs.Panel>
        <Tabs.Panel value="permisos">
          <PermisosTab />
        </Tabs.Panel>
        <Tabs.Panel value="vacaciones">
          <VacacionesTab />
        </Tabs.Panel>
        <Tabs.Panel value="periodos">
          <PeriodosVacacionesTab />
        </Tabs.Panel>
        <Tabs.Panel value="consolidado">
          <ConsolidadoPermisosTab />
        </Tabs.Panel>
        <Tabs.Panel value="online">
          <MarcacionOnlineTab />
        </Tabs.Panel>
      </Tabs>
    </Stack>
  );
}
