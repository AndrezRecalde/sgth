"use client";

import { useState, useEffect } from "react";
import {
  Drawer,
  Stack,
  TextInput,
  MultiSelect,
  Button,
  Group,
  Divider,
  Text,
  Badge,
  Paper,
  Avatar,
  Loader,
  ThemeIcon,
  Alert,
  ScrollArea,
  ActionIcon,
} from "@mantine/core";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod/v4";
import {
  IconUser,
  IconSearch,
  IconCheck,
  IconArrowLeft,
  IconX,
} from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { useUsuarioMutations } from "../hooks/useUsuarioMutations";
import { usuarioService } from "../services/usuarioService";
import type { Usuario } from "@/types/api";

const schema = z.object({
  servidor_id: z.number({ error: "Seleccione un servidor" }),
  email: z.string().email("Email inválido"),
  usuario_ti: z
    .string()
    .min(3, "Mínimo 3 caracteres")
    .regex(/^[a-z0-9]+$/, "Solo letras minúsculas y números"),
  roles: z.array(z.string()).min(1, "Asigne al menos un rol"),
});

type FormData = z.infer<typeof schema>;

const ROL_LABELS: Record<string, string> = {
  "admin-ti": "Admin TI",
  "admin-uath": "Admin UATH",
  "asistente-uath": "Asistente UATH",
  "maxima-autoridad": "Máxima Autoridad",
  director: "Director",
  "jefe-unidad": "Jefe de Unidad",
  servidor: "Servidor",
  recepcion: "Recepción",
  "trabajo-social": "Trabajo Social",
  medico: "Médico",
  odontologo: "Odontólogo",
  enfermera: "Enfermera",
  "admin-dispensario": "Admin Dispensario",
  "tecnico-dtic": "Técnico DTIC",
  auditor: "Auditor",
};

const ROLES_DISPONIBLES = Object.entries(ROL_LABELS).map(([value, label]) => ({
  value,
  label,
}));

type ServidorItem = {
  id: number;
  cedula: string;
  nombre_completo: string;
};

type Paso = "buscar" | "configurar";

interface Props {
  opened: boolean;
  onClose: () => void;
  usuario?: Usuario | null;
}

