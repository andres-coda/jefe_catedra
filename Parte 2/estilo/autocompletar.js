/**
 * Autocompletar + chips + filas de horario (formularios-autocomplete, WU3 — D5).
 *
 * Primer JavaScript del proyecto: cero dependencias, sin build step. Mejora los
 * formularios que ya funcionan sin JS; si este script no carga, el navegador no
 * soporta fetch o el fetch falla, el formulario sigue siendo submittable y el
 * servidor decide con los nombres tipeados (REQ-05/S5). El JS nunca rompe el
 * flujo degradado.
 *
 * Contrato (D5):
 *   - input[data-autocompletar="{recurso}"] → consulta GET /buscador/{recurso}
 *     y pinta el dropdown (300ms debounce, AbortController, máx. 10 filas).
 *   - Modo simple: al elegir llena el input visible {recurso}_nombre + el
 *     hidden UUID id_{recurso} (REQ-02). Editar el texto después de elegir
 *     invalida el hidden (D7: el servidor re-valida todo UUID oculto).
 *   - data-multiple: chips removibles; cada chip lleva pares ocultos
 *     {recurso}_ids[] + {recurso}_nombres[] (los controllers de WU2a/WU2b los
 *     emparejan por índice, paresChips). No re-propone lo ya elegido.
 *   - data-area-input="#id_area": acota materia al área elegida (REQ-22/S2);
 *     el área es el hidden id_{recurso} de otro autocompletar (UUID).
 *   - Teclado: ArrowDown/ArrowUp mueven, Enter elige, Escape cierra y
 *     conserva el valor (REQ-02/S2); el click también elige.
 *   - button[data-agregar-fila]: clona la última .fila-horario (la fila vacía
 *     de más se descarta en el servidor, leerFilas).
 */
