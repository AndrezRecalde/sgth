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

  const aprobar = useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id:   number
      data?: { coeficiente_exterior?: number; pais_destino?: string }
    }) => viaticoService.aprobar(id, data),
    onSuccess: (_data, { id }) => {
      notifications.show({
        title:   "Viático aprobado",
        message: "El viático fue aprobado correctamente.",
        color:   "emerald",
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ["viaticos"] })
      qc.invalidateQueries({ queryKey: ["viatico", id] })
      qc.invalidateQueries({ queryKey: ["viatico"] })
    },
    onError,
  })

  const cancelar = useMutation(createEstadoMutation(
    viaticoService.cancelar,
    "Solicitud cancelada",
    "El viático fue cancelado correctamente.",
  ))

  const rechazar = useMutation(createEstadoMutation(
    viaticoService.rechazar,
    "Viático rechazado",
    "El viático fue rechazado correctamente.",
  ))

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

  const guardarActividades = useMutation({
    mutationFn: ({
      viaticoId,
      actividades,
    }: {
      viaticoId:   number
      actividades: Parameters<
        typeof viaticoService.liquidacion.guardarActividades
      >[1]
    }) => viaticoService.liquidacion.guardarActividades(
      viaticoId, actividades
    ),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title:   'Actividades guardadas',
        message: 'Las actividades se guardaron correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['liquidacion', viaticoId]
      })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError,
  })

  const guardarFacturas = useMutation({
    mutationFn: ({
      viaticoId,
      facturas,
    }: {
      viaticoId: number
      facturas:  Parameters<
        typeof viaticoService.liquidacion.guardarFacturas
      >[1]
    }) => viaticoService.liquidacion.guardarFacturas(
      viaticoId, facturas
    ),
    onSuccess: (_data, { viaticoId }) => {
      notifications.show({
        title:   'Facturas guardadas',
        message: 'Los comprobantes se guardaron correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({
        queryKey: ['liquidacion', viaticoId]
      })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError,
  })

  const confirmarLiquidacion = useMutation({
    mutationFn: (viaticoId: number) =>
      viaticoService.liquidacion.confirmar(viaticoId),
    onSuccess: () => {
      notifications.show({
        title:   'Liquidación registrada',
        message: 'La liquidación fue confirmada correctamente.',
        color:   'emerald',
        icon:    React.createElement(IconCheck, { size: 16 }),
      })
      qc.invalidateQueries({ queryKey: ['viaticos'] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
      qc.invalidateQueries({ queryKey: ['liquidacion'] })
    },
    onError,
  })

  return {
    solicitar,
    aprobar,
    cancelar,
    rechazar,
    actualizar,
    entregarAnticipo,
    marcarEnComision,
    marcarPendienteLiquidacion,
    contabilizar,
    liquidar,
    guardarActividades,
    guardarFacturas,
    confirmarLiquidacion,
  };
}
