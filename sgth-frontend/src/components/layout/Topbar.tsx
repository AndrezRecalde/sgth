"use client";

import {
  Group,
  Burger,
  ActionIcon,
  useMantineColorScheme,
  Avatar,
  Menu,
  Text,
  UnstyledButton,
  Switch,
  Tooltip,
  Image,
  Indicator,
  Paper,
  Stack,
  Button,
} from "@mantine/core";
import {
  IconSun,
  IconMoon,
  IconBell,
  IconLogout,
  IconGridDots,
  IconBuildingHospital,
  IconUserCircle,
  IconContract,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { Popover, SimpleGrid } from "@mantine/core";
import { getSubsistemasDisponibles } from "@/config/nav";
import { ROUTES } from "@/config/routes";

const SUBSISTEMA_CONFIG = {
  sgth: {
    label: "SGTH",
    icon: IconContract,
    color: "emerald",
    home: ROUTES.SGTH.HOME,
  },
  salud: {
    label: "Salud",
    icon: IconBuildingHospital,
    color: "blue",
    home: ROUTES.SALUD.HOME,
  },
  portal: {
    label: "Portal",
    icon: IconUserCircle,
    color: "violet",
    home: ROUTES.PORTAL.HOME,
  },
} as const;

interface TopbarProps {
  mobileOpened: boolean;
  desktopOpened: boolean;
  onMobileToggle: () => void;
  onDesktopToggle: () => void;
}

export function Topbar({
  mobileOpened,
  desktopOpened,
  onMobileToggle,
  onDesktopToggle,
}: TopbarProps) {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  const { usuario, clearAuth } = useAuth();
  const router = useRouter();

  const displayName = usuario?.usuario_ti || "Usuario";

  const initials =
    displayName
      .split(" ")
      .slice(0, 2)
      .map((w: string) => w[0] ?? "")
      .join("")
      .toUpperCase() || "US";

  const roles = (usuario?.roles as string[]) ?? [];
  const disponibles = getSubsistemasDisponibles(roles);

  const handleLogout = (e?: React.MouseEvent) => {
    e?.preventDefault();
    clearAuth();
    window.location.href = "/login?logout=true";
  };

  return (
    <Group h="100%" px="md" justify="space-between" align="center">
      {/* Sección Izquierda: Burger y Logo */}
      <Group gap="sm">
        <Burger
          opened={mobileOpened}
          onClick={onMobileToggle}
          hiddenFrom="md"
          lineSize={2}
          size="sm"
        />
        <Burger
          opened={desktopOpened}
          onClick={onDesktopToggle}
          visibleFrom="md"
          lineSize={2}
          size="sm"
        />

        <Group
          gap="xs"
          style={{ cursor: "pointer" }}
          onClick={() => router.push("/bienvenida")}
        >
          {/* <ThemeIcon variant="light" color="emerald" size="md" radius="lg">
            <IconUsers size={16} />
          </ThemeIcon> */}
          <Image
            radius="lg"
            mx="auto"
            h={40}
            w={60}
            fit="contain"
            alt="logo"
            src={
              "https://prefecturadeesmeraldas.gob.ec/wp-content/uploads/2026/05/LogoCompleto-2.png"
            }
            fallbackSrc="https://placehold.co/600x400?text=Placeholder"
          />
          <Text fw={800} size="lg" style={{ letterSpacing: "-0.5px" }}>
            GADPE
          </Text>
        </Group>
      </Group>

      {/* Centro: Vacío para separar */}
      <Group style={{ flex: 1 }} />

      {/* Sección Derecha: Notificaciones y Menú de Usuario */}
      <Group gap="md">
        <Tooltip label="Notificaciones">
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            radius="xl"
            aria-label="Notificaciones"
          >
            <IconBell size={25} />
          </ActionIcon>
        </Tooltip>
        <Popover
          width={320}
          position="bottom-end"
          shadow="xl"
          radius="xl"
          withArrow
        >
          <Popover.Target>
            <Tooltip label="Aplicaciones de GADPE">
              <ActionIcon
                variant="subtle"
                color="gray"
                size="lg"
                radius="xl"
                aria-label="Aplicaciones"
              >
                <IconGridDots size={25} />
              </ActionIcon>
            </Tooltip>
          </Popover.Target>
          <Popover.Dropdown p="lg">
            <Text size="sm" fw={700} mb="lg" c="dimmed">
              Tus aplicaciones
            </Text>
            <SimpleGrid cols={3} spacing="md">
              {disponibles.map((key) => {
                const config =
                  SUBSISTEMA_CONFIG[key as keyof typeof SUBSISTEMA_CONFIG];
                const Icon = config.icon;
                return (
                  <UnstyledButton
                    key={key}
                    onClick={() => router.push(config.home)}
                    style={{
                      display: "flex",
                      flexDirection: "column",
                      alignItems: "center",
                      justifyContent: "center",
                      padding: "8px",
                      borderRadius: "var(--mantine-radius-md)",
                      transition: "background-color 0.2s ease",
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor =
                        "var(--mantine-color-default-hover)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = "transparent";
                    }}
                  >
                    <Avatar
                      size={50}
                      radius="100%"
                      color={config.color}
                      variant="light"
                      mb={8}
                    >
                      <Icon size={28} stroke={1.5} />
                    </Avatar>
                    <Text size="xs" fw={500} ta="center">
                      {config.label}
                    </Text>
                  </UnstyledButton>
                );
              })}
            </SimpleGrid>
          </Popover.Dropdown>
        </Popover>
        <Menu
          width={340}
          position="bottom-end"
          shadow="xl"
          radius={24}
          withinPortal
          transitionProps={{ transition: "pop", duration: 150 }}
        >
          <Menu.Target>
            <UnstyledButton
              style={{ display: "flex", alignItems: "center" }}
              aria-label="Menú de usuario"
            >
              <Avatar
                color="emerald"
                size={45}
                style={{ cursor: "pointer", fontWeight: 800 }}
              >
                {initials}
              </Avatar>
            </UnstyledButton>
          </Menu.Target>

          <Menu.Dropdown p="md">
            {/* Cabecera: Email */}
            <Text size="sm" fw={600} ta="center" mt="xs">
              {usuario?.email || "correo@ejemplo.com"}
            </Text>

            {/* Avatar & Saludo */}
            <Stack align="center" gap={4} mt="md">
              <Indicator
                inline
                size={26}
                offset={10}
                position="bottom-end"
                color="emerald"
                withBorder
                processing
              >
                <Avatar
                  color="emerald"
                  size={84}
                  radius="100%"
                  style={{
                    border: "2px solid var(--mantine-color-emerald-filled)",
                    fontWeight: 700,
                    fontSize: "32px",
                  }}
                >
                  {initials}
                </Avatar>
              </Indicator>
              <Text size="xl" fw={400} mt="sm">
                ¡Hola, {displayName.split(" ")[0]}!
              </Text>
              <Button
                variant="default"
                radius="xl"
                size="sm"
                mt="xs"
                fw={600}
                onClick={() => router.push("/configuracion")}
              >
                Gestionar tu cuenta
              </Button>
            </Stack>

            {/* Bloque de Acciones */}
            <Paper
              radius="xl"
              withBorder
              bg="var(--mantine-color-default-hover)"
              mt="xl"
              p={4}
            >
              <Menu.Item
                closeMenuOnClick={false}
                leftSection={
                  colorScheme === "dark" ? (
                    <IconSun size={20} />
                  ) : (
                    <IconMoon size={20} />
                  )
                }
                rightSection={
                  <Switch
                    checked={colorScheme === "dark"}
                    size="sm"
                    color="emerald"
                    style={{ pointerEvents: "none" }}
                  />
                }
                onClick={() => toggleColorScheme()}
                style={{ borderRadius: "var(--mantine-radius-xl)" }}
                py="sm"
              >
                <Text size="sm" fw={600}>
                  Modo oscuro
                </Text>
              </Menu.Item>

              <Menu.Item
                leftSection={<IconLogout size={20} />}
                color="red"
                onClick={handleLogout}
                style={{ borderRadius: "var(--mantine-radius-xl)" }}
                py="sm"
              >
                <Text size="sm" fw={600}>
                  Cerrar sesión en todas las cuentas
                </Text>
              </Menu.Item>
            </Paper>

            {/* Footer de enlaces */}
            <Group justify="center" gap="xs" mt="lg" mb="sm">
              <Text size="xs" c="dimmed" style={{ cursor: "pointer" }}>
                Política de Privacidad
              </Text>
              <Text size="xs" c="dimmed">
                •
              </Text>
              <Text size="xs" c="dimmed" style={{ cursor: "pointer" }}>
                Términos del Servicio
              </Text>
            </Group>
          </Menu.Dropdown>
        </Menu>
      </Group>
    </Group>
  );
}
