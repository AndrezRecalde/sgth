"use client";

import {
  Stack,
  Grid,
  Select,
  Button,
  Divider,
  TextInput,
  Alert,
  Text,
  Group,
} from "@mantine/core";
import { DateTimePicker } from "@mantine/dates";
import "@mantine/dates/styles.css";
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
import type { CatalogoTransporte, EmpresaTransporte } from "@/types/api";
import { tramoSchema, type TramoFormData } from "../schemas/viatico.schema";

interface Props {
  viaticoId: number;
  viatico?: import("@/types/api").Viatico | null;
  onSuccess: () => void;
  onCancel: () => void;
}

const fromDateTime = (d: Date | null | string): string => {
  if (!d) return "";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (isNaN(dt.getTime())) return "";

  const year = dt.getFullYear();
  const month = String(dt.getMonth() + 1).padStart(2, "0");
  const day = String(dt.getDate()).padStart(2, "0");
  const hours = String(dt.getHours()).padStart(2, "0");
  const minutes = String(dt.getMinutes()).padStart(2, "0");

  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const PAISES_COMUNES = [
  "Colombia",
  "Perú",
  "Bolivia",
  "Chile",
  "Argentina",
  "Brasil",
  "Venezuela",
  "México",
  "España",
  "Estados Unidos",
  "Canadá",
  "Francia",
  "Alemania",
  "Italia",
  "China",
  "Japón",
  "Otro",
];

export function TramoForm({ viaticoId, viatico, onSuccess, onCancel }: Props) {
  const contained = useContainedInput();
  const qc = useQueryClient();

  const { data: tipos = [] } = useTiposTransporte();
  const { data: provincias = [] } = useProvincias();

  const {
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<TramoFormData>({
    resolver: zodResolver(tramoSchema),
    defaultValues: {
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

  const { data: empresas = [] } = useEmpresasPorTipo(catalogoSelId || null);

  const { data: cantonesOrigen = [] } = useCantones(origenProvId ?? null);

  const { data: cantonesDestino = [] } = useCantones(destinoProvId ?? null);

  const tipoOptions = (tipos as CatalogoTransporte[]).map((t) => ({
    value: String(t.id),
    label: t.nombre,
  }));

  const empresaOptions = (empresas as EmpresaTransporte[]).map((e) => ({
    value: String(e.id),
    label: e.nombre,
  }));

  const provinciaOptions = (
    provincias as {
      id: number;
      nombre: string;
    }[]
  ).map((p) => ({
    value: String(p.id),
    label: p.nombre,
  }));

  const cantonOrigenOptions = (
    cantonesOrigen as {
      id: number;
      nombre: string;
    }[]
  ).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));

  const cantonDestinoOptions = (
    cantonesDestino as {
      id: number;
      nombre: string;
    }[]
  ).map((c) => ({
    value: String(c.id),
    label: c.nombre,
  }));

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
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { catalogo_transporte_id, ...rest } = values;
    crear.mutate(rest);
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Stack gap="sm">
        {/* ── ORIGEN ── */}
        <Divider label="Origen" labelPosition="left" />

        <Controller
          name="origen_tipo"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de origen"
              data={[
                { value: "nacional", label: "Nacional" },
                { value: "internacional", label: "Internacional" },
              ]}
              {...contained}
              value={field.value}
              onChange={(v) => {
                field.onChange(v ?? "nacional");
                setValue("origen_provincia_id", null);
                setValue("origen_canton_id", null);
                setValue("origen_pais", null);
                setValue("origen_ciudad", "");
              }}
            />
          )}
        />

        {origenTipo === "nacional" ? (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="origen_provincia_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Provincia de origen"
                    placeholder="Seleccionar"
                    data={provinciaOptions}
                    searchable
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : null);
                      setValue("origen_canton_id", null);
                      setValue("origen_ciudad", "");
                    }}
                    error={errors.origen_provincia_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="origen_canton_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Cantón de origen"
                    placeholder={
                      !origenProvId ? "Seleccione provincia" : "Seleccionar"
                    }
                    data={cantonOrigenOptions}
                    searchable
                    disabled={!origenProvId}
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : null);
                      // Poblar ciudad con nombre del cantón
                      const canton = (
                        cantonesOrigen as {
                          id: number;
                          nombre: string;
                        }[]
                      ).find((c) => String(c.id) === v);
                      if (canton) {
                        setValue("origen_ciudad", canton.nombre);
                      }
                    }}
                    error={errors.origen_canton_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="origen_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad / Lugar"
                    placeholder="Ej: Esmeraldas"
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.origen_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        ) : (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="origen_pais"
                control={control}
                render={({ field }) => (
                  <Select
                    label="País de origen"
                    data={PAISES_COMUNES}
                    searchable
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => {
                      field.onChange(v ?? null);
                      setValue("origen_ciudad", "");
                    }}
                    error={errors.origen_pais?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="origen_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad de origen"
                    placeholder="Ej: Bogotá"
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.origen_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        )}

        {/* ── TRANSPORTE ── */}
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
                  label="Empresa / Aerolínea"
                  data={empresaOptions}
                  searchable
                  disabled={!catalogoSelId}
                  placeholder={
                    !catalogoSelId
                      ? "Seleccione primero el tipo"
                      : "Seleccionar empresa"
                  }
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : 0)}
                  error={errors.empresa_transporte_id?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        {/* ── DESTINO ── */}
        <Divider label="Destino" labelPosition="left" />

        <Controller
          name="destino_tipo"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de destino"
              data={[
                { value: "nacional", label: "Nacional" },
                { value: "internacional", label: "Internacional" },
              ]}
              {...contained}
              value={field.value}
              onChange={(v) => {
                field.onChange(v ?? "nacional");
                setValue("destino_provincia_id", null);
                setValue("destino_canton_id", null);
                setValue("destino_pais", null);
                setValue("destino_ciudad", "");
              }}
            />
          )}
        />

        {destinoTipo === "nacional" ? (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="destino_provincia_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Provincia de destino"
                    placeholder="Seleccionar"
                    data={provinciaOptions}
                    searchable
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : null);
                      setValue("destino_canton_id", null);
                      setValue("destino_ciudad", "");
                    }}
                    error={errors.destino_provincia_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="destino_canton_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Cantón de destino"
                    placeholder={
                      !destinoProvId ? "Seleccione provincia" : "Seleccionar"
                    }
                    data={cantonDestinoOptions}
                    searchable
                    disabled={!destinoProvId}
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => {
                      field.onChange(v ? Number(v) : null);
                      const canton = (
                        cantonesDestino as {
                          id: number;
                          nombre: string;
                        }[]
                      ).find((c) => String(c.id) === v);
                      if (canton) {
                        setValue("destino_ciudad", canton.nombre);
                      }
                    }}
                    error={errors.destino_canton_id?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>
              <Controller
                name="destino_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad / Lugar exacto"
                    placeholder="Ej: Quito"
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.destino_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        ) : (
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="destino_pais"
                control={control}
                render={({ field }) => (
                  <Select
                    label="País de destino"
                    data={PAISES_COMUNES}
                    searchable
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => {
                      field.onChange(v ?? null);
                      setValue("destino_ciudad", "");
                    }}
                    error={errors.destino_pais?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="destino_ciudad"
                control={control}
                render={({ field }) => (
                  <TextInput
                    label="Ciudad de destino"
                    placeholder="Ej: Bogotá"
                    {...contained}
                    value={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.destino_ciudad?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        )}

        {/* ── FECHAS ── */}
        <Divider label="Fechas y horas" labelPosition="left" />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="datetime_salida"
              control={control}
              render={({ field }) => (
                <DateTimePicker
                  label="Fecha y hora de salida"
                  placeholder="Seleccionar"
                  valueFormat="DD/MM/YYYY HH:mm"
                  timePickerProps={{
                    withDropdown: true,
                    popoverProps: { withinPortal: false },
                    format: "24h",
                  }}
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
                  placeholder="Seleccionar"
                  valueFormat="DD/MM/YYYY HH:mm"
                  timePickerProps={{
                    withDropdown: true,
                    popoverProps: { withinPortal: false },
                    format: "24h",
                  }}
                  {...contained}
                  value={field.value ? new Date(field.value) : null}
                  onChange={(v) => field.onChange(fromDateTime(v))}
                  error={errors.datetime_llegada?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        {viatico && (
          <Stack gap="xs">
            {alertaSalida === "ok" && (
              <Alert color="emerald" variant="light" p="xs">
                <Text size="xs">
                  ✅ La salida del tramo coincide con el inicio del viático
                </Text>
              </Alert>
            )}
            {alertaSalida === "error" && (
              <Alert color="orange" variant="light" p="xs">
                <Text size="xs" fw={500}>
                  ⚠️ El primer tramo debe salir exactamente el{" "}
                  <strong>
                    {new Date(viatico.datetime_salida as string).toLocaleString(
                      "es-EC",
                      {
                        timeZone: "UTC",
                        day: "2-digit",
                        month: "2-digit",
                        year: "numeric",
                        hour: "2-digit",
                        minute: "2-digit",
                      },
                    )}
                  </strong>
                </Text>
              </Alert>
            )}
            {alertaLlegada === "error" && (
              <Alert color="red" variant="light" p="xs">
                <Text size="xs" fw={500}>
                  🚫 La llegada no puede superar la fecha de regreso del
                  viático:{" "}
                  <strong>
                    {new Date(
                      viatico.datetime_llegada as string,
                    ).toLocaleString("es-EC", {
                      timeZone: "UTC",
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
