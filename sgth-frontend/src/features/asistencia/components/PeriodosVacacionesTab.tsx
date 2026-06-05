"use client";

import { useState } from "react";
import {
  Stack,
  Group,
  Button,
  Text,
  Badge,
  Select,
  NumberInput,
  Alert,
  Grid,
  Card,
  Divider,
  Skeleton,
} from "@mantine/core";
import {
  IconCalendarStats,
  IconRefresh,
  IconAlertTriangle,
  IconUsers,
  IconInfoCircle,
} from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useServidores } from "@/features/expediente/hooks/useServidores";
import { usePeriodosVacaciones } from "../hooks/usePeriodosVacaciones";
import { usePeriodosMutations } from "../hooks/usePeriodosMutations";
import { SgthTable } from "@/components/ui/SgthTable";
import type { ServidorConRelaciones, PeriodoVacacion } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";

const REGIMEN_COLORS = {
  losep: "blue",
  codigo_trabajo: "orange",
};
const REGIMEN_LABELS = {
  losep: "LOSEP",
  codigo_trabajo: "Código del Trabajo",
};
const ESTADO_COLORS = {
  abierto: "emerald",
  cerrado: "gray",
  vencido: "red",
};

function formatDias(v: number | string | null | undefined): string {
  if (v === null || v === undefined) return "—";
  return Number(v).toFixed(1);
}

