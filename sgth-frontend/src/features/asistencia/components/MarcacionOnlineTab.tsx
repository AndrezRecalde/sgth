"use client";

import { useState, useEffect } from "react";
import {
  Stack,
  Button,
  Text,
  Badge,
  Group,
  Card,
  Alert,
  Loader,
  Center,
  Timeline,
  Grid,
  Paper,
  ThemeIcon,
} from "@mantine/core";
import {
  IconLogin,
  IconLogout,
  IconSoup,
  IconInfoCircle,
  IconMapPin,
  IconMapPinOff,
  IconCheck,
  IconClock,
} from "@tabler/icons-react";
import { notifications } from "@mantine/notifications";
import { useQuery } from "@tanstack/react-query";
import { asistenciaService } from "../services/asistenciaService";
import { useAuthStore } from "@/store/auth.store";

export function MarcacionOnlineTab() {
  const { usuario } = useAuthStore();
  const [ubicacion, setUbicacion] = useState<{
    lat: number;
    lon: number;
  } | null>(null);
  const [cargandoUbicacion, setCargandoUbicacion] = useState(true);
  const [registrando, setRegistrando] = useState(false);

  const cedula = usuario?.servidor?.cedula ?? null;
  const puedeMarcar = usuario?.servidor?.puede_marcar ?? false;

  const {
    data: estadoHoy,
    isLoading: cargandoEstado,
    refetch,
  } = useQuery({
    queryKey: ["marcacion-hoy", cedula],
    queryFn: () => asistenciaService.marcaciones.estadoHoy(),
    enabled: !!cedula && puedeMarcar,
    staleTime: 0,
    refetchInterval: 60_000,
  });

  const obtenerUbicacion = () => {
    setCargandoUbicacion(true);
    if (!navigator.geolocation) {
      setTimeout(() => setCargandoUbicacion(false), 0);
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setUbicacion({
          lat: pos.coords.latitude,
          lon: pos.coords.longitude,
        });
        setCargandoUbicacion(false);
      },
      () => setCargandoUbicacion(false),
      { enableHighAccuracy: true }
    );
  };

  useEffect(() => {
    obtenerUbicacion();
  }, []);

  const registrar = async (checktype: "I" | "O", label: string) => {
    if (!cedula || !puedeMarcar) return;
    setRegistrando(true);
    try {
      await asistenciaService.marcaciones.registrarOnline({
        checktype,
        latitud: ubicacion?.lat,
        longitud: ubicacion?.lon,
      });
      notifications.show({
        title: `${label} registrada`,
        message: `Tu ${label.toLowerCase()} fue registrada correctamente.`,
        color: "emerald",
        icon: <IconCheck size={16} />,
      });
      refetch();
    } catch {
      notifications.show({
        title: "Error",
        message: "No se pudo registrar la marcación.",
        color: "red",
      });
    } finally {
      setRegistrando(false);
    }
  };

  if (!cedula || !puedeMarcar) {
    return (
      <Alert
        icon={<IconInfoCircle size={16} />}
        color="orange"
        variant="light"
        radius="md"
      >
        <Text size="sm">
          Tu usuario no tiene habilitada la marcación biométrica. Contacta a
          Talento Humano para habilitarla en tu contrato.
        </Text>
      </Alert>
    );
  }

  // Lógica para determinar el paso activo en el Timeline
  let activeStep = -1;
  if (estadoHoy) {
    if (estadoHoy.Entrada) activeStep = 0;
    if (estadoHoy.AlmuerzoSalida) activeStep = 1;
    if (estadoHoy.AlmuerzoRetorno) activeStep = 2;
    if (estadoHoy.Salida) activeStep = 3;
  }

  return (
    <Stack gap="lg" maw={600} mx="auto">
      {/* Panel Superior: Información y Estado del GPS */}
      <Paper withBorder radius="md" p="md" bg="var(--mantine-color-body)">
        <Group justify="space-between" align="center" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <ThemeIcon size={32} radius="xl" variant="light" color="blue">
              <IconInfoCircle size={18} />
            </ThemeIcon>
            <Text size="xs" c="dimmed" lh={1.3} maw={250}>
              Registra tu asistencia desde territorio con conexión a internet y
              GPS activo.
            </Text>
          </Group>

          <Stack gap={4} align="flex-end">
            {cargandoUbicacion ? (
              <Badge variant="dot" color="gray" size="sm">
                Ubicando...
              </Badge>
            ) : ubicacion ? (
              <>
                <Badge
                  variant="light"
                  color="emerald"
                  size="sm"
                  leftSection={
                    <IconMapPin size={10} style={{ marginLeft: 6 }} />
                  }
                >
                  GPS Activo
                </Badge>
                <Text size="10px" c="dimmed" fw={500}>
                  {ubicacion.lat.toFixed(4)}, {ubicacion.lon.toFixed(4)}
                </Text>
              </>
            ) : (
              <Group gap="xs">
                <Button
                  size="compact-xs"
                  variant="subtle"
                  color="blue"
                  onClick={obtenerUbicacion}
                >
                  Reintentar
                </Button>
                <Badge
                  variant="dot"
                  color="red"
                  size="sm"
                  leftSection={
                    <IconMapPinOff size={10} style={{ marginLeft: 6 }} />
                  }
                >
                  GPS Inactivo
                </Badge>
              </Group>
            )}
          </Stack>
        </Group>
      </Paper>

      {/* Tarjeta Central: Timeline y Botones */}
      <Card withBorder radius="md" p="xl" shadow="sm">
        <Grid gap="xl">
          {/* Columna Izquierda: Línea de tiempo (Timeline) */}
          <Grid.Col span={{ base: 12, sm: 5 }}>
            <Text
              size="xs"
              fw={600}
              c="dimmed"
              tt="uppercase"
              style={{ letterSpacing: 0.5 }}
              mb="xl"
            >
              Progreso de hoy
            </Text>

            {cargandoEstado ? (
              <Center py="xl">
                <Loader size="sm" color="emerald" />
              </Center>
            ) : (
              <Timeline
                active={activeStep}
                bulletSize={24}
                lineWidth={2}
                color="emerald"
              >
                <Timeline.Item
                  bullet={
                    estadoHoy?.Entrada ? (
                      <IconCheck size={14} />
                    ) : (
                      <IconClock size={14} />
                    )
                  }
                  title="Entrada"
                >
                  <Text c="dimmed" size="xs" mt={4}>
                    {estadoHoy?.Entrada
                      ? estadoHoy.Entrada.substring(0, 5)
                      : "--:--"}
                  </Text>
                </Timeline.Item>

                <Timeline.Item
                  bullet={
                    estadoHoy?.AlmuerzoSalida ? (
                      <IconCheck size={14} />
                    ) : (
                      <IconClock size={14} />
                    )
                  }
                  title="Salida almuerzo"
                >
                  <Text c="dimmed" size="xs" mt={4}>
                    {estadoHoy?.AlmuerzoSalida
                      ? estadoHoy.AlmuerzoSalida.substring(0, 5)
                      : "--:--"}
                  </Text>
                </Timeline.Item>

                <Timeline.Item
                  bullet={
                    estadoHoy?.AlmuerzoRetorno ? (
                      <IconCheck size={14} />
                    ) : (
                      <IconClock size={14} />
                    )
                  }
                  title="Retorno almuerzo"
                >
                  <Text c="dimmed" size="xs" mt={4}>
                    {estadoHoy?.AlmuerzoRetorno
                      ? estadoHoy.AlmuerzoRetorno.substring(0, 5)
                      : "--:--"}
                  </Text>
                </Timeline.Item>

                <Timeline.Item
                  bullet={
                    estadoHoy?.Salida ? (
                      <IconCheck size={14} />
                    ) : (
                      <IconClock size={14} />
                    )
                  }
                  title="Salida final"
                >
                  <Text c="dimmed" size="xs" mt={4}>
                    {estadoHoy?.Salida
                      ? estadoHoy.Salida.substring(0, 5)
                      : "--:--"}
                  </Text>
                </Timeline.Item>
              </Timeline>
            )}
          </Grid.Col>

          {/* Columna Derecha: Botones de Acción */}
          <Grid.Col span={{ base: 12, sm: 7 }}>
            <Text
              size="xs"
              fw={600}
              c="dimmed"
              tt="uppercase"
              style={{ letterSpacing: 0.5 }}
              mb="md"
            >
              Registrar Acción
            </Text>

            <Grid gap="sm">
              <Grid.Col span={6}>
                <Button
                  h={100}
                  radius="md"
                  color="emerald"
                  variant="light"
                  loading={registrando}
                  onClick={() => registrar("I", "Entrada")}
                  fullWidth
                  disabled={cargandoEstado}
                  p={0}
                >
                  <Stack gap={4} align="center" justify="center">
                    <IconLogin size={28} stroke={1.5} />
                    <Text size="xs" fw={600}>
                      Entrada
                    </Text>
                  </Stack>
                </Button>
              </Grid.Col>

              <Grid.Col span={6}>
                <Button
                  h={100}
                  radius="md"
                  color="red"
                  variant="light"
                  loading={registrando}
                  onClick={() => registrar("I", "Salida")}
                  fullWidth
                  disabled={cargandoEstado}
                  p={0}
                >
                  <Stack gap={4} align="center" justify="center">
                    <IconLogout size={28} stroke={1.5} />
                    <Text size="xs" fw={600}>
                      Salida
                    </Text>
                  </Stack>
                </Button>
              </Grid.Col>

              <Grid.Col span={6}>
                <Button
                  h={100}
                  radius="md"
                  color="orange"
                  variant="light"
                  loading={registrando}
                  onClick={() => registrar("O", "Salida Almuerzo")}
                  fullWidth
                  disabled={cargandoEstado}
                  p={0}
                >
                  <Stack gap={4} align="center" justify="center">
                    <IconSoup size={28} stroke={1.5} />
                    <Text size="xs" fw={600} ta="center" lh={1.1}>
                      Salida
                      <br />
                      Almuerzo
                    </Text>
                  </Stack>
                </Button>
              </Grid.Col>

              <Grid.Col span={6}>
                <Button
                  h={100}
                  radius="md"
                  color="blue"
                  variant="light"
                  loading={registrando}
                  onClick={() => registrar("O", "Retorno Almuerzo")}
                  fullWidth
                  disabled={cargandoEstado}
                  p={0}
                >
                  <Stack gap={4} align="center" justify="center">
                    <IconSoup size={28} stroke={1.5} />
                    <Text size="xs" fw={600} ta="center" lh={1.1}>
                      Retorno
                      <br />
                      Almuerzo
                    </Text>
                  </Stack>
                </Button>
              </Grid.Col>
            </Grid>
          </Grid.Col>
        </Grid>
      </Card>
    </Stack>
  );
}
