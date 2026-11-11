"""Extrae las fichas «Descripción y perfil del puesto» del Manual de Puestos
2018-2019 a un JSON que el SGTH carga con `php artisan sgth:manual:importar`
(docs/diseno-manual-de-puestos.md, P4).

Uso:
    python extraer_fichas.py "<carpeta del manual>" <salida.json>

Es una herramienta de desarrollo: Python no va al servidor; el JSON sí, y se
versiona en `sgth-backend/database/data/`. Necesita PyMuPDF (`pip install
pymupdf`). TH no tiene el manual en Word ni Excel (D7): se lee de los PDF.

Cómo lee la ficha. El formulario va en tres bloques de columnas:
- a la izquierda, la identificación, la misión y las actividades;
- al centro, las relaciones y los conocimientos de cada actividad;
- a la derecha, la instrucción, la experiencia, la capacitación y las
  competencias.

Cada bloque se recorre de arriba abajo, y cada encabezado o etiqueta («Nivel de
Instrucción:», «Tiempo de Experiencia:», «9. COMPETENCIAS TÉCNICAS»…) cambia el
campo que se está leyendo. Así se lee también la ficha a la que le falta un
encabezado, si conserva la etiqueta. Solo se leen las dos primeras páginas: lo
que sigue en muchas fichas es el diccionario de competencias anexado.

Lo que el extractor no resuelve lo deja escrito: el texto tal como viene, sin
interpretar niveles ni tiempos. Eso lo hace el comando, que además empareja
las competencias con el diccionario del SGTH.
"""
import json
import os
import re
import sys
import unicodedata

import pymupdf


def norm(texto):
    return re.sub(r'\s+', ' ', (texto or '').replace('\n', ' ')).strip()


def clave(texto):
    t = unicodedata.normalize('NFD', norm(texto).lower())
    return ''.join(c for c in t if unicodedata.category(c) != 'Mn').rstrip(':').strip()


# Las etiquetas de la identificación, en la primera columna.
ETIQUETAS = {
    'codigo': 'codigo', 'denominacion del puesto': 'denominacion', 'nivel': 'nivel',
    'unidad administrativa': 'unidad', 'rol': 'rol', 'grupo ocupacional': 'grupo_ocupacional',
    'grado': 'grado', 'ambito': 'ambito',
}

# Encabezados y etiquetas: cada uno dice qué campo empieza.
ANCLAS = [
    (r'^2\.? ?mision', 'mision'),
    (r'^3\.? ?relaciones', 'relaciones'),
    (r'^interfaz', 'relaciones'),
    (r'^4\.? ?instruccion', 'instruccion'),
    (r'^nivel de instruccion', 'instruccion'),
    (r'^area de conocimiento', 'area'),
    (r'^5\.? ?experiencia', 'experiencia'),
    (r'^tiempo de', 'experiencia'),
    (r'^experiencia$', 'experiencia'),
    (r'^especificidad', 'especificidad'),
    (r'^6\.? ?capacitacion', 'capacitacion'),
    (r'^tematica', 'capacitacion'),
    (r'^capacitacion$', 'capacitacion'),
    (r'^7\.? ?actividades', 'actividades'),
    (r'^8\.? ?conocimientos', 'conocimientos'),
    (r'^9\.? ?competencias', 'tecnicas'),
    (r'^10\.? ?competencias', 'conductuales'),
]

# Lo que no es dato: títulos, pies y cabeceras de columna.
RUIDO = (r'^descripcion y perfil', r'^formulario mrl', r'^1\.? ?datos de identificacion',
         r'^relacionados a las actividades', r'^esenciales$', r'^denominacion de la competencia',
         r'^comportamiento observable', r'^nivel$', r'^o$', r'^n[°º.]?$')

NIVELES_COMPETENCIA = ('alto', 'medio', 'bajo')


def ancla(celda):
    k = clave(celda)
    return next((campo for patron, campo in ANCLAS if re.match(patron, k)), None)


def es_ruido(celda):
    k = clave(celda)
    return not k or any(re.match(p, k) for p in RUIDO)


def es_tiempo(texto):
    k = clave(texto)
    return bool(re.search(r'\d', k)) or 'no requerid' in k or 'sin experiencia' in k


CONECTORES = {'de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'o', 'u', 'a', 'al', 'en', 'por', 'para', 'con',
              'que', 'su', 'sus', 'sobre', 'entre', 'como', 'un', 'una'}


