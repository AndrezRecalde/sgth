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
  IconGrain,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";

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
          onClick={() => router.push("/")}
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
        <Tooltip label="Aplicaciones">
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            radius="xl"
            aria-label="Aplicaciones"
          >
            <IconGrain size={25} />
          </ActionIcon>
        </Tooltip>
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
