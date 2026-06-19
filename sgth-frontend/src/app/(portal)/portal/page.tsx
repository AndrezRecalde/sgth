"use client";

import { Center, Loader, Container, Grid } from "@mantine/core";
import { useAuth } from "@/hooks/useAuth";
import { PerfilServidorCard } from "@/features/portal/components/PerfilServidorCard";
import { NoticiasCard } from "@/features/portal/components/NoticiasCard";

export default function PortalHomePage() {
  const { usuario } = useAuth();

  if (!usuario) {
    return (
      <Center h="60vh">
        <Loader color="emerald" size="lg" type="dots" />
      </Center>
    );
  }

  return (
    <Container size="xl">
      <Grid>
        <Grid.Col span={6}>
          <PerfilServidorCard usuario={usuario} />
        </Grid.Col>
        <Grid.Col span={6}>
          <NoticiasCard />
        </Grid.Col>
      </Grid>
    </Container>
  );
}