export function PeriodosVacacionesTab() {
  const contained = useContainedInput();
  const [servidorSelId, setServidorSelId] = useState<number | null>(null);
  const [anio, setAnio] = useState<number>(new Date().getFullYear());

  const { data: servidoresData } = useServidores({ per_page: 200 });
  const servidores = (servidoresData?.data ?? []) as ServidorConRelaciones[];

  const servidorOptions = servidores.map((s) => ({
    value: String(s.id),
    label: `${[s.apellido, s.nombre].filter(Boolean).join(" ")} — ${s.cedula}`,
  }));

  const { data: resumen, isLoading } = usePeriodosVacaciones(servidorSelId);

  const { generar, generarTodos } = usePeriodosMutations();

  const periodos = (resumen?.periodos ?? []) as PeriodoVacacion[];
  const saldoTotal = resumen?.saldo_total ?? 0;
  const alertaLimite = resumen?.alerta_limite ?? false;

  const columns: DataTableColumn<PeriodoVacacion>[] = [
    {
      accessor: "anio",
      title: "Año",
      width: 70,
      render: ({ anio: a }) => (
        <Text size="sm" fw={700} ff="monospace">
          {a}
        </Text>
      ),
    },
    {
      accessor: "regimen",
      title: "Régimen",
      width: 130,
      render: ({ regimen }) => (
        <Badge
          color={
            REGIMEN_COLORS[regimen as keyof typeof REGIMEN_COLORS] ?? "gray"
          }
          variant="light"
          size="sm"
        >
          {REGIMEN_LABELS[regimen as keyof typeof REGIMEN_LABELS] ?? regimen}
        </Badge>
      ),
    },
    {
      accessor: "anios_antiguedad",
      title: "Antigüedad",
      width: 90,
      render: ({ anios_antiguedad }) => (
        <Text size="sm" ta="center">
          {anios_antiguedad} años
        </Text>
      ),
    },
    {
      accessor: "dias_generados",
      title: "Generados",
      width: 90,
      render: ({ dias_generados }) => (
        <Text size="sm" ta="center" c="blue">
          {formatDias(dias_generados)}
        </Text>
      ),
    },
    {
      accessor: "dias_vacaciones_aprobadas",
      title: "Días Vacaciones",
      width: 120,
      render: ({ dias_vacaciones_aprobadas }) => {
        const val = Number(dias_vacaciones_aprobadas ?? 0);
        return (
          <Stack gap={2}>
            <Text size="sm" ta="center" c="blue" fw={500}>
              {val.toFixed(2)}
            </Text>
            <Text size="xs" c="dimmed" ta="center">
              días
            </Text>
          </Stack>
        );
      },
    },
    {
      accessor: "dias_permisos_personales",
      title: "Permisos Pers.",
      width: 110,
      render: ({ dias_permisos_personales, regimen }) => {
        const val = Number(dias_permisos_personales ?? 0);
        const horas = Math.round(val * 8 * 100) / 100;

        if (val === 0) {
          return (
            <Text size="xs" c="dimmed" ta="center">
              —
            </Text>
          );
        }

        return (
          <Stack gap={2} align="center">
            <Text size="sm" fw={500} c="orange">
              {val.toFixed(3)} días
            </Text>
            <Text size="xs" c="dimmed">
              {horas}h descontadas
            </Text>
            {(regimen as string) === "losep" && (
              <Badge size="xs" color="orange" variant="dot">
                LOSEP
              </Badge>
            )}
          </Stack>
        );
      },
    },
    {
      accessor: "dias_saldo",
      title: "Saldo",
      width: 130,
      render: ({ dias_saldo, dias_generados }) => {
        const saldo = Number(dias_saldo);
        const generado = Number(dias_generados);
        const usado = generado - saldo;
        const pct =
          generado > 0
            ? Math.min(100, Math.round((usado / generado) * 100))
            : 0;
        const color = pct >= 80 ? "red" : pct >= 50 ? "orange" : "emerald";

        return (
          <Stack gap={3}>
            <Group gap={4} justify="space-between">
              <Text size="xs" fw={600} c="emerald">
                {saldo.toFixed(1)} días
              </Text>
              <Text size="xs" c="dimmed">
                {pct}% usado
              </Text>
            </Group>
            <div
              style={{
                width: "100%",
                height: 6,
                background: "var(--mantine-color-gray-2)",
                borderRadius: 3,
                overflow: "hidden",
              }}
            >
              <div
                style={{
                  width: `${pct}%`,
                  height: "100%",
                  background:
                    color === "red"
                      ? "var(--mantine-color-red-5)"
                      : color === "orange"
                        ? "var(--mantine-color-orange-5)"
                        : "var(--mantine-color-green-5)",
                  borderRadius: 3,
                  transition: "width 0.3s ease",
                }}
              />
            </div>
          </Stack>
        );
      },
    },
    {
      accessor: "saldo_acumulado",
      title: "Acumulado",
      width: 100,
      render: ({ saldo_acumulado, regimen }) => {
        const acum = Number(saldo_acumulado);
        const enAlerta = acum >= 45 && regimen === "losep";
        return (
          <Group gap={4}>
            <Text size="sm" fw={600} c={enAlerta ? "orange" : "inherit"}>
              {formatDias(saldo_acumulado)}
            </Text>
            {enAlerta && <IconAlertTriangle size={12} color="orange" />}
          </Group>
        );
      },
    },
    {
      accessor: "estado",
      title: "Estado",
      width: 90,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as keyof typeof ESTADO_COLORS] ?? "gray"}
          variant="light"
          size="sm"
        >
          {estado}
        </Badge>
      ),
    },
  ];

  return (
    <Stack gap="md">
      {/* ── PANEL SUPERIOR: Generar todos ── */}
      <Card withBorder radius="md" p="md">
        <Group justify="space-between">
          <Stack gap={4}>
            <Text fw={600} size="sm">
              Generación masiva de períodos
            </Text>
            <Text size="xs" c="dimmed">
              Genera los períodos de vacaciones para todos los servidores
              activos en el año seleccionado.
            </Text>
          </Stack>
          <Group gap="sm" justify="flex-end">
            <NumberInput
              label="Año"
              min={2020}
              max={2035}
              {...contained}
              value={anio}
              onChange={(v) =>
                setAnio(typeof v === "number" ? v : new Date().getFullYear())
              }
            />
            <Button
              color="emerald"
              variant="light"
              leftSection={<IconUsers size={16} />}
              loading={generarTodos.isPending}
              onClick={() => {
                if (
                  confirm(
                    `¿Generar períodos ${anio} para todos los servidores activos?`,
                  )
                ) {
                  generarTodos.mutate(anio);
                }
              }}
            >
              Generar para todos
            </Button>
          </Group>
        </Group>
      </Card>

      <Divider label="Consulta por servidor" labelPosition="left" />

      {/* ── PANEL INFERIOR: Consulta individual ── */}
      <Group justify="flex-start">
        <Select
          label="Servidor"
          placeholder="Buscar servidor"
          data={servidorOptions}
          searchable
          {...contained}
          value={servidorSelId ? String(servidorSelId) : null}
          onChange={(v) => setServidorSelId(v ? Number(v) : null)}
        />
        <Button
          variant="light"
          leftSection={<IconRefresh size={16} />}
          disabled={!servidorSelId}
          loading={generar.isPending}
          onClick={() => {
            if (servidorSelId) {
              if (confirm(`¿Generar período ${anio} para este servidor?`)) {
                generar.mutate({
                  servidorId: servidorSelId,
                  anio,
                });
              }
            }
          }}
        >
          Generar período {anio}
        </Button>
      </Group>

      {/* ── RESUMEN SALDO ── */}
      {servidorSelId && !isLoading && resumen && (
        <Alert
          icon={
            alertaLimite ? (
              <IconAlertTriangle size={16} />
            ) : (
              <IconInfoCircle size={16} />
            )
          }
          color={alertaLimite ? "orange" : "blue"}
          variant="light"
        >
          <Group gap="sm">
            <Text size="sm">Saldo total disponible:</Text>
            <Badge
              variant="default"
              color={alertaLimite ? "orange" : "emerald"}
              size="lg"
            >
              {Number(saldoTotal).toFixed(1)} días
            </Badge>
            {alertaLimite && (
              <Text size="xs" c="orange" fw={500}>
                ⚠️ Servidor acumula más de 45 días — debe gozar vacaciones
                pronto (límite LOSEP: 60 días)
              </Text>
            )}
          </Group>
        </Alert>
      )}

      {/* ── TABLA DE PERÍODOS ── */}
      {!servidorSelId ? (
        <Alert
          icon={<IconCalendarStats size={16} />}
          color="gray"
          variant="light"
        >
          <Text size="sm">
            Selecciona un servidor para ver sus períodos de vacaciones.
          </Text>
        </Alert>
      ) : isLoading ? (
        <Skeleton height={200} radius="md" />
      ) : periodos.length === 0 ? (
        <Alert
          icon={<IconInfoCircle size={16} />}
          color="orange"
          variant="light"
        >
          <Text size="sm">
            Este servidor no tiene períodos generados. Usa el botón
            &quot;Generar período {anio}&quot; para crearlo.
          </Text>
        </Alert>
      ) : (
        <>
          <SgthTable
            records={periodos}
            columns={columns}
            fetching={isLoading}
            minHeight={150}
          />
          {periodos.length > 0 && (
            <Card withBorder radius="md" p="md">
              <Text fw={600} size="sm" mb="sm">
                Resumen del servidor
              </Text>
              <Grid>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text size="xl" fw={700} c="blue">
                      {periodos.length}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Períodos registrados
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text size="xl" fw={700} c="emerald">
                      {Number(saldoTotal).toFixed(1)}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Días saldo total
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text size="xl" fw={700} c="orange">
                      {periodos
                        .reduce((acc, p) => acc + Number(p.dias_utilizados), 0)
                        .toFixed(1)}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Días utilizados total
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text size="xl" fw={700} c="blue">
                      {Number(resumen?.total_vacaciones_aprobadas ?? 0).toFixed(
                        1,
                      )}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Días por vacaciones
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text size="xl" fw={700} c="orange">
                      {Number(resumen?.total_permisos_personales ?? 0).toFixed(
                        3,
                      )}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Días por permisos
                    </Text>
                  </Stack>
                </Grid.Col>
                <Grid.Col span={{ base: 6, sm: 2 }}>
                  <Stack gap={2} align="center">
                    <Text
                      size="xl"
                      fw={700}
                      c={alertaLimite ? "orange" : "gray"}
                    >
                      {periodos
                        .reduce((acc, p) => acc + Number(p.dias_generados), 0)
                        .toFixed(1)}
                    </Text>
                    <Text size="xs" c="dimmed" ta="center">
                      Días generados total
                    </Text>
                  </Stack>
                </Grid.Col>
              </Grid>
            </Card>
          )}
        </>
      )}
    </Stack>
  );
}
