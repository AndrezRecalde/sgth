"use client";

import { useState } from "react";
import { Combobox, TextInput, useCombobox, Text, Group } from "@mantine/core";
import { useDebouncedValue } from "@mantine/hooks";
import { IconCubePlus } from "@tabler/icons-react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useQuery } from "@tanstack/react-query";
import { inventarioMedicinaService } from "../services/inventarioMedicinaService";

// El término escrito entraba directo en la clave de consulta, así que cada
// tecla a partir de la segunda pedía una lista de medicinas y solo importaba
// la última.
const RETARDO_BUSQUEDA_MS = 300;

interface Props {
  onSeleccionar: (id: number, nombre: string) => void;
  onCrearNueva: () => void;
}

export function BuscarMedicinaSelect({ onSeleccionar, onCrearNueva }: Props) {
  const contained = useContainedInput();
  const [termino, setTermino] = useState("");
  const combobox = useCombobox({
    onDropdownClose: () => combobox.resetSelectedOption(),
  });

  const [terminoConRetardo] = useDebouncedValue(termino, RETARDO_BUSQUEDA_MS);
  // Bajar de dos caracteres corta la búsqueda al instante: el retardo aplaza
  // las peticiones, no el vaciado de la lista.
  const consulta = termino.length < 2 ? "" : terminoConRetardo;

  const { data: resultados = [], isFetching } = useQuery({
    queryKey: ["medicinas-buscar", consulta],
    queryFn: () => inventarioMedicinaService.buscar(consulta),
    enabled: consulta.length >= 2,
    staleTime: 1000 * 30,
  });

  // El desplegable decía «Sin resultados» mientras la petición iba en vuelo, y
  // ahora también durante el retardo. Con esto dice «Buscando...» en los dos
  // tramos, en vez de afirmar que no hay nada a media palabra.
  const buscando =
    isFetching || (termino.length >= 2 && consulta !== termino);

  return (
    <Combobox
      store={combobox}
      onOptionSubmit={(id) => {
        if (id === "__nueva__") {
          onCrearNueva();
          setTermino("");
          combobox.closeDropdown();
          return;
        }
        const seleccionada = resultados.find((m) => String(m.id) === id);
        if (seleccionada) {
          onSeleccionar(seleccionada.id, seleccionada.nombre);
        }
        setTermino("");
        combobox.closeDropdown();
      }}
    >
      <Combobox.Target>
        <TextInput
          label="Filtrar Medicina"
          placeholder="Buscar medicina por nombre o código"
          size="sm"
          {...contained}
          value={termino}
          onChange={(e) => {
            setTermino(e.currentTarget.value);
            combobox.openDropdown();
          }}
          onFocus={() => combobox.openDropdown()}
        />
      </Combobox.Target>

      <Combobox.Dropdown>
        <Combobox.Options mah={220} style={{ overflowY: "auto" }}>
          {termino.length < 2 ? (
            <Combobox.Empty>Escribe al menos 2 caracteres</Combobox.Empty>
          ) : buscando ? (
            <Combobox.Empty>Buscando...</Combobox.Empty>
          ) : resultados.length === 0 ? (
            <Combobox.Empty>Sin resultados</Combobox.Empty>
          ) : (
            resultados.map((m) => (
              <Combobox.Option value={String(m.id)} key={m.id}>
                <Group gap={6} justify="space-between">
                  <Text size="sm">
                    {m.nombre}
                    {m.concentracion && (
                      <Text span c="dimmed">
                        {" "}
                        — {m.concentracion}
                      </Text>
                    )}
                  </Text>
                  <Text size="xs" c="dimmed">
                    Stock: {m.stock_actual}
                  </Text>
                </Group>
              </Combobox.Option>
            ))
          )}

          <Combobox.Option value="__nueva__">
            <Group gap={6} c="emerald">
              <IconCubePlus size={13} />
              <Text size="sm" fw={500}>
                Crear medicina nueva
              </Text>
            </Group>
          </Combobox.Option>
        </Combobox.Options>
      </Combobox.Dropdown>
    </Combobox>
  );
}
