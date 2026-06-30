import { Metadata } from "next";
import Image from "next/image";
import {
  Box,
  Text,
  Title,
  Divider,
  Flex,
  Button,
  Container,
} from "@mantine/core";
import { IconMail } from "@tabler/icons-react";
import { LoginForm } from "@/features/auth/components/LoginForm";
import classes from "./login.module.css";

export const metadata: Metadata = {
  title: "Iniciar sesión",
};

export default function LoginPage() {
  return (
    <Flex className={classes.pageContainer}>
      {/* Left Column */}
      <Flex
        direction="column"
        w={{ base: "100%", md: 600, lg: 650 }}
        p={{ base: "xl", md: 60 }}
        justify="center"
        align="flex-end"
        wrap="wrap"
      >
        <Container size={400} px={0} w="100%">
          {/* Logo or Icon */}
          <Box mb="md">
            <Image
              src="/logo.png"
              alt="Logo Institucional"
              width={80}
              height={80}
              priority
            />
          </Box>

          <Title order={1} size="h2" fw={800} mb="xs">
            Iniciar sesión
          </Title>
          <LoginForm />
          <Divider label="O continuar con" labelPosition="center" my="lg" />
          <Button
            component="a"
            href="https://www.gadpe.gob.ec/webmail"
            target="_blank"
            variant="default"
            fullWidth
            radius="xl"
            leftSection={<IconMail size={20} />}
          >
            Correo Institucional
          </Button>

          <Box mt={60}>
            <Text size="xs" c="dimmed">
              Estás navegando en{" "}
              <Text span fw={700} c="dark">
                GADPE
              </Text>
              . Solo para uso institucional.
            </Text>
          </Box>
        </Container>
      </Flex>

      {/* Right Column */}
      <Box className={classes.rightColumn}>
        {/* Abstract SVG Background */}
        <svg
          style={{
            position: "absolute",
            top: 0,
            left: 0,
            width: "100%",
            height: "100%",
            zIndex: 0,
            pointerEvents: "none",
          }}
          viewBox="0 0 1000 1000"
          preserveAspectRatio="xMidYMid slice"
          xmlns="http://www.w3.org/2000/svg"
        >
          {/* Círculo enorme superior izquierdo */}
          <circle cx="150" cy="150" r="600" fill="rgba(0, 0, 0, 0.12)" />
          {/* Círculo gigante inferior derecho */}
          <circle cx="850" cy="850" r="700" fill="rgba(0, 0, 0, 0.18)" />
          {/* Círculo de acento centro-derecha */}
          <circle cx="1000" cy="400" r="350" fill="rgba(0, 0, 0, 0.08)" />
        </svg>

        <Box className={classes.rightContent}>
          <Title order={2} className={classes.welcomeTitle}>
            Bienvenido a nuestra intranet
          </Title>
          <Text c="gray.4" size="lg" mb="xl" className={classes.welcomeText}>
            El Sistema de Gestión de Talento Humano ayuda a organizar de forma
            eficiente los procesos del GAD Provincial de Esmeraldas.
          </Text>
        </Box>
      </Box>
    </Flex>
  );
}
