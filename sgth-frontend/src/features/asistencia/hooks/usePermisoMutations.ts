import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import { IconCheck, IconX } from "@tabler/icons-react";
import React from "react";
import { asistenciaService } from "../services/asistenciaService";
import { getApiErrorMessage } from "@/types/api";

export function usePermisoMutations() {
  const qc = useQueryClient();
  const invalidar = () => qc.invalidateQueries({ queryKey: ["permisos"] });

  const onError = (error: unknown) =>
    notifications.show({
      title: "Error",
      message: getApiErrorMessage(error),
      color: "red",
      icon: React.createElement(IconX, { size: 16 }),
    });

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof asistenciaService.permisos.crear>[0]) =>
      asistenciaService.permisos.crear(data),
    onSuccess: () => {
      notifications.show({
        title: "Permiso registrado",
        message: "El permiso fue registrado correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError: (error: unknown) => {
      notifications.show({
        title: "Error",
        message: getApiErrorMessage(error),
        color: "red",
        icon: React.createElement(IconX, { size: 16 }),
      });
    },
  });

  const confirmar = useMutation({
    mutationFn: (folio: string) => asistenciaService.permisos.confirmar(folio),
    onSuccess: () => {
      notifications.show({
        title: "Permiso confirmado",
        message: "El permiso fue confirmado por Recepción.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError: (error: unknown) => {
      notifications.show({
        title: "Error",
        message: getApiErrorMessage(error),
        color: "red",
        icon: React.createElement(IconX, { size: 16 }),
      });
    },
  });

  const anular = useMutation({
    mutationFn: (id: number) => asistenciaService.permisos.anular(id),
    onSuccess: () => {
      notifications.show({
        title: "Permiso anulado",
        message: "El permiso fue anulado correctamente.",
        color: "orange",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError,
  });

  const rechazar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.permisos.rechazar(id, motivo),
    onSuccess: () => {
      notifications.show({
        title: "Permiso rechazado",
        message: "El documento fue rechazado y el motivo quedó registrado.",
        color: "orange",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError,
  });

  const revertirConfirmacion = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.permisos.revertirConfirmacion(id, motivo),
    onSuccess: () => {
      notifications.show({
        title: "Confirmación revertida",
        message:
          "El permiso vuelve a pendiente y se devolvió el saldo descontado.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
      // El saldo vacacional cambió: lo que lo muestre tiene que releerlo.
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  const validarTs = useMutation({
    mutationFn: (id: number) => asistenciaService.permisos.validarTs(id),
    onSuccess: () => {
      notifications.show({
        title: "Validado por Trabajo Social",
        message: "El permiso fue validado correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError,
  });

  return { crear, confirmar, anular, validarTs, rechazar, revertirConfirmacion };
}