(function () {
  'use strict';

  var DEBOUNCE_MS = 300;
  var MAX_FILAS = 10;
  var UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  // La URL base del buscador la declara el <script> en cabecera.phtml
  // (Http::url('buscador')); fallback root-relative si falta el atributo.
  var scriptTag = document.querySelector('script[data-buscador-url]');
  var baseUrl = scriptTag ? scriptTag.getAttribute('data-buscador-url') : '/buscador';

  function iniciar() {
    if (typeof fetch !== 'function') {
      return; // Sin fetch: degradación total, el server decide (REQ-05/S5).
    }

    var inputs = document.querySelectorAll('input[data-autocompletar]');
    for (var i = 0; i < inputs.length; i++) {
      componerAutocompletar(inputs[i]);
    }

    var botones = document.querySelectorAll('button[data-agregar-fila]');
    for (var j = 0; j < botones.length; j++) {
      componerFilasHorario(botones[j]);
    }
  }

  function componerAutocompletar(input) {
    var recurso = input.getAttribute('data-autocompletar');
    if (!recurso) {
      return;
    }

    var multiple = input.hasAttribute('data-multiple');
    var cajaChips = multiple ? document.querySelector('div[data-chips="' + recurso + '"]') : null;
    var oculto = document.getElementById('id_' + recurso); // hidden UUID (modo simple)

    if (!multiple && !oculto) {
      return; // Sin hidden id_{recurso} no hay dónde guardar la selección.
    }

    var envoltorio = input.parentNode; // .inputs-entero (position: relative, ancla del dropdown)
    if (!envoltorio) {
      return;
    }

    var estado = { timeout: null, abortador: null, filaActiva: -1, resultados: [] };

    var lista = document.createElement('ul');
    lista.className = 'autocompletar-lista oculta';
    lista.setAttribute('role', 'listbox');
    envoltorio.appendChild(lista);

    if (multiple) {
      // Los chips renderizados por el servidor (re-render REQ-12/REQ-27)
      // también deben ser removibles: se les agrega el botón × si no lo tienen.
      equiparChips(cajaChips);
    }

    input.addEventListener('input', function () {
      if (!multiple && oculto && oculto.value !== '') {
        oculto.value = ''; // El texto editado ya no representa la selección (D7).
      }
      programarBusqueda(input, recurso, multiple, cajaChips, estado, lista);
    });

    input.addEventListener('keydown', function (evento) {
      var tecla = evento.key;

      if (tecla === 'ArrowDown' || tecla === 'ArrowUp') {
        if (lista.classList.contains('oculta')) {
          return;
        }
        evento.preventDefault();
        moverFilaActiva(tecla === 'ArrowDown' ? 1 : -1, lista, estado);
      } else if (tecla === 'Enter') {
        if (!lista.classList.contains('oculta') && estado.filaActiva >= 0) {
          evento.preventDefault();
          seleccionarFila(
            estado.resultados[estado.filaActiva],
            input,
            recurso,
            multiple,
            cajaChips,
            estado,
            lista
          );
        }
        // Sin dropdown abierto: Enter hace el submit normal (texto libre → server).
      } else if (tecla === 'Escape') {
        if (!lista.classList.contains('oculta')) {
          cerrarLista(estado, lista); // Cierra y conserva el valor (REQ-02/S2).
        }
      }
    });

    input.addEventListener('blur', function () {
      if (!lista.classList.contains('oculta')) {
        cerrarLista(estado, lista);
      }
    });

    // Click fuera del componente cierra la lista (los clicks internos eligen
    // vía mousedown antes del blur, para no perder el foco del input).
    document.addEventListener('click', function (evento) {
      if (!envoltorio.contains(evento.target) && !lista.classList.contains('oculta')) {
        cerrarLista(estado, lista);
      }
    });
  }

  function programarBusqueda(input, recurso, multiple, cajaChips, estado, lista) {
    if (estado.timeout) {
      clearTimeout(estado.timeout);
    }
    if (estado.abortador) {
      estado.abortador.abort(); // Descarta la búsqueda previa en vuelo.
    }

    var texto = input.value.trim();
    if (texto === '') {
      cerrarLista(estado, lista);
      return;
    }

    estado.timeout = setTimeout(function () {
      buscar(input, recurso, multiple, cajaChips, estado, lista, texto);
    }, DEBOUNCE_MS);
  }

  function buscar(input, recurso, multiple, cajaChips, estado, lista, texto) {
    var url = baseUrl + '/' + encodeURIComponent(recurso) + '?q=' + encodeURIComponent(texto);

    var areaId = areaElegida(input);
    if (areaId !== '') {
      url += '&area=' + encodeURIComponent(areaId); // REQ-22/S2: scoping por área.
    }

    var abortador = new AbortController();
    estado.abortador = abortador;

    fetch(url, { signal: abortador.signal, headers: { Accept: 'application/json' } })
      .then(function (respuesta) {
        if (!respuesta.ok) {
          throw new Error('HTTP ' + respuesta.status);
        }
        return respuesta.json();
      })
      .then(function (resultados) {
        if (estado.abortador !== abortador) {
          return; // Respuesta obsoleta: ya hay una búsqueda más nueva.
        }
        if (!Array.isArray(resultados)) {
          return;
        }

        if (multiple && cajaChips) {
          resultados = filtradosPorChips(resultados, cajaChips, recurso);
        }
        resultados = resultados.slice(0, MAX_FILAS);

        if (resultados.length === 0) {
          cerrarLista(estado, lista); // Sin coincidencias, el server devuelve [] (REQ-32).
          return;
        }

        renderizarLista(input, recurso, multiple, cajaChips, estado, lista, resultados);
      })
      .catch(function () {
        if (estado.abortador === abortador) {
          cerrarLista(estado, lista); // Fetch erróneo: degradación silenciosa (REQ-05/S5).
        }
      });
  }

  /** UUID del área elegida (hidden id_area), o '' si no hay área (REQ-22). */
  function areaElegida(input) {
    var selector = input.getAttribute('data-area-input');
    if (!selector) {
      return '';
    }
    var areaInput = document.querySelector(selector);
    if (!areaInput) {
      return '';
    }
    var id = areaInput.value.trim();

    return UUID_V4.test(id) ? id : '';
  }

  /** Excluye lo que ya está elegido como chip (no re-proponer, D5). */
  function filtradosPorChips(resultados, cajaChips, recurso) {
    var ids = [];
    var nombres = [];
    var campos = cajaChips.querySelectorAll('input[type="hidden"]');
    for (var i = 0; i < campos.length; i++) {
      var nombreCampo = campos[i].getAttribute('name');
      var valor = campos[i].value.trim();
      if (nombreCampo === recurso + '_ids[]') {
        ids.push(valor);
      } else if (nombreCampo === recurso + '_nombres[]') {
        nombres.push(valor.toLowerCase());
      }
    }

    return resultados.filter(function (fila) {
      if (ids.indexOf(String(fila.id)) !== -1) {
        return false;
      }
      return nombres.indexOf(String(fila.nombre).toLowerCase()) === -1;
    });
  }

  function renderizarLista(input, recurso, multiple, cajaChips, estado, lista, resultados) {
    lista.textContent = '';
    cerosDeActivo(estado, lista);

    for (var i = 0; i < resultados.length; i++) {
      (function (item, fila, indice) {
        item.addEventListener('mousedown', function (evento) {
          evento.preventDefault(); // Sin blur: el input conserva el foco.
          seleccionarFila(fila, input, recurso, multiple, cajaChips, estado, lista);
        });
        item.addEventListener('mouseenter', function () {
          marcarFila(indice, lista, estado);
        });
      })(crearItem(lista, resultados[i]), resultados[i], i);
    }

    lista.classList.remove('oculta');
  }

  function crearItem(lista, fila) {
    var item = document.createElement('li');
    item.className = 'autocompletar-item';
    item.setAttribute('role', 'option');
    item.setAttribute('aria-selected', 'false');
    item.setAttribute('data-id', String(fila.id));
    item.textContent = String(fila.nombre); // textContent: sin interpretar HTML.

    lista.appendChild(item);

    return item;
  }

  function marcarFila(indice, lista, estado) {
    var items = lista.children;
    for (var i = 0; i < items.length; i++) {
      if (i === indice) {
        items[i].classList.add('activo');
        items[i].setAttribute('aria-selected', 'true');
      } else {
        items[i].classList.remove('activo');
        items[i].setAttribute('aria-selected', 'false');
      }
    }
    estado.filaActiva = indice;
  }

  function moverFilaActiva(delta, lista, estado) {
    var total = lista.children.length;
    if (total === 0) {
      return;
    }
    var proxima = estado.filaActiva + delta;
    if (proxima < 0) {
      proxima = total - 1;
    }
    if (proxima >= total) {
      proxima = 0;
    }
    marcarFila(proxima, lista, estado);
  }

  function cerosDeActivo(estado, lista) {
    marcarFila(-1, lista, estado);
  }

  function seleccionarFila(fila, input, recurso, multiple, cajaChips, estado, lista) {
    if (!fila) {
      return;
    }
    var id = String(fila.id);
    var nombre = String(fila.nombre);

    if (multiple && cajaChips) {
      agregarChip(cajaChips, recurso, id, nombre);
      // El input visible también envía {recurso}_nombres[]: si conservara el
      // texto, el server recibiría un par sin id ADICIONAL al del chip
      // (paresChips no lo deduplica contra el par con id). Se limpia y el par
      // autorizado viaja solo en el chip.
      input.value = '';
    } else {
      input.value = nombre;
      var oculto = document.getElementById('id_' + recurso);
      if (oculto) {
        oculto.value = id; // El hidden UUID que el server re-valida (D7).
      }
    }

    cerrarLista(estado, lista);
    input.focus();
  }

  /** Crea un chip con su par oculto y lo hace removible (REQ-04/S4). */
  function agregarChip(cajaChips, recurso, id, nombre) {
    var chip = document.createElement('span');
    chip.className = 'chip';

    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'chip-x';
    boton.setAttribute('aria-label', 'Quitar ' + nombre);
    boton.textContent = '\u00d7'; // ×

    var campoId = document.createElement('input');
    campoId.type = 'hidden';
    campoId.name = recurso + '_ids[]';
    campoId.value = id;

    var campoNombre = document.createElement('input');
    campoNombre.type = 'hidden';
    campoNombre.name = recurso + '_nombres[]';
    campoNombre.value = nombre;

    // El orden DOM es el orden de submisión: el server empareja ids[]/nombres[]
    // por índice (paresChips), así que el par oculto viaja junto en este chip.
    chip.appendChild(document.createTextNode(nombre));
    chip.appendChild(boton);
    chip.appendChild(campoId);
    chip.appendChild(campoNombre);

    boton.addEventListener('click', function () {
      chip.parentNode.removeChild(chip); // Quitar el chip quita su par del submit.
    });

    cajaChips.appendChild(chip);
  }

  /** Agrega el botón × a los chips que ya renderizó el servidor (re-render). */
  function equiparChips(cajaChips) {
    if (!cajaChips) {
      return;
    }
    var chips = cajaChips.querySelectorAll('span.chip');
    for (var i = 0; i < chips.length; i++) {
      (function (chip) {
        if (chip.querySelector('.chip-x')) {
          return;
        }
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'chip-x';
        boton.setAttribute('aria-label', 'Quitar ' + (chip.textContent || '').trim());
        boton.textContent = '\u00d7';
        boton.addEventListener('click', function () {
          chip.parentNode.removeChild(chip);
        });
        chip.insertBefore(boton, chip.firstChild);
      })(chips[i]);
    }
  }

  function cerrarLista(estado, lista) {
    lista.classList.add('oculta');
    cerosDeActivo(estado, lista);
  }

  /** Clona la última fila de horario en blanco (REQ-23; WU3). */
  function componerFilasHorario(boton) {
    boton.addEventListener('click', function () {
      var contenedor = boton.parentNode;
      if (!contenedor) {
        return;
      }
      var filas = contenedor.querySelectorAll('.fila-horario');
      if (filas.length === 0) {
        return;
      }

      var origen = filas[filas.length - 1];
      var copia = origen.cloneNode(true);

      var selectorDia = copia.querySelector('select');
      if (selectorDia) {
        selectorDia.value = '';
      }
      var horas = copia.querySelectorAll('input[type="time"]');
      for (var i = 0; i < horas.length; i++) {
        horas[i].value = '';
      }

      // Las filas vacías se descartan en el servidor (leerFilas), así la fila
      // nueva sin completar nunca rompe la validación.
      contenedor.insertBefore(copia, boton);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();