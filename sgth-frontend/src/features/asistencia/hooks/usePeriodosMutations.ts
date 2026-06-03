import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import { IconCheck, IconX } from "@tabler/icons-react";
import React from "react";
import { asistenciaService } from "../services/asistenciaService";

export function usePeriodosMutations() {
  const qc = useQueryClient();

  const onError = () =>
    notifications.show({
      title: "Error",
      message: "No se pudo completar la operación.",
      color: "red",
      icon: React.createElement(IconX, { size: 16 }),
    });

  const generar = useMutation({
    mutationFn: ({ servidorId, anio }: { servidorId: number; anio: number }) =>
      asistenciaService.periodos.generar(servidorId, anio),
    onSuccess: (_, vars) => {
      notifications.show({
        title: "Período generado",
        message: `Período ${vars.anio} generado correctamente.`,
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  const generarTodos = useMutation({
    mutationFn: (anio: number) => asistenciaService.periodos.generarTodos(anio),
    onSuccess: (data) => {
      const generados = (data as { generados?: number })?.generados ?? 0;
      notifications.show({
        title: "Períodos generados",
        message: `Se generaron ${generados} períodos correctamente.`,
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  return { generar, generarTodos };
}
