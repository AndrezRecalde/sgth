"use client";

import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { viaticoSchema, type ViaticoFormData } from "../schemas/viatico.schema";
import { ViaticoDatosCampos } from "./ViaticoDatosCampos";
import type { Viatico } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  viatico: Viatico;
  onSuccess?: () => void;
}

/**
 * Corregir los datos de un viático, con los mismos campos que la solicitud.
 * Al cambiar las fechas, el backend rehace las noches y el monto.
 */
export function ViaticoEditModal({ opened, onClose, viatico, onSuccess }: Props) {
  const { actualizar } = useViaticoMutations();

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: {
      zona: (viatico.zona as ViaticoFormData["zona"]) ?? "fuera_provincia",
      datetime_salida: (viatico.datetime_salida as string | null) ?? "",
      datetime_llegada: (viatico.datetime_llegada as string | null) ?? "",
      justificacion: (viatico.justificacion as string | null) ?? "",
      modalidad_anticipo:
        (viatico.modalidad_anticipo as ViaticoFormData["modalidad_anticipo"]) ?? "total",
      monto_calculado: null,
      tipo_viaje: (viatico.tipo_viaje as string | null) ?? null,
      pais_destino: (viatico.pais_destino as string | null) ?? null,
    },
  });

  const onSubmit = (values: ViaticoFormData) => {
    const exterior = values.zona === "exterior";
    actualizar.mutate(
      {
        id: viatico.id,
        data: {
          zona: values.zona,
          datetime_salida: values.datetime_salida,
          datetime_llegada: values.datetime_llegada,
          justificacion: values.justificacion,
          modalidad_anticipo: values.modalidad_anticipo,
          // Fuera del exterior no hay país ni motivo que guardar.
          tipo_viaje: exterior ? values.tipo_viaje ?? null : null,
          pais_destino: exterior ? values.pais_destino ?? null : null,
        },
      },
      {
        onSuccess: () => {
          onSuccess?.();
          onClose();
        },
      },
    );
  };

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Editar datos del viaje"
      size="lg"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar cambios"
      submitting={actualizar.isPending}
    >
      <ViaticoDatosCampos control={control} errors={errors} />
    </FormModal>
  );
}
