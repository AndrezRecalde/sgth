"use client";

import { Stack } from "@mantine/core";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { FormModal } from "@/components/ui";
import { useAuth } from "@/hooks/useAuth";
import { useViaticoMutations } from "../hooks/useViaticoMutations";
import { viaticoSchema, type ViaticoFormData } from "../schemas/viatico.schema";
import { ViaticoDatosCampos } from "./ViaticoDatosCampos";
import { ViaticoServidorCard } from "./ViaticoServidorCard";
import type { Viatico } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  onCreated: (viatico: Viatico) => void;
}

const VACIO: ViaticoFormData = {
  zona: "fuera_provincia",
  datetime_salida: "",
  datetime_llegada: "",
  tipo_viaje: null,
  pais_destino: null,
  justificacion: "",
  modalidad_anticipo: "total",
  monto_calculado: null,
};

/** La solicitud de un viático: quién la pide y los datos del viaje. */
export function ViaticoModal({ opened, onClose, onCreated }: Props) {
  const { solicitar } = useViaticoMutations();

  // El usuario con sesión ya trae el cargo y la unidad (`auth/perfil`).
  const { usuario } = useAuth();
  const servidor = usuario?.servidor;

  const {
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: VACIO,
  });

  const cerrar = () => {
    reset(VACIO);
    onClose();
  };

  const onSubmit = (values: ViaticoFormData) =>
    solicitar.mutate(values, {
      onSuccess: (viatico) => {
        reset(VACIO);
        onClose();
        if (viatico) onCreated(viatico);
      },
    });

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Nueva solicitud de viático"
      size="lg"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Enviar solicitud"
      submitting={solicitar.isPending}
    >
      <Stack gap="md">
        {servidor && (
          <ViaticoServidorCard
            nombre={
              [servidor.nombre, servidor.apellido].filter(Boolean).join(" ") ||
              (usuario?.nombre_completo ?? "")
            }
            cargo={servidor.puesto?.nombre}
            unidad={servidor.unidad_administrativa?.nombre}
          />
        )}
        <ViaticoDatosCampos control={control} errors={errors} />
      </Stack>
    </FormModal>
  );
}
