"use client";

import {
  TextInput,
  PasswordInput,
  Button,
  Stack,
  Checkbox,
  Anchor,
  Group,
} from "@mantine/core";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useContainedInput } from "@/hooks/useContainedInput";
import { loginSchema, type LoginFormData } from "../schemas/login.schema";
import { useLogin } from "../hooks/useLogin";

export function LoginForm() {
  const { mutate, isPending } = useLogin();
  const contained = useContainedInput();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: { usuario: "", contrasena: "" },
  });

  return (
    <form onSubmit={handleSubmit((v) => mutate(v))} noValidate>
      <Stack gap="md">
        <TextInput
          label="Usuario"
          placeholder="admin@fusetheme.com"
          {...contained}
          {...register("usuario")}
          error={errors.usuario?.message}
        />
        <PasswordInput
          label="Contraseña"
          placeholder="••••••••••••"
          {...contained}
          {...register("contrasena")}
          error={errors.contrasena?.message}
        />

        <Group justify="space-between" align="center">
          <Checkbox label="Recordarme" size="sm" />
          <Anchor
            size="sm"
            href="https://www.gadpe.gob.ec/webmail"
            target="_blank"
            c="dimmed"
          >
            ¿Olvidaste tu contraseña?
          </Anchor>
        </Group>

        <Button type="submit" fullWidth mt="xs" loading={isPending} radius="xl">
          Iniciar sesión
        </Button>
      </Stack>
    </form>
  );
}
