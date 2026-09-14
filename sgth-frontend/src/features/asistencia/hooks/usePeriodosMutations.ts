import { useMutation, useQueryClient } from "@tanstack/react-query";
import { asistenciaService } from "../services/asistenciaService";
import { getApiErrorMessage } from "@/types/api";
import { notificar } from "@/components/ui";

export function usePeriodosMutations() {
  const qc = useQueryClient();

  const onError = () =>
    notificar.error("Error", "No se pudo completar la operación.");

  const generar = useMutation({
    mutationFn: ({ servidorId, anio }: { servidorId: number; anio: number }) =>
      asistenciaService.periodos.generar(servidorId, anio),
    onSuccess: (_, vars) => {
      notificar.exito("Período generado", `Período ${vars.anio} generado correctamente.`);
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  const generarTodos = useMutation({
    mutationFn: (anio: number) => asistenciaService.periodos.generarTodos(anio),
    onSuccess: (data) => {
      const generados = (data as { generados?: number })?.generados ?? 0;
      notificar.exito(
        "Períodos generados",
        `Se generaron ${generados} períodos correctamente.`,
      );
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
      notificar.exito(
        "Período recalculado",
        respuesta.mensaje ?? "El período cerrado fue recalculado.",
        { autoClose: 8000 },
      );
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError,
  });

  /**
   * Vencer el excedente sobre el tope. Quita días a una persona: el mensaje
   * del backend dice cuántos y cómo quedó el saldo, y un error se muestra con
   * su motivo, no con un «no se pudo» genérico.
   */
  const vencerExcedente = useMutation({
    mutationFn: (servidorId: number) =>
      asistenciaService.periodos.vencerExcedente(servidorId),
    onSuccess: (respuesta) => {
      notificar.exito("Excedente vencido", respuesta.mensaje, { autoClose: 8000 });
      qc.invalidateQueries({ queryKey: ["periodos-vacaciones"] });
    },
    onError: (error: unknown) =>
      notificar.error("Error", getApiErrorMessage(error)),
  });

  return {
    generar,
    generarTodos,
    previsualizarRecalculo,
    recalcularCerrado,
    vencerExcedente,
  };
}