def es_continuacion(anterior, texto):
    """
    Un renglón que sigue al anterior: la tabla partió la celda en dos filas.
    Empieza en minúscula; o el anterior quedó en «de», «la», «por el»…; o es
    una o dos palabras con punto final tras un texto sin cerrar («…EN AREAS» y
    «ASIGNADAS.»). «Elabora memorandos» seguido de «Elabora oficios» son dos.
    """
    if re.match(r'^[a-zà-ÿ(,;]', texto):
        return True
    palabras = anterior.split()
    if palabras and clave(palabras[-1]) in CONECTORES:
        return True
    return not re.search(r'[.;:)]$', anterior) and len(texto.split()) <= 2 and bool(re.search(r'[.)]$', texto))


def filas_de(doc):
    filas = []
    for n in range(min(2, doc.page_count)):
        for tabla in doc[n].find_tables().tables:
            filas.extend([norm(c) for c in fila] for fila in tabla.extract())
    return filas


def columna_de(filas, campos, defecto):
    for fila in filas:
        for c, celda in enumerate(fila):
            if ancla(celda) in campos:
                return c
    return defecto


def plazas_del_archivo(nombre):
    """«ANALISTA SP5 (2).pdf», «ASISTE SP3(2 PUESTOS).pdf»: la ficha cubre varias plazas."""
    m = re.search(r'\((\d+)\s*(puestos?)?\)', nombre, re.IGNORECASE)
    return int(m.group(1)) if m else 1


