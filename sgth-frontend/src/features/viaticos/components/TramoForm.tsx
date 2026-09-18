"use client";

import { Stack, Text } from "@mantine/core";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notificar } from "@/components/ui";
import { useProvincias } from "@/features/expediente/hooks/useProvincias";
import { useCantones } from "@/features/expediente/hooks/useCantones";
import { viaticoService } from "../services/viaticoService";
import { PAISES_OPTIONS } from "../constants/viatico.constants";
import { tramoSchema, type TramoFormData } from "../schemas/viatico.schema";
import { TramoLugarSelect } from "./TramoLugarSelect";
import { TramoTipoSelector } from "./TramoTipoSelector";
import { TramoTransporteCampos } from "./TramoTransporteCampos";
import type { Viatico } from "@/types/api";

/** El `id` del formulario: el botón de enviar vive en el pie del modal. */
export const TRAMO_FORM_ID = "tramo-form";
/** Para que el pie del modal sepa que se está guardando. */
export const CREAR_TRAMO = ["crear-tramo"];

interface Props {
  viaticoId: number;
  viatico?: Viatico | null;
  tramosExistentes?: number;
  onSuccess: () => void;
}

type Opcion = { id: number; nombre?: string | null };
const opciones = (lista: Opcion[]) =>
  lista.map((o) => ({ value: String(o.id), label: o.nombre ?? "" }));

const VACIO: TramoFormData = {
  tipo_tramo: null,
  origen_tipo: "nacional",
  origen_provincia_id: null,
  origen_canton_id: null,
  origen_pais: null,
  origen_ciudad: "",
  destino_tipo: "nacional",
  destino_provincia_id: null,
  destino_canton_id: null,
  destino_pais: null,
  destino_ciudad: "",
  catalogo_transporte_id: 0,
  empresa_transporte_id: 0,
  datetime_salida: "",
  datetime_llegada: "",
};

/*
| Un tramo nuevo del itinerario: de dónde a dónde, en qué y cuándo.
|
| Sin botones propios: los pone el pie del modal, por el `id` del formulario.
| Antes el formulario traía su Cancelar y su Agregar debajo de los campos, y
| con un error de validación quedaban fuera de la vista.
*/
export function TramoForm({ viaticoId, viatico, tramosExistentes, onSuccess }: Props) {
  const qc = useQueryClient();

  const { control, handleSubmit, setValue, setError, formState: { errors } } =
    useForm<TramoFormData>({ resolver: zodResolver(tramoSchema), defaultValues: VACIO });

  const [origenTipo, destinoTipo, origenProv, destinoProv, tipoTramo] = useWatch({
    control,
    name: ["origen_tipo", "destino_tipo", "origen_provincia_id", "destino_provincia_id", "tipo_tramo"],
  });

  const esPrimerTramo = (tramosExistentes ?? 0) === 0;
  const tipoEfectivo = esPrimerTramo ? "ida" : tipoTramo;

  const { data: provincias = [] } = useProvincias();
  const { data: cantonesOrigen = [] } = useCantones(origenProv ?? null);
  const { data: cantonesDestino = [] } = useCantones(destinoProv ?? null);

  const provinciaOptions = opciones(provincias as Opcion[]);

  const crear = useMutation({
    mutationKey: CREAR_TRAMO,
    mutationFn: (data: Parameters<typeof viaticoService.tramos.crear>[1]) =>
      viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notificar.exito("Tramo agregado", "El tramo quedó en el itinerario.");
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      qc.invalidateQueries({ queryKey: ["viatico", viaticoId] });
      onSuccess();
    },
    onError: notificar.alFallar("No se pudo agregar el tramo"),
  });

  const onSubmit = (values: TramoFormData) => {
    if (!tipoEfectivo) {
      setError("tipo_tramo", { message: "Elija qué es este tramo en el viaje" });
      return;
    }
    const { catalogo_transporte_id: _catalogo, ...resto } = values;
    crear.mutate({ ...resto, tipo_tramo: tipoEfectivo });
  };

  const limpiarLugar = (prefijo: "origen" | "destino") => () => {
    setValue(`${prefijo}_provincia_id`, null);
    setValue(`${prefijo}_canton_id`, null);
    setValue(`${prefijo}_pais`, null);
    setValue(`${prefijo}_ciudad`, "");
  };

  return (
    <form id={TRAMO_FORM_ID} onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="md">
        {(["origen", "destino"] as const).map((prefijo) => (
          <Stack key={prefijo} gap="xs">
            <Text size="sm" fw={600}>{prefijo === "origen" ? "Origen" : "Destino"}</Text>
            <TramoLugarSelect
              prefijo={prefijo}
              label={prefijo === "origen" ? "Origen" : "Destino"}
              control={control}
              errors={errors}
              tipo={(prefijo === "origen" ? origenTipo : destinoTipo) as "nacional" | "internacional"}
              provinciaOptions={provinciaOptions}
              cantonOptions={opciones((prefijo === "origen" ? cantonesOrigen : cantonesDestino) as Opcion[])}
              paises={PAISES_OPTIONS}
              onTipoChange={limpiarLugar(prefijo)}
              onProvinciaChange={() => setValue(`${prefijo}_canton_id`, null)}
              setValue={setValue}
            />
          </Stack>
        ))}

        <Stack gap="xs">
          <Text size="sm" fw={600}>Transporte y horario</Text>
          <TramoTransporteCampos
            control={control}
            errors={errors}
            setValue={setValue}
            viatico={viatico}
            esPrimerTramo={esPrimerTramo}
          />
        </Stack>

        <Stack gap="xs">
          <Text size="sm" fw={600}>Qué es este tramo</Text>
          <TramoTipoSelector control={control} errors={errors} esPrimerTramo={esPrimerTramo} />
        </Stack>
      </Stack>
    </form>
  );
}
