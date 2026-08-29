"use client";

import {
  Stack,
  Grid,
  Select,
  Button,
  Divider,
  Alert,
  Text,
  Group,
} from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import React from "react";
import { IconCheck, IconX } from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useTiposTransporte, useEmpresasPorTipo } from "../hooks/useViaticos";
import { useProvincias } from "@/features/expediente/hooks/useProvincias";
import { useCantones } from "@/features/expediente/hooks/useCantones";
import { viaticoService } from "../services/viaticoService";
import { getApiErrorMessage } from "@/types/api";
import { TramoLugarSelect } from "./TramoLugarSelect";
import { TramoTipoSelector } from "./TramoTipoSelector";
import type {
  CatalogoTransporte,
  EmpresaTransporte,
  Viatico,
} from "@/types/api";
import { tramoSchema, type TramoFormData } from "../schemas/viatico.schema";

const PAISES_COMUNES = [
  "Colombia",
  "Perú",
  "Chile",
  "Estados Unidos",
  "España",
  "Brasil",
  "México",
  "Panamá",
  "Argentina",
  "Bolivia",
  "Costa Rica",
  "Ecuador",
  "Uruguay",
  "Paraguay",
  "Venezuela",
  "Francia",
  "Suiza",
  "Alemania",
  "Italia",
  "Bélgica",
  "Reino Unido",
  "Canadá",
  "China",
  "Japón",
  "Corea del Sur",
  "República Dominicana",
  "Guatemala",
  "El Salvador",
  "Honduras",
  "Nicaragua",
  "Cuba",
  "Países Bajos",
  "Austria",
  "Israel",
  "India",
  "Australia",
  "Otro",
].map((p) => ({ value: p, label: p }));

function fromDateTime(d: Date | null | string): string {
  if (!d) return "";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (isNaN(dt.getTime())) return "";
  return (
    [
      dt.getFullYear(),
      String(dt.getMonth() + 1).padStart(2, "0"),
      String(dt.getDate()).padStart(2, "0"),
    ].join("-") +
    "T" +
    [
      String(dt.getHours()).padStart(2, "0"),
      String(dt.getMinutes()).padStart(2, "0"),
    ].join(":")
  );
}

