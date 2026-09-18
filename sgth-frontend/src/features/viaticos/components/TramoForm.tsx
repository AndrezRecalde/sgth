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
import { erroresDeCampo } from "@/lib/erroresDeCampo";
import { CIUDAD_BASE, TRAMO_VACIO, desdeTramo, mismoLugar, valoresNuevoTramo } from "../utils/itinerario";
import type { TramoViatico, Viatico } from "@/types/api";

/** El `id` del formulario: el botón de enviar vive en el pie del modal. */
export const TRAMO_FORM_ID = "tramo-form";
/** Para que el pie del modal sepa que se está guardando. */
export const CREAR_TRAMO = ["crear-tramo"];

interface Props {
  viaticoId: number;
  viatico?: Viatico | null;
  /** Los tramos que ya tiene el itinerario: de ellos sale lo que se prellena. */
  tramos?: TramoViatico[];
  /** Con esto se corrige ese tramo; sin él, se agrega uno nuevo. */
  tramo?: TramoViatico | null;
  onSuccess: () => void;
}

type Opcion = { id: number; nombre?: string | null };
const opciones = (lista: Opcion[]) =>
  lista.map((o) => ({ value: String(o.id), label: o.nombre ?? "" }));


/*
| Un tramo del itinerario, nuevo o para corregirlo: de dónde a dónde, en qué
| y cuándo.
|
| Sin botones propios: los pone el pie del modal, por el `id` del formulario.
| Antes el formulario traía su Cancelar y su Agregar debajo de los campos, y
| con un error de validación quedaban fuera de la vista.
*/
export function TramoForm({ viaticoId, viatico, tramos = [], tramo, onSuccess }: Props) {
  const qc = useQueryClient();

  const { control, handleSubmit, setValue, setError, formState: { errors } } =
    useForm<TramoFormData>({
      resolver: zodResolver(tramoSchema),
      defaultValues: tramo
        ? desdeTramo(tramo)
        : { ...TRAMO_VACIO, tipo_tramo: "destino", ...valoresNuevoTramo(viatico, tramos) },
    });

  const [origenTipo, destinoTipo, origenProv, destinoProv, destinoCanton, destinoCiudad] = useWatch({
    control,
    name: [
      "origen_tipo", "destino_tipo", "origen_provincia_id", "destino_provincia_id",
      "destino_canton_id", "destino_ciudad",
    ],
  });

  // La ida y el regreso los deduce el backend con la misma regla; aquí solo
  // se anticipa para no preguntar lo que ya se sabe.
  const ordenados = [...tramos].sort((a, b) => a.orden - b.orden);
  const ida = ordenados[0];
  const esPrimerTramo = tramo ? tramo.orden === 1 : ordenados.length === 0;
  const esUltimo = !tramo || tramo.orden === ordenados.at(-1)?.orden;
  const esRegreso =
    !esPrimerTramo && esUltimo && !!ida &&
    mismoLugar({ canton_id: destinoCanton, ciudad: destinoCiudad }, { canton_id: ida.origen_canton_id, ciudad: ida.origen_ciudad });
  // Dentro o fuera de la provincia, todo el viaje es en el país.
  const soloNacional = viatico?.zona !== "exterior";

  const { data: provincias = [] } = useProvincias();
  const { data: cantonesOrigen = [] } = useCantones(origenProv ?? null);
  const { data: cantonesDestino = [] } = useCantones(destinoProv ?? null);

  const provinciaOptions = opciones(provincias as Opcion[]);

  const guardar = useMutation({
    mutationKey: CREAR_TRAMO,
    mutationFn: (data: Parameters<typeof viaticoService.tramos.crear>[1]) =>
      tramo
        ? viaticoService.tramos.actualizar(viaticoId, tramo.id, data)
        : viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notificar.exito(
        tramo ? "Tramo corregido" : "Tramo agregado",
        tramo ? "Los cambios quedaron en el itinerario." : "El tramo quedó en el itinerario.",
      );
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      // La ficha se consulta por código, no por id: sin la clave corta no se
      // refrescaba, y el aviso del itinerario seguía igual.
      qc.invalidateQueries({ queryKey: ["viatico"] });
      onSuccess();
    },
    // Las reglas del itinerario (fechas, cruces, regreso) llegan por campo.
    onError: (error) => {
      const campos = erroresDeCampo(error);
      if (!campos) {
        notificar.alFallar(tramo ? "No se pudo corregir el tramo" : "No se pudo agregar el tramo")(error);
        return;
      }
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in TRAMO_VACIO) setError(campo as keyof TramoFormData, { message: mensaje });
      }
    },
  });

  const onSubmit = (values: TramoFormData) => {
    const { con_empresas: _conEmpresas, ...resto } = values;
    // Solo se manda si realiza actividades (destino) o solo pasa (escala).
    guardar.mutate({ ...resto, tipo_tramo: values.tipo_tramo === "escala" ? "escala" : "destino" });
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
              soloNacional={soloNacional}
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
          <TramoTipoSelector
            control={control}
            errors={errors}
            esPrimerTramo={esPrimerTramo}
            esRegreso={esRegreso}
            destino={destinoCiudad}
            base={ida?.origen_ciudad ?? CIUDAD_BASE}
          />
        </Stack>
      </Stack>
    </form>
  );
}