export function UsuarioDrawer({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint();
  const contained = useContainedInput();
  const { crear, actualizar } = useUsuarioMutations();

  const modoEditar = !!usuario;

  const [paso, setPaso] = useState<Paso>("buscar");
  const [busqueda, setBusqueda] = useState("");
  const [queryBusq, setQueryBusq] = useState("");
  const [servidorSel, setServidorSel] = useState<ServidorItem | null>(null);
  const [cargandoTi, setCargandoTi] = useState(false);
  const [resultados, setResultados] = useState<ServidorItem[]>([]);
  const [buscando, setBuscando] = useState(false);

  const {
    register,
    control,
    handleSubmit,
    setValue,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      servidor_id: undefined,
      email: usuario?.email ?? "",
      usuario_ti: usuario?.usuario_ti ?? "",
      roles: (usuario?.roles as string[]) ?? [],
    },
  });

  useEffect(() => {
    if (opened) {
      if (usuario) {
        reset({
          servidor_id: usuario.servidor_id ?? undefined,
          email: usuario.email ?? "",
          usuario_ti: usuario.usuario_ti ?? "",
          roles: (usuario.roles as string[]) ?? [],
        });
      } else {
        reset({
          servidor_id: undefined,
          email: "",
          usuario_ti: "",
          roles: [],
        });
      }
    }
  }, [opened, usuario, reset]);

  const handleClose = () => {
    reset();
    setPaso("buscar");
    setBusqueda("");
    setQueryBusq("");
    setServidorSel(null);
    setResultados([]);
    onClose();
  };

  const handleBuscar = async () => {
    if (!busqueda.trim()) return;
    setBuscando(true);
    try {
      const res = await usuarioService.servidoresSinUsuario(busqueda.trim());
      setResultados(res ?? []);
      setQueryBusq(busqueda.trim());
    } catch {
      setResultados([]);
    } finally {
      setBuscando(false);
    }
  };

  const handleSeleccionar = async (s: ServidorItem) => {
    setServidorSel(s);
    setValue("servidor_id", s.id);
    setCargandoTi(true);
    try {
      const res = await usuarioService.sugerirUsuarioTi(s.id);
      setValue("usuario_ti", res?.usuario_ti_sugerido ?? "");
    } catch {
      setValue("usuario_ti", "");
    } finally {
      setCargandoTi(false);
    }
    setPaso("configurar");
  };

  const onSubmit = async (values: FormData) => {
    try {
      if (modoEditar && usuario) {
        await actualizar.mutateAsync({
          id: Number(usuario.id),
          data: {
            email: values.email,
            usuario_ti: values.usuario_ti,
            roles: values.roles,
          },
        });
      } else {
        await crear.mutateAsync(values as never);
      }
      handleClose();
    } catch {}
  };

  return (
    <Drawer
      opened={opened}
      onClose={handleClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="md" radius="md">
            <IconUser size={16} />
          </ThemeIcon>
          <Text fw={700}>
            {modoEditar
              ? "Editar usuario"
              : paso === "buscar"
                ? "Buscar servidor"
                : "Configurar acceso"}
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? "100%" : 520}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
        <form onSubmit={handleSubmit(onSubmit)}>
          <Stack gap="md">
            {/* MODO EDITAR — directo a configurar */}
            {modoEditar && usuario && (
              <>
                <Paper
                  withBorder
                  radius="md"
                  p="md"
                  style={{
                    borderLeft: "4px solid var(--mantine-color-emerald-6)",
                  }}
                >
                  <Group gap="sm">
                    <ThemeIcon
                      color="emerald"
                      variant="light"
                      size="lg"
                      radius="xl"
                    >
                      <IconUser size={18} />
                    </ThemeIcon>
                    <Stack gap={0}>
                      <Text size="sm" fw={600}>
                        {usuario.nombre_completo ||
                          usuario.servidor?.nombre ||
                          "—"}
                      </Text>
                      <Text size="xs" c="dimmed">
                        CI: {usuario.servidor?.cedula ?? "—"}
                      </Text>
                    </Stack>
                  </Group>
                </Paper>

                <Divider label="Datos de acceso" labelPosition="left" />

                <TextInput
                  label="Correo institucional"
                  placeholder="usuario@gadpe.gob.ec"
                  {...contained}
                  {...register("email")}
                  error={errors.email?.message}
                />

                <TextInput
                  label="Usuario del sistema"
                  placeholder="ej: jperez"
                  rightSection={cargandoTi ? <Loader size="xs" /> : undefined}
                  {...contained}
                  {...register("usuario_ti")}
                  error={errors.usuario_ti?.message}
                />

                <Controller
                  name="roles"
                  control={control}
                  render={({ field }) => (
                    <MultiSelect
                      label="Roles del sistema"
                      placeholder="Seleccione uno o más roles"
                      data={ROLES_DISPONIBLES}
                      searchable
                      {...contained}
                      value={field.value}
                      onChange={field.onChange}
                      error={errors.roles?.message}
                    />
                  )}
                />

                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleClose}>
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    color="emerald"
                    loading={isSubmitting || actualizar.isPending}
                    leftSection={<IconCheck size={14} />}
                  >
                    Guardar cambios
                  </Button>
                </Group>
              </>
            )}

            {/* MODO CREAR — PASO 1: Buscar servidor */}
            {!modoEditar && paso === "buscar" && (
              <Stack gap="md">
                <Text size="sm" c="dimmed">
                  Busca al servidor por cédula o nombre para crearle un acceso
                  al sistema.
                </Text>

                <TextInput
                  label="Cédula o nombre del servidor"
                  placeholder="Ej: 0800123456 o Juan Pérez"
                  {...contained}
                  value={busqueda}
                  onChange={(e) => setBusqueda(e.currentTarget.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      handleBuscar();
                    }
                  }}
                  style={{ flex: 1 }}
                  rightSection={
                    busqueda ? (
                      <ActionIcon
                        size="sm"
                        variant="subtle"
                        color="gray"
                        onClick={() => {
                          setBusqueda("");
                          setQueryBusq("");
                          setResultados([]);
                        }}
                      >
                        <IconX size={12} />
                      </ActionIcon>
                    ) : null
                  }
                />
                <Button
                  variant="light"
                  loading={buscando}
                  onClick={handleBuscar}
                  leftSection={<IconSearch size={14} />}
                >
                  Buscar
                </Button>

                {queryBusq && resultados.length === 0 && !buscando && (
                  <Alert color="gray" variant="light">
                    <Text size="xs">
                      No se encontraron servidores sin usuario para {queryBusq}.
                    </Text>
                  </Alert>
                )}

                {resultados.length > 0 && (
                  <Stack gap="xs">
                    <Text size="xs" c="dimmed">
                      {resultados.length} resultado(s) — selecciona el servidor:
                    </Text>
                    {resultados.map((s) => (
                      <Paper
                        key={s.id}
                        withBorder
                        radius="md"
                        p="sm"
                        style={{ cursor: "pointer" }}
                        onClick={() => handleSeleccionar(s)}
                      >
                        <Group justify="space-between">
                          <Group gap="sm">
                            <Avatar color="emerald" size="md" radius="xl">
                              {s.nombre_completo
                                .split(" ")
                                .slice(0, 2)
                                .map((w) => w[0])
                                .join("")
                                .toUpperCase()}
                            </Avatar>
                            <Stack gap={0}>
                              <Text size="sm" fw={600}>
                                {s.nombre_completo}
                              </Text>
                              <Text size="xs" c="dimmed">
                                CI: {s.cedula}
                              </Text>
                            </Stack>
                          </Group>
                          <Badge color="emerald" variant="light" size="sm">
                            Seleccionar
                          </Badge>
                        </Group>
                      </Paper>
                    ))}
                  </Stack>
                )}
              </Stack>
            )}

            {/* MODO CREAR — PASO 2: Configurar acceso */}
            {!modoEditar && paso === "configurar" && servidorSel && (
              <Stack gap="md">
                <Paper
                  withBorder
                  radius="md"
                  p="md"
                  style={{
                    borderLeft: "4px solid var(--mantine-color-emerald-6)",
                  }}
                >
                  <Group justify="space-between">
                    <Group gap="sm">
                      <ThemeIcon
                        color="emerald"
                        variant="light"
                        size="lg"
                        radius="xl"
                      >
                        <IconCheck size={18} />
                      </ThemeIcon>
                      <Stack gap={0}>
                        <Text size="sm" fw={600}>
                          {servidorSel.nombre_completo}
                        </Text>
                        <Text size="xs" c="dimmed">
                          CI: {servidorSel.cedula}
                        </Text>
                      </Stack>
                    </Group>
                    <Button
                      size="xs"
                      variant="subtle"
                      color="gray"
                      leftSection={<IconArrowLeft size={12} />}
                      onClick={() => {
                        setPaso("buscar");
                        setServidorSel(null);
                        setValue("servidor_id", undefined as never);
                        setValue("usuario_ti", "");
                        setValue("email", "");
                      }}
                    >
                      Cambiar
                    </Button>
                  </Group>
                </Paper>

                <Divider label="Datos de acceso" labelPosition="left" />

                <TextInput
                  label="Correo institucional"
                  placeholder="usuario@gadpe.gob.ec"
                  {...contained}
                  {...register("email")}
                  error={errors.email?.message}
                />

                <TextInput
                  label="Usuario del sistema"
                  placeholder="ej: jperez"
                  description="Solo letras minúsculas y números"
                  rightSection={cargandoTi ? <Loader size="xs" /> : undefined}
                  {...contained}
                  {...register("usuario_ti")}
                  error={errors.usuario_ti?.message}
                />

                <Controller
                  name="roles"
                  control={control}
                  render={({ field }) => (
                    <MultiSelect
                      label="Roles del sistema"
                      placeholder="Seleccione uno o más roles"
                      data={ROLES_DISPONIBLES}
                      searchable
                      {...contained}
                      value={field.value}
                      onChange={field.onChange}
                      error={errors.roles?.message}
                    />
                  )}
                />

                <Group justify="flex-end" mt="md">
                  <Button variant="default" onClick={handleClose}>
                    Cancelar
                  </Button>
                  <Button
                    type="submit"
                    color="emerald"
                    loading={isSubmitting || crear.isPending}
                    leftSection={<IconCheck size={14} />}
                  >
                    Crear usuario
                  </Button>
                </Group>
              </Stack>
            )}
          </Stack>
        </form>
      </ScrollArea>
    </Drawer>
  );
}
