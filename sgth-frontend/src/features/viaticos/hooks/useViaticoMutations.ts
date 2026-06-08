import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import { IconCheck, IconX } from "@tabler/icons-react";
import React from "react";
import { viaticoService } from "../services/viaticoService";
import { getApiErrorMessage } from "@/types/api";

export function useViaticoMutations() {
  const qc = useQueryClient();

  const invalidar = (id?: number, codigo?: string | number) => {
    qc.invalidateQueries({ queryKey: ["viaticos"] });
    if (id) {
      qc.invalidateQueries({ queryKey: ["viatico", id] });
    }
    if (codigo) {
      qc.invalidateQueries({ queryKey: ["viatico", String(codigo)] });
      qc.invalidateQueries({ queryKey: ["viatico", Number(codigo)] });
    }
  };

  const onError = (error: unknown) =>
    notifications.show({
      title: "Error",
      message: getApiErrorMessage(error),
      color: "red",
      icon: React.createElement(IconX, { size: 16 }),
    });

  // Mutación de estado — invalida por id Y por codigo
  const createEstadoMutation = (
    mutationFn: (id: number) => Promise<unknown>,
    title: string,
    message: string,
  ) => ({
    mutationFn,
    onSuccess: (_data: unknown, id: number) => {
      notifications.show({
        title,
        message,
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      // Invalida AMBAS formas del query key
      qc.invalidateQueries({ queryKey: ["viaticos"] });
      qc.invalidateQueries({ queryKey: ["viatico", id] });
      // Invalida también queries que usen string
      qc.invalidateQueries({ queryKey: ["viatico"] });
    },
    onError,
  });

  const solicitar = useMutation({
    mutationFn: (data: Parameters<typeof viaticoService.solicitar>[0]) =>
      viaticoService.solicitar(data),
    onSuccess: () => {
      notifications.show({
        title: "Viático solicitado",
        message: "La solicitud fue registrada correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar();
    },
    onError,
  });

  const actualizar = useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id: number;
      data: Parameters<typeof viaticoService.actualizar>[1];
    }) => viaticoService.actualizar(id, data),
    onSuccess: (_data, { id }) => {
      notifications.show({
        title: "Cambios guardados",
        message: "El viático fue actualizado correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      invalidar(id);
    },
    onError,
  });

  const aprobar = useMutation(createEstadoMutation(
    viaticoService.aprobar,
    "Viático aprobado",
    "El viático fue aprobado correctamente.",
  ));

  const entregarAnticipo = useMutation(createEstadoMutation(
    viaticoService.entregarAnticipo,
    "Anticipo entregado",
    "El anticipo fue registrado como entregado.",
  ));

  const marcarEnComision = useMutation(createEstadoMutation(
    viaticoService.marcarEnComision,
    "En comisión",
    "El servidor ha sido marcado en comisión.",
  ));

  const marcarPendienteLiquidacion = useMutation(createEstadoMutation(
    viaticoService.marcarPendienteLiquidacion,
    "Pendiente de liquidación",
    "El viático queda pendiente de liquidación.",
  ));

  const contabilizar = useMutation(createEstadoMutation(
    viaticoService.contabilizar,
    "Viático contabilizado",
    "La liquidación fue contabilizada correctamente.",
  ));

  const liquidar = useMutation({
    mutationFn: ({
      viaticoId,
      data,
    }: {
      viaticoId: number;
      data: Parameters<typeof viaticoService.liquidar>[1];
    }) => viaticoService.liquidar(viaticoId, data),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title: "Viático liquidado",
        message: "La liquidación fue registrada correctamente.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      // Invalida el viático específico
      qc.invalidateQueries({ queryKey: ["viaticos"] });
      qc.invalidateQueries({ queryKey: ["viatico", viaticoId] });
      qc.invalidateQueries({ queryKey: ["viatico"] });
    },
    onError,
  });

  return {
    solicitar,
    aprobar,
    actualizar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
    liquidar,
  };
}
