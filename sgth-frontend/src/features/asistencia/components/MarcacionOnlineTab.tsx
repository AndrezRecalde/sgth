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
} from "@mantine/core";
import {
  IconLogin,
  IconLogout,
  IconSoup,
  IconInfoCircle,
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

  // La cédula viene del servidor vinculado al usuario
  const cedula = usuario?.servidor?.cedula ?? null

  // puede_marcar viene del servidor vinculado
  const puedeMarcar = usuario?.servidor?.puede_marcar ?? false

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

  useEffect(() => {
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
    );
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
      <Alert icon={<IconInfoCircle size={16} />} color="orange" variant="light">
        <Text size="sm">
          Tu usuario no tiene habilitada la marcación biométrica. Contacta a
          Talento Humano para habilitarla en tu contrato.
        </Text>
      </Alert>
    );
  }

  return (
    <Stack gap="md" maw={500} mx="auto">
      <Alert icon={<IconInfoCircle size={16} />} color="blue" variant="light">
        <Text size="xs">
          Esta función es para servidores en campo o territorio. Registra tu
          asistencia desde cualquier lugar con conexión a internet.
        </Text>
      </Alert>

      {/* Estado del día */}
      <Card withBorder radius="md" p="md">
        <Text size="sm" fw={600} mb="xs">
          Estado de hoy
        </Text>
        {cargandoEstado ? (
          <Center>
            <Loader size="sm" />
          </Center>
        ) : estadoHoy ? (
          <Stack gap="xs">
            {(
              [
                "Entrada",
                "AlmuerzoSalida",
                "AlmuerzoRetorno",
                "Salida",
              ] as const
            ).map((campo) => (
              <Group key={campo}>
                <Text size="xs" c="dimmed" w={130}>
                  {campo === "Entrada"
                    ? "Entrada"
                    : campo === "AlmuerzoSalida"
                      ? "Salida almuerzo"
                      : campo === "AlmuerzoRetorno"
                        ? "Retorno almuerzo"
                        : "Salida"}
                  :
                </Text>
                <Badge
                  color={estadoHoy[campo] ? "emerald" : "gray"}
                  variant="light"
                  size="sm"
                >
                  {estadoHoy[campo]?.substring(0, 5) ?? "Sin registro"}
                </Badge>
              </Group>
            ))}
          </Stack>
        ) : (
          <Text size="sm" c="dimmed">
            Sin marcaciones hoy.
          </Text>
        )}
      </Card>

      {/* Ubicación */}
      {cargandoUbicacion ? (
        <Group gap="xs">
          <Loader size="xs" />
          <Text size="xs" c="dimmed">
            Obteniendo ubicación...
          </Text>
        </Group>
      ) : ubicacion ? (
        <Text size="xs" c="dimmed">
          Lat: {ubicacion.lat.toFixed(4)}, Lon: {ubicacion.lon.toFixed(4)}
        </Text>
      ) : (
        <Alert color="orange" variant="light">
          <Text size="xs">
            No se pudo obtener la ubicación. Activa el GPS para registrar tu
            posición.
          </Text>
        </Alert>
      )}

      {/* Botones */}
      <Stack gap="sm">
        <Button
          size="lg"
          color="emerald"
          variant="filled"
          leftSection={<IconLogin size={20} />}
          loading={registrando}
          onClick={() => registrar("I", "Entrada")}
          fullWidth
        >
          Registrar Entrada
        </Button>
        <Button
          size="lg"
          color="orange"
          variant="light"
          leftSection={<IconSoup size={20} />}
          loading={registrando}
          onClick={() => registrar("O", "Salida Almuerzo")}
          fullWidth
        >
          Salida Almuerzo
        </Button>
        <Button
          size="lg"
          color="blue"
          variant="light"
          leftSection={<IconSoup size={20} />}
          loading={registrando}
          onClick={() => registrar("I", "Retorno Almuerzo")}
          fullWidth
        >
          Retorno Almuerzo
        </Button>
        <Button
          size="lg"
          color="red"
          variant="light"
          leftSection={<IconLogout size={20} />}
          loading={registrando}
          onClick={() => registrar("I", "Salida")}
          fullWidth
        >
          Registrar Salida
        </Button>
      </Stack>
    </Stack>
  );
}