interface Props {
  viaticoId: number;
  viatico?: Viatico | null;
  tramosExistentes?: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function TramoForm({
  viaticoId,
  viatico,
  tramosExistentes,
  onSuccess,
  onCancel,
}: Props) {
  const contained = useContainedInput();
  const qc = useQueryClient();

  const { data: tipos = [] } = useTiposTransporte();
  const { data: provincias = [] } = useProvincias();

  const {
    control,
    handleSubmit,
    setValue,
    setError,
    formState: { errors },
  } = useForm<TramoFormData>({
    resolver: zodResolver(tramoSchema),
    defaultValues: {
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
    },
  });

  const origenTipo = useWatch({ control, name: "origen_tipo" });
  const destinoTipo = useWatch({ control, name: "destino_tipo" });
  const catalogoSelId = useWatch({ control, name: "catalogo_transporte_id" });
  const origenProvId = useWatch({ control, name: "origen_provincia_id" });
  const destinoProvId = useWatch({ control, name: "destino_provincia_id" });
  const salidaTramo = useWatch({ control, name: "datetime_salida" });
  const llegadaTramo = useWatch({ control, name: "datetime_llegada" });
  const tipoTramo = useWatch({ control, name: "tipo_tramo" });

  const esPrimerTramo = (tramosExistentes ?? 0) === 0;
  const tipoTramoEfectivo = esPrimerTramo ? "ida" : tipoTramo;

  const { data: empresas = [] } = useEmpresasPorTipo(catalogoSelId || null);
  const { data: cantonesOrigen = [] } = useCantones(origenProvId ?? null);
  const { data: cantonesDestino = [] } = useCantones(destinoProvId ?? null);

  type Prov = { id: number; nombre: string };
  type Cant = { id: number; nombre: string };

  const provinciaOptions = (provincias as Prov[]).map((p) => ({
    value: String(p.id),
    label: p.nombre,
  }));
  const cantonOrigenOptions = (cantonesOrigen as Cant[]).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));
  const cantonDestinoOptions = (cantonesDestino as Cant[]).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));
  const tipoOptions = (tipos as CatalogoTransporte[]).map((t) => ({
    value: String(t.id),
    label: t.nombre ?? "",
  }));
  const empresaOptions = (empresas as EmpresaTransporte[]).map((e) => ({
    value: String(e.id),
    label: e.nombre ?? "",
  }));

  const alertaSalida: "ok" | "error" | null =
    viatico && salidaTramo
      ? (() => {
          const sv = new Date(viatico.datetime_salida as string);
          const st = new Date(salidaTramo);
          if (isNaN(sv.getTime()) || isNaN(st.getTime())) return null;
          return sv.getTime() === st.getTime() ? "ok" : "error";
        })()
      : null;

  const alertaLlegada: "ok" | "error" | null =
    viatico && llegadaTramo
      ? (() => {
          const lv = new Date(viatico.datetime_llegada as string);
          const lt = new Date(llegadaTramo);
          if (isNaN(lv.getTime()) || isNaN(lt.getTime())) return null;
          return lt.getTime() > lv.getTime() ? "error" : "ok";
        })()
      : null;

  const crear = useMutation({
    mutationFn: (data: Parameters<typeof viaticoService.tramos.crear>[1]) =>
      viaticoService.tramos.crear(viaticoId, data),
    onSuccess: () => {
      notifications.show({
        title: "Tramo agregado",
        message: "El tramo fue registrado al itinerario.",
        color: "emerald",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      qc.invalidateQueries({ queryKey: ["tramos", viaticoId] });
      qc.invalidateQueries({ queryKey: ["viatico", viaticoId] });
      onSuccess();
    },
    onError: (error: unknown) =>
      notifications.show({
        title: "Error",
        message: getApiErrorMessage(error),
        color: "red",
        icon: React.createElement(IconX, { size: 16 }),
      }),
  });

  const onSubmit = (values: TramoFormData) => {
    if (!esPrimerTramo && !tipoTramoEfectivo) {
      setError("tipo_tramo", {
        message: "Debes seleccionar el tipo de tramo",
      });
      return;
    }
    const { catalogo_transporte_id: _cat, ...rest } = values;
    crear.mutate({ ...rest, tipo_tramo: tipoTramoEfectivo ?? "destino" });
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="sm">
        {/* Origen */}
        <Divider label="Origen" labelPosition="left" />
        <TramoLugarSelect
          prefijo="origen"
          label="Origen"
          control={control}
          errors={errors}
          tipo={origenTipo as "nacional" | "internacional"}
          provinciaOptions={provinciaOptions}
          cantonOptions={cantonOrigenOptions}
          paises={PAISES_COMUNES}
          onTipoChange={() => {
            setValue("origen_provincia_id", null);
            setValue("origen_canton_id", null);
            setValue("origen_pais", null);
            setValue("origen_ciudad", "");
          }}
          onProvinciaChange={() => setValue("origen_canton_id", null)}
          setValue={setValue}
        />

        {/* Destino */}
        <Divider label="Destino" labelPosition="left" />
        <TramoLugarSelect
          prefijo="destino"
          label="Destino"
          control={control}
          errors={errors}
          tipo={destinoTipo as "nacional" | "internacional"}
          provinciaOptions={provinciaOptions}
          cantonOptions={cantonDestinoOptions}
          paises={PAISES_COMUNES}
          onTipoChange={() => {
            setValue("destino_provincia_id", null);
            setValue("destino_canton_id", null);
            setValue("destino_pais", null);
            setValue("destino_ciudad", "");
          }}
          onProvinciaChange={() => setValue("destino_canton_id", null)}
          setValue={setValue}
        />

        {/* Transporte */}
        <Divider label="Transporte" labelPosition="left" />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="catalogo_transporte_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de transporte"
                  data={tipoOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => {
                    field.onChange(v ? Number(v) : 0);
                    setValue("empresa_transporte_id", 0);
                  }}
                  error={errors.catalogo_transporte_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="empresa_transporte_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Empresa de transporte"
                  data={empresaOptions}
                  searchable
                  disabled={empresaOptions.length === 0}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : 0)}
                  error={errors.empresa_transporte_id?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        {/* Fechas */}
        <Divider label="Fechas" labelPosition="left" />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="datetime_salida"
              control={control}
              render={({ field }) => (
                <DateTimePicker
                  label="Fecha y hora de salida"
                  valueFormat="DD/MM/YYYY HH:mm"
                  {...contained}
                  value={field.value ? new Date(field.value) : null}
                  onChange={(v) => field.onChange(fromDateTime(v))}
                  error={errors.datetime_salida?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="datetime_llegada"
              control={control}
              render={({ field }) => (
                <DateTimePicker
                  label="Fecha y hora de llegada"
                  valueFormat="DD/MM/YYYY HH:mm"
                  {...contained}
                  value={field.value ? new Date(field.value) : null}
                  onChange={(v) => field.onChange(fromDateTime(v))}
                  error={errors.datetime_llegada?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        {/* Alertas de fechas */}
        {viatico && (
          <Stack gap="xs">
            {alertaSalida === "ok" && (
              <Alert color="emerald" variant="light" p="xs">
                <Text size="xs">
                  La salida del tramo coincide con el inicio del viático
                </Text>
              </Alert>
            )}
            {alertaSalida === "error" && (
              <Alert color="orange" variant="light" p="xs">
                <Text size="xs" fw={500}>
                  El primer tramo debe salir exactamente el{" "}
                  <strong>
                    {new Date(
                      (viatico.datetime_salida as string).replace(/-/g, "/"),
                    ).toLocaleString("es-EC", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                  </strong>
                </Text>
              </Alert>
            )}
            {alertaLlegada === "error" && (
              <Alert color="red" variant="light" p="xs">
                <Text size="xs" fw={500}>
                  La llegada no puede superar la fecha de regreso del viático:{" "}
                  <strong>
                    {new Date(
                      (viatico.datetime_llegada as string).replace(/-/g, "/"),
                    ).toLocaleString("es-EC", {
                      day: "2-digit",
                      month: "2-digit",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                    })}
                  </strong>
                </Text>
              </Alert>
            )}
          </Stack>
        )}

        {/* Tipo de tramo */}
        <Divider label="Tipo de tramo" labelPosition="left" />
        <TramoTipoSelector
          control={control}
          errors={errors}
          esPrimerTramo={esPrimerTramo}
          tipoTramo={tipoTramo}
        />

        {/* Botones */}
        <Group justify="flex-end" mt="sm">
          <Button variant="default" size="sm" onClick={onCancel}>
            Cancelar
          </Button>
          <Button
            type="submit"
            size="sm"
            color="emerald"
            variant="light"
            loading={crear.isPending}
          >
            Agregar tramo
          </Button>
        </Group>
      </Stack>
    </form>
  );
}
