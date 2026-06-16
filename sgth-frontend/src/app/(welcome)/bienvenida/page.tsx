"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import api from "@/lib/axios";
import type { UsuarioAuth } from "@/store/auth.store";
import {
  Stack,
  Group,
  Text,
  Badge,
  Card,
  Center,
  Loader,
  ThemeIcon,
  SimpleGrid,
  Avatar,
  Box,
  Title,
  Paper,
  ActionIcon,
} from "@mantine/core";
import {
  IconUsers,
  IconBuildingHospital,
  IconUserCircle,
  IconPlane,
  IconCalendarEvent,
  IconBeach,
  IconStethoscope,
  IconPill,
  IconFolder,
  IconArrowRight,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { ROUTES } from "@/config/routes";
import { getSubsistemasDisponibles } from "@/config/nav";

const SUBSISTEMA_CONFIG = {
  sgth: {
    label: "SGTH",
    descripcion: "Gestión de Talento Humano",
    color: "emerald" as const,
    icon: IconUsers,
    home: ROUTES.SGTH.HOME,
    modulos: [
      { label: "Expediente", icon: IconFolder },
      { label: "Viáticos", icon: IconPlane },
      { label: "Asistencia", icon: IconCalendarEvent },
      { label: "Nómina", icon: IconUsers },
    ],
  },
  salud: {
    label: "Dispensario",
    descripcion: "Sistema de Salud Ambulatoria",
    color: "blue" as const,
    icon: IconBuildingHospital,
    home: ROUTES.SALUD.HOME,
    modulos: [
      { label: "Consultas", icon: IconStethoscope },
      { label: "Odontología", icon: IconStethoscope },
      { label: "Farmacia", icon: IconPill },
      { label: "SSO", icon: IconUsers },
    ],
  },
  portal: {
    label: "Portal servidor",
    descripcion: "Mi espacio personal",
    color: "violet" as const,
    icon: IconUserCircle,
    home: ROUTES.PORTAL.HOME,
    modulos: [
      { label: "Mi perfil", icon: IconUserCircle },
      { label: "Permisos", icon: IconCalendarEvent },
      { label: "Vacaciones", icon: IconBeach },
      { label: "Mis tickets", icon: IconFolder },
    ],
  },
} as const;

type SubsistemaKey = keyof typeof SUBSISTEMA_CONFIG;

export default function BienvenidaPage() {
  const router = useRouter();
  const { usuario, clearAuth, setAuth, token } = useAuth();

  useQuery({
    queryKey: ["mi-perfil-bienvenida"],
    queryFn: async () => {
      const res = await api.get<{
        datos: UsuarioAuth;
      }>("/auth/perfil");
      const perfil = res.data.datos;
      if (perfil && token) {
        setAuth(token, perfil);
      }
      return perfil;
    },
    enabled: !!token && !!usuario,
    staleTime: 0,
  });

  useEffect(() => {
    if (!usuario) {
      router.replace(ROUTES.AUTH.LOGIN);
    }
  }, [usuario, router]);

  if (!usuario) {
    return (
      <Center h="100vh">
        <Loader color="emerald" size="xl" type="dots" />
      </Center>
    );
  }

  const roles = (usuario.roles as string[]) ?? [];
  const disponibles = getSubsistemasDisponibles(roles);

  const nombreCompleto =
    usuario.nombre_completo || usuario.usuario_ti || usuario.email;

  const initials =
    (nombreCompleto ?? "")
      .split(" ")
      .slice(0, 2)
      .map((w: string) => w[0] ?? "")
      .join("")
      .toUpperCase() || "US";

  const handleIngresar = (key: SubsistemaKey) => {
    router.push(SUBSISTEMA_CONFIG[key].home);
  };

  return (
    <Box 
      style={{ 
        minHeight: "100dvh", 
        padding: "2rem"
      }}
      bg="var(--mantine-color-body)"
    >
      <Center>
        <Stack gap="xl" w="100%" maw={1000}>
          {/* Saludo Estilo Google */}
          <Box pt="xl" pb="lg" style={{ textAlign: "center" }}>
            <Title order={1} fw={400} size={42} mb="xs" style={{ letterSpacing: "-0.5px", color: "var(--mantine-color-text)" }}>
              Te damos la bienvenida, {nombreCompleto.split(" ")[0]}
            </Title>
            <Text size="lg" c="dimmed" fw={400}>
              {usuario.servidor?.puesto?.nombre ?? "Analista de Talento Humano 2"} • {usuario.servidor?.unidad_administrativa?.nombre ?? "Dirección de Gestión de Talento Humano"}
            </Text>
          </Box>

          {/* Listado de Subsistemas */}
          <Box>
            <Text
              size="sm"
              fw={800}
              c="dimmed"
              tt="uppercase"
              mb="md"
              style={{ letterSpacing: "0.05em" }}
            >
              Tus subsistemas disponibles
            </Text>

            <SimpleGrid
              cols={{
                base: 1,
                sm: 2,
                md: 3,
                lg: 4,
              }}
              spacing="lg"
            >
              {disponibles.map((key) => {
                const cfg = SUBSISTEMA_CONFIG[key];
                const Icon = cfg.icon;

                return (
                  <Card
                    key={key}
                    withBorder
                    radius="lg"
                    p="xl"
                    shadow="none"
                    bg="transparent"
                    style={{
                      cursor: "pointer",
                      transition: "all 0.15s ease",
                      borderColor: "var(--mantine-color-default-border)",
                      display: "flex",
                      flexDirection: "column",
                      alignItems: "center",
                      justifyContent: "center",
                      textAlign: "center",
                      minHeight: 160,
                    }}
                    onClick={() => handleIngresar(key)}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = "var(--mantine-color-default-hover)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = "transparent";
                    }}
                  >
                    <Icon size={48} stroke={1.2} style={{ color: "var(--mantine-color-text)", marginBottom: "1rem" }} />

                    <Text fw={500} size="md" style={{ color: "var(--mantine-color-text)" }}>
                      {cfg.label}
                    </Text>
                  </Card>
                );
              })}
            </SimpleGrid>
          </Box>
        </Stack>
      </Center>
    </Box>
  );
}
