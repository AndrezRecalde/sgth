"use client";

import { useState } from "react";
import { Alert, Button, Card, Group, Stack, Text } from "@mantine/core";
import { IconHistory } from "@tabler/icons-react";
import { useVersionesConsulta } from "../hooks/useConsultaMedica";
import type { VersionConsulta } from "../services/consultaMedicaService";
import { TextoClinico } from "./TextoClinico";
import { formatFechaMesHora } from '@/lib/fecha';

interface Props {
  consultaId: number;
}

function Campo({
  label,
  valor,
  enriquecido,
}: {
  label: string;
  valor?: string | null;
  enriquecido?: boolean;
}) {
  if (!valor) return null;

  return (
    <Stack gap={2}>
      <Text size="xs" c="dimmed" fw={500}>
        {label}
      </Text>
      <TextoClinico valor={valor} enriquecido={enriquecido} size="xs" />
    </Stack>
  );
}

/**
 * Lo que la consulta decía antes de cada corrección.
 *
 * No se enseña desplegado: en el caso normal no hay ninguna, y cuando la hay
 * lo que importa primero es saber que existió, no leerla entera.
 */
export function CorreccionesConsulta({ consultaId }: Props) {
  const { data: versiones = [] } = useVersionesConsulta(consultaId);
  const [abierto, setAbierto] = useState(false);

  if (versiones.length === 0) return null;

  return (
    <Alert
      icon={<IconHistory size={16} />}
      color="amber"
      variant="light"
      radius="md"
      mx="md"
      mb="sm"
      title={
        versiones.length === 1
          ? "Esta consulta fue corregida una vez"
          : `Esta consulta fue corregida ${versiones.length} veces`
      }
    >
      <Stack gap="xs" align="flex-start">
        <Text size="xs">
          Lo que decía antes se conserva. La versión vigente es la de arriba.
        </Text>

        <Button
          size="compact-xs"
          variant="subtle"
          onClick={() => setAbierto((v) => !v)}
        >
          {abierto ? "Ocultar versiones anteriores" : "Ver qué decía antes"}
        </Button>

        {abierto && (
          <Stack gap="xs" w="100%">
            {versiones.map((version: VersionConsulta) => (
              <Card key={version.id} withBorder radius="sm" p="xs">
                <Stack gap={6}>
                  <Group gap={6} wrap="wrap">
                    <Text size="xs" c="dimmed" ff="monospace">
                      {formatFechaMesHora(version.created_at)}
                    </Text>
                    <Text size="xs" c="dimmed">
                      — reemplazada por{" "}
                      {version.autor_del_cambio?.nombre_completo ??
                        version.autor_del_cambio?.usuario_ti ??
                        "—"}
                    </Text>
                  </Group>

                  <Campo label="Motivo" valor={version.motivo_consulta} />
                  <Campo
                    label="Enfermedad actual"
                    valor={version.enfermedad_actual}
                  />
                  <Campo label="Examen físico" valor={version.examen_fisico} />
                  <Campo
                    label="Diagnóstico"
                    valor={
                      version.diagnostico_cie10
                        ? `${version.diagnostico_cie10.codigo} — ${version.diagnostico_cie10.descripcion}`
                        : null
                    }
                  />
                  <Campo
                    label="Diagnósticos secundarios"
                    valor={(version.diagnosticos_secundarios_cie10 ?? [])
                      .map((d) => `${d.codigo} — ${d.descripcion}`)
                      .join("\n")}
                  />
                  <Campo
                    label="Diagnóstico detallado"
                    valor={version.diagnostico_detallado}
                    enriquecido
                  />
                  <Campo
                    label="Plan"
                    valor={version.plan_tratamiento}
                    enriquecido
                  />
                  <Campo label="Notas" valor={version.notas_medico} />
                </Stack>
              </Card>
            ))}
          </Stack>
        )}
      </Stack>
    </Alert>
  );
}