def extraer(ruta):
    filas = filas_de(pymupdf.open(ruta))
    avisos = []

    centro = columna_de(filas, ('relaciones',), None)
    if centro is None:
        centro = columna_de(filas, ('conocimientos',), 2)
    derecha = columna_de(filas, ('instruccion',), None)
    if derecha is None:
        derecha = columna_de(filas, ('tecnicas',), centro + 1)

    # La fila donde empiezan las actividades: la del último de sus dos encabezados.
    inicio_actividades = max(
        (r for r, fila in enumerate(filas) for celda in fila[:derecha] if ancla(celda) in ('actividades', 'conocimientos')),
        default=len(filas),
    )

    ficha = {campo: '' for campo in ETIQUETAS.values()}

    # Identificación: la etiqueta en la primera columna y el valor a su derecha.
    ultima_etiqueta = -1
    for r, fila in enumerate(filas[:inicio_actividades]):
        if fila and clave(fila[0]) in ETIQUETAS:
            valores = [x for x in fila[1:centro] if x and not es_ruido(x) and not ancla(x)]
            ficha[ETIQUETAS[clave(fila[0])]] = valores[0] if valores else ''
            ultima_etiqueta = r

    # Misión: el bloque izquierdo entre la identificación y las actividades.
    mision = []
    for fila in filas[ultima_etiqueta + 1:inicio_actividades]:
        for x in fila[:centro]:
            if x and not es_ruido(x) and not ancla(x) and clave(x) not in ETIQUETAS and x not in mision:
                mision.append(x)
    ficha['mision'] = ' '.join(mision)

    # Relaciones: el bloque del centro, antes de los conocimientos.
    relaciones = []
    for fila in filas[:inicio_actividades]:
        for x in fila[centro:derecha]:
            if x and not es_ruido(x) and not ancla(x) and x not in relaciones:
                relaciones.append(x)
    ficha['relaciones'] = ' '.join(relaciones)

    # Actividades y conocimientos, por fila. Una celda partida en dos filas
    # sigue en la siguiente; un conocimiento sin actividad en su fila es de la
    # actividad de arriba.
    actividades = []
    for fila in filas[inicio_actividades + 1:]:
        act = next((x for x in fila[:centro] if x and not es_ruido(x) and not ancla(x)), '')
        con = next((x for x in fila[centro:derecha] if x and not es_ruido(x) and not ancla(x)), '')
        if act and not (actividades and es_continuacion(actividades[-1]['descripcion'], act)):
            actividades.append({'descripcion': act, 'conocimientos': con})
        elif actividades and (act or con):
            ultima = actividades[-1]
            if act:
                ultima['descripcion'] += ' ' + act
            if con:
                ultima['conocimientos'] = (ultima['conocimientos'] + ' ' + con).strip()
    ficha['actividades'] = actividades

    # El bloque derecho, de arriba abajo: cada ancla cambia el campo.
    campo = None
    valores = {c: [] for c in ('instruccion', 'area', 'experiencia', 'especificidad', 'capacitacion')}
    competencias = {'tecnicas': [], 'conductuales': []}
    def guardar(campo, resto):
        if not resto or campo is None:
            return
        if campo in competencias:
            lista = competencias[campo]
            nivel = next((x for x in resto if clave(x) in NIVELES_COMPETENCIA), '')
            numero = next((int(x) for x in reversed(resto) if re.fullmatch(r'\d{1,3}', x)), None)
            textos = [x for x in resto if x != nivel and not re.fullmatch(r'\d{1,3}', x)]
            if not nivel and numero is None and len(textos) == 1 and lista:
                # El comportamiento siguió en otra fila.
                lista[-1]['comportamiento'] = (lista[-1]['comportamiento'] + ' ' + textos[0]).strip()
            elif textos:
                lista.append({
                    'nombre': textos[0], 'nivel': nivel, 'numero': numero,
                    'comportamiento': ' '.join(textos[1:]),
                })
        elif campo in valores:
            valores[campo].extend(resto)

    for fila in filas:
        resto = []
        for x in fila[derecha:]:
            nuevo = ancla(x)
            if nuevo:
                # Lo que la fila traía antes de la etiqueta es del campo anterior.
                guardar(campo, resto)
                campo, resto = nuevo, []
            elif x and not es_ruido(x):
                resto.append(x)
        guardar(campo, resto)

    ficha['area_conocimiento'] = ' '.join(valores['area'])
    ficha['especificidad'] = ' '.join(valores['especificidad'])
    ficha['capacitacion'] = ' '.join(valores['capacitacion'])
    ficha['tecnicas'] = competencias['tecnicas']
    ficha['conductuales'] = competencias['conductuales']

    # Instrucción y experiencia: cada alternativa con su tiempo. La experiencia
    # repite los niveles como cabecera («Técnico Superior | Tecnológico | Tercer
    # Nivel» y debajo «5 | 4 | 2»): se quedan solo los tiempos, en orden.
    alternativas = valores['instruccion']
    tiempos = [x for x in valores['experiencia'] if es_tiempo(x)]
    if not alternativas:
        alternativas = [x for x in valores['experiencia'] if not es_tiempo(x)]
    if len(tiempos) == len(alternativas):
        pares = list(zip(alternativas, tiempos))
    elif len(tiempos) == 1:
        pares = [(a, tiempos[0]) for a in alternativas]
        if len(alternativas) > 1:
            avisos.append(f'Un solo tiempo de experiencia para {len(alternativas)} alternativas de instrucción: se aplicó a todas.')
    else:
        pares = [(a, tiempos[i] if i < len(tiempos) else None) for i, a in enumerate(alternativas)]
        if tiempos:
            avisos.append(f'{len(alternativas)} alternativas de instrucción y {len(tiempos)} tiempos de experiencia: '
                          'se emparejaron en orden.')
    ficha['instruccion'] = [{'nivel': a, 'experiencia': t} for a, t in pares]
    ficha['avisos'] = avisos
    return ficha


def main(raiz, salida):
    fichas = []
    for carpeta, _, archivos in os.walk(raiz):
        for nombre in sorted(archivos):
            if not nombre.lower().endswith('.pdf') or nombre.upper().startswith('DISTRIBUCION'):
                continue
            ruta = os.path.join(carpeta, nombre)
            relativa = os.path.relpath(ruta, raiz).replace('\\', '/')
            partes = relativa.split('/')
            ficha = {'archivo': relativa, 'proceso': partes[0], 'carpeta': '/'.join(partes[1:-1]),
                     'plazas': plazas_del_archivo(nombre)}
            try:
                ficha.update(extraer(ruta))
            except Exception as error:  # noqa: BLE001 — una ficha ilegible no detiene las demás
                ficha['error'] = repr(error)
            fichas.append(ficha)

    fichas.sort(key=lambda f: f['archivo'])
    with open(salida, 'w', encoding='utf-8', newline='\n') as s:
        json.dump(fichas, s, ensure_ascii=False, indent=1)
        s.write('\n')
    print(len(fichas), 'fichas;', sum(1 for f in fichas if 'error' in f), 'con error')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
