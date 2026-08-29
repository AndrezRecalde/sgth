"use client";

import { useQuery } from "@tanstack/react-query";
import { Center, Loader, Grid } from "@mantine/core";
import api from "@/lib/axios";
import { useAuth } from "@/hooks/useAuth";
import type { UsuarioAuth } from "@/store/auth.store";
import { PerfilServidorCard } from "@/features/portal/components/PerfilServidorCard";
import { NoticiasCard } from "@/features/portal/components/NoticiasCard";
import { PageShell } from "@/components/ui";

export default function PortalHomePage() {
  const { usuario, token, setAuth } = useAuth();

  const { data: perfil, isLoading } = useQuery({
    queryKey: ["mi-perfil-portal"],
    queryFn: async () => {
      const res = await api.get<{
        datos: UsuarioAuth;
      }>("/auth/perfil");
      const data = res.data.datos;
      if (data && token) {
        setAuth(token, data);
      }
      return data;
    },
    enabled: !!token,
    staleTime: 1000 * 60 * 5,
  });

  const usuarioActual = perfil ?? usuario;

  if (isLoading || !usuarioActual) {
    return (
      <Center h="60vh">
        <Loader color="emerald" size="lg" type="dots" />
      </Center>
    );
  }

  return (
    <PageShell>
      <Grid>
        <Grid.Col span={{ base: 12, md: 6, lg: 6 }}>
          <PerfilServidorCard usuario={usuarioActual} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 6, lg: 6 }}>
          <NoticiasCard />
        </Grid.Col>
      </Grid>
    </PageShell>
  );
}
