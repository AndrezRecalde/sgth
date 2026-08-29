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

  /**
   * Qué cambiaría al forzar el recálculo.
   *
   * Es una lectura, pero va como mutación y no como query porque se dispara al
   * pulsar una fila concreta: no hay nada que precargar ni cachear para las
   * demás filas de la tabla.
   */
  const previsualizarRecalculo = useMutation({
    mutationFn: ({ servidorId, anio }: { servidorId: number; anio: number }) =>
      asistenciaService.periodos.previsualizarRecalculo(servidorId, anio),
    onError,
  });

  /**
   * Recálculo deliberado de un año ya cerrado.
   *
   * El mensaje del backend dice el saldo antes y después, así que se muestra
   * tal cual en vez de un «listo» genérico: quien lo pidió necesita ver qué
   * cambió, y queda además en la bitácora del sistema.
   */
  const recalcularCerrado = useMutation({
    mutationFn: ({ servidorId, anio }: { servidorId: number; anio: number }) =>
      asistenciaService.periodos.recalcularCerrado(servidorId, anio),
    onSuccess: (respuesta) => {
      notifications.show({
        title: "Período recalculado",
        message: respuesta.mensaje ?? "El período cerrado fue recalculado.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
        autoClose: 8000,
      });
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  return { generar, generarTodos, previsualizarRecalculo, recalcularCerrado };
}
