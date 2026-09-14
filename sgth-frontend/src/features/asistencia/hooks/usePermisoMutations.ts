import { useMutation, useQueryClient } from "@tanstack/react-query";
import { asistenciaService } from "../services/asistenciaService";
import { notificar } from "@/components/ui";

export function usePermisoMutations() {
  const qc = useQueryClient();
  const invalidar = () => qc.invalidateQueries({ queryKey: ["permisos"] });

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof asistenciaService.permisos.crear>[0]) =>
      asistenciaService.permisos.crear(data),
    onSuccess: () => {
      notificar.exito("Permiso registrado", "El permiso fue registrado correctamente.");
      invalidar();
      // «Mis permisos» del portal lee otra consulta: sin esto, el permiso
      // recién registrado no aparecía en la lista hasta recargar.
      qc.invalidateQueries({ queryKey: ["mis-permisos"] });
    },
    onError: notificar.alFallar("No se pudo registrar el permiso"),
  });

  const confirmar = useMutation({
    mutationFn: (folio: string) => asistenciaService.permisos.confirmar(folio),
    onSuccess: () => {
      notificar.exito("Permiso confirmado", "El permiso fue confirmado por Recepción.");
      invalidar();
    },
    onError: notificar.alFallar("No se pudo confirmar el permiso"),
  });

  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.permisos.anular(id, motivo),
    onSuccess: () => {
      notificar.exito("Permiso anulado", "El permiso fue anulado correctamente.");
      invalidar();
    },
    onError: notificar.alFallar("No se pudo anular el permiso"),
  });

  const rechazar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.permisos.rechazar(id, motivo),
    onSuccess: () => {
      notificar.exito(
        "Permiso rechazado",
        "El documento fue rechazado y el motivo quedó registrado.",
      );
      invalidar();
    },
    onError: notificar.alFallar("No se pudo rechazar el permiso"),
  });

  const revertirConfirmacion = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      asistenciaService.permisos.revertirConfirmacion(id, motivo),
    onSuccess: () => {
      notificar.exito(
        "Confirmación revertida",
        "El permiso vuelve a pendiente y se devolvió el saldo descontado.",
      );
      invalidar();
      // El saldo vacacional cambió: lo que lo muestre tiene que releerlo.
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError: notificar.alFallar("No se pudo revertir la confirmación"),
  });

  const validarTs = useMutation({
    mutationFn: (id: number) => asistenciaService.permisos.validarTs(id),
    onSuccess: () => {
      notificar.exito(
        "Validado por Trabajo Social",
        "El permiso fue validado correctamente.",
      );
      invalidar();
    },
    onError: notificar.alFallar("No se pudo validar el permiso"),
  });

  return { crear, confirmar, anular, validarTs, rechazar, revertirConfirmacion };
}
