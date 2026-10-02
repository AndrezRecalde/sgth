"use client";

import { TextInput, PasswordInput, Button, Stack, Text } from "@mantine/core";
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
        {/* `autoComplete` es lo que deja a los gestores de contraseñas
            reconocer el formulario y ofrecer lo guardado. */}
        <TextInput
          label="Usuario"
          placeholder="Su usuario del sistema"
          autoComplete="username"
          autoCapitalize="none"
          spellCheck={false}
          autoFocus
          {...contained}
          {...register("usuario")}
          error={errors.usuario?.message}
        />
        <PasswordInput
          label="Contraseña"
          placeholder="••••••••••••"
          autoComplete="current-password"
          {...contained}
          {...register("contrasena")}
          error={errors.contrasena?.message}
        />

        {/* Aquí había un «Recordarme» que no estaba conectado a nada —la
            sesión dura lo mismo se marque o no— y un «¿Olvidaste tu
            contraseña?» que abría el webmail, donde no se recupera nada. La
            contraseña la restablece TI, y eso es lo que hay que decir. */}
        <Text size="sm" c="dimmed">
          ¿Olvidó su contraseña? Solicite a la Dirección de TI que la
          restablezca.
        </Text>

        <Button type="submit" fullWidth loading={isPending} radius="xl">
          Iniciar sesión
        </Button>
      </Stack>
    </form>
  );
}
