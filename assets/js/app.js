/**
 * Lógica del cliente del CRUD de productos.
 * - Envía y recibe datos de api/productos.php con fetch.
 * - Usa switch para decidir entre Guardar y Modificar, y para las acciones de la tabla.
 * - Valida el formulario con las clases de Bootstrap antes de enviar.
 * - Muestra éxitos y errores con SweetAlert2.
 */
'use strict';

/** Dirección del endpoint único de la API */
const API_URL = 'api/productos.php';

/** Modos posibles del formulario */
const MODO = Object.freeze({ GUARDAR: 'guardar', MODIFICAR: 'modificar' });

/** Categorías válidas (las mismas que valida el servidor) */
const CATEGORIAS = ['Electrónica', 'Hogar', 'Oficina', 'Alimentos', 'Ropa', 'Otros'];

/** Modo actual del formulario */
let modoActual = MODO.GUARDAR;

/** Temporizador para la búsqueda mientras se escribe */
let temporizadorBusqueda = null;

// Referencias a los elementos de la página
const formProducto = document.getElementById('formProducto');
const campoId = document.getElementById('id');
const btnPrincipal = document.getElementById('btnPrincipal');
const btnCancelar = document.getElementById('btnCancelar');
const tituloFormulario = document.getElementById('tituloFormulario');
const indicadorModo = document.getElementById('indicadorModo');
const alertaErrores = document.getElementById('alertaErrores');
const listaErrores = document.getElementById('listaErrores');
const formBuscar = document.getElementById('formBuscar');
const campoBusqueda = document.getElementById('q');
const resumenBusqueda = document.getElementById('resumenBusqueda');
const cuerpoTabla = document.getElementById('cuerpoTabla');

/** Nombres de los campos del formulario que se validan */
const CAMPOS = ['codigo', 'nombre', 'descripcion', 'categoria', 'precio', 'cantidad'];

/* ------------------------------------------------------------------ */
/* Utilidades                                                          */
/* ------------------------------------------------------------------ */

/**
 * Escapa caracteres especiales de HTML para evitar XSS
 * (se usa en los mensajes de SweetAlert2 que se arman como HTML).
 */
function escapar(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Da formato de moneda a un precio. */
function formatearPrecio(valor) {
    return '$' + Number(valor).toLocaleString('es-PA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Realiza una petición a la API y devuelve el JSON recibido.
 * Si la respuesta no es JSON (p. ej. error del servidor web) lanza un error.
 */
async function peticion(url, opciones = {}) {
    const respuesta = await fetch(url, { headers: { Accept: 'application/json' }, ...opciones });

    let json;
    try {
        json = await respuesta.json();
    } catch {
        throw new Error(`Respuesta no válida del servidor (HTTP ${respuesta.status}).`);
    }
    return json;
}

/** Muestra un error de conexión con SweetAlert2. */
function mostrarErrorRed(error) {
    console.error(error);
    Swal.fire({
        icon: 'error',
        title: 'Error de conexión',
        text: 'No fue posible comunicarse con el servidor. Verifique su conexión e intente nuevamente.',
    });
}

/* ------------------------------------------------------------------ */
/* Validación del formulario                                           */
/* ------------------------------------------------------------------ */

/** Quita las marcas de error de todos los campos y oculta el alert de errores. */
function limpiarErrores() {
    CAMPOS.forEach((campo) => formProducto.elements[campo].classList.remove('is-invalid', 'is-valid'));
    formProducto.classList.remove('was-validated');
    alertaErrores.classList.add('d-none');
    listaErrores.replaceChildren();
}

/**
 * Marca los campos con error (is-invalid), escribe el mensaje en su invalid-feedback
 * y llena el alert de Bootstrap con la lista de errores.
 *
 * @param {Object<string, string>} errores Mensajes por campo
 */
function mostrarErrores(errores) {
    limpiarErrores();

    CAMPOS.forEach((campo) => {
        const elemento = formProducto.elements[campo];
        if (errores[campo]) {
            elemento.classList.add('is-invalid');
            const retro = formProducto.querySelector(`[data-error="${campo}"]`);
            if (retro) retro.textContent = errores[campo];
        } else {
            elemento.classList.add('is-valid');
        }
    });

    const mensajes = Object.values(errores);
    if (mensajes.length > 0) {
        mensajes.forEach((mensaje) => {
            const li = document.createElement('li');
            li.textContent = mensaje;
            listaErrores.appendChild(li);
        });
        alertaErrores.classList.remove('d-none');
    }
}

/**
 * Valida los datos en el cliente (las mismas reglas que el servidor).
 *
 * @returns {Object<string, string>} Errores por campo; vacío si todo es correcto
 */
function validarFormulario(datos) {
    const errores = {};

    if (!datos.codigo) {
        errores.codigo = 'Ingrese el código del producto.';
    } else if (!/^[A-Za-z0-9-]{3,20}$/.test(datos.codigo)) {
        errores.codigo = 'El código debe tener de 3 a 20 caracteres: letras, números o guion (p. ej. PRD-001).';
    }

    if (!datos.nombre) {
        errores.nombre = 'Ingrese el nombre del producto.';
    } else if (datos.nombre.length < 3 || datos.nombre.length > 100) {
        errores.nombre = 'El nombre debe tener entre 3 y 100 caracteres.';
    }

    if (datos.descripcion.length > 255) {
        errores.descripcion = 'La descripción no puede superar los 255 caracteres.';
    }

    if (!datos.categoria) {
        errores.categoria = 'Seleccione una categoría.';
    } else if (!CATEGORIAS.includes(datos.categoria)) {
        errores.categoria = 'La categoría seleccionada no es válida.';
    }

    if (datos.precio === '') {
        errores.precio = 'Ingrese el precio.';
    } else if (!/^\d+(\.\d{1,2})?$/.test(datos.precio)) {
        errores.precio = 'El precio debe ser un número con hasta 2 decimales.';
    } else if (Number(datos.precio) <= 0) {
        errores.precio = 'El precio debe ser mayor que cero.';
    } else if (Number(datos.precio) > 99999999.99) {
        errores.precio = 'El precio supera el valor máximo permitido.';
    }

    if (datos.cantidad === '') {
        errores.cantidad = 'Ingrese la cantidad.';
    } else if (!/^\d+$/.test(datos.cantidad)) {
        errores.cantidad = 'La cantidad debe ser un número entero mayor o igual a cero.';
    } else if (Number(datos.cantidad) > 4294967295) {
        errores.cantidad = 'La cantidad supera el valor máximo permitido.';
    }

    return errores;
}

/** Lee los valores del formulario ya recortados. */
function leerFormulario() {
    const datos = {};
    CAMPOS.forEach((campo) => {
        datos[campo] = formProducto.elements[campo].value.trim();
    });
    datos.codigo = datos.codigo.toUpperCase();
    return datos;
}

/* ------------------------------------------------------------------ */
/* Modo del formulario (Guardar / Modificar)                           */
/* ------------------------------------------------------------------ */

/**
 * Cambia el modo del formulario y actualiza el texto y el color del botón principal.
 */
function establecerModo(modo) {
    modoActual = modo;
    const texto = btnPrincipal.querySelector('span');
    const icono = btnPrincipal.querySelector('i');

    switch (modo) {
        case MODO.MODIFICAR:
            texto.textContent = 'Modificar';
            icono.className = 'bi bi-pencil-square me-1';
            btnPrincipal.classList.replace('btn-success', 'btn-warning');
            tituloFormulario.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Editar producto';
            indicadorModo.textContent = 'Modo: Modificar';
            indicadorModo.className = 'badge text-bg-warning';
            break;

        case MODO.GUARDAR:
        default:
            modoActual = MODO.GUARDAR;
            texto.textContent = 'Guardar';
            icono.className = 'bi bi-save me-1';
            btnPrincipal.classList.replace('btn-warning', 'btn-success');
            tituloFormulario.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Nuevo producto';
            indicadorModo.textContent = 'Modo: Guardar';
            indicadorModo.className = 'badge text-bg-success';
            break;
    }
}

/** Limpia el formulario y vuelve al modo Guardar. */
function reiniciarFormulario() {
    formProducto.reset();
    campoId.value = '';
    limpiarErrores();
    establecerModo(MODO.GUARDAR);
}

/* ------------------------------------------------------------------ */
/* Guardar / Modificar                                                 */
/* ------------------------------------------------------------------ */

/**
 * Maneja el envío del formulario: valida en el cliente y, según el modo actual,
 * envía la acción "guardar" o "modificar" a la API.
 */
async function enviarFormulario(evento) {
    evento.preventDefault();

    const datos = leerFormulario();
    const errores = validarFormulario(datos);

    if (Object.keys(errores).length > 0) {
        mostrarErrores(errores);
        Swal.fire({
            icon: 'warning',
            title: 'Datos incompletos o incorrectos',
            html: listaHtml(errores),
        });
        return;
    }

    // Se arma el FormData con los datos y la acción correspondiente al modo
    const formData = new FormData();
    Object.entries(datos).forEach(([campo, valor]) => formData.append(campo, valor));

    let mensajeConfirmacion;
    switch (modoActual) {
        case MODO.GUARDAR:
            formData.append('accion', 'guardar');
            mensajeConfirmacion = null; // Guardar no requiere confirmación
            break;

        case MODO.MODIFICAR:
            formData.append('accion', 'modificar');
            formData.append('id', campoId.value);
            mensajeConfirmacion = `¿Desea guardar los cambios del producto ${datos.codigo}?`;
            break;

        default:
            Swal.fire({ icon: 'error', title: 'Error', text: 'Modo de formulario desconocido.' });
            return;
    }

    if (mensajeConfirmacion) {
        const confirmacion = await Swal.fire({
            icon: 'question',
            title: 'Confirmar modificación',
            text: mensajeConfirmacion,
            showCancelButton: true,
            confirmButtonText: 'Sí, modificar',
            cancelButtonText: 'Cancelar',
        });
        if (!confirmacion.isConfirmed) return;
    }

    btnPrincipal.disabled = true;
    try {
        const json = await peticion(API_URL, { method: 'POST', body: formData });

        if (json.success) {
            await Swal.fire({ icon: 'success', title: '¡Listo!', text: json.message, timer: 2000, showConfirmButton: false });
            reiniciarFormulario();
            buscarProductos(campoBusqueda.value);
        } else {
            // Errores devueltos por el servidor (validación, duplicado, no encontrado...)
            const erroresServidor = json.errors && !Array.isArray(json.errors) ? json.errors : {};
            mostrarErrores(erroresServidor);
            Swal.fire({
                icon: 'error',
                title: json.message || 'No se pudo completar la operación',
                html: listaHtml(erroresServidor),
            });
        }
    } catch (error) {
        mostrarErrorRed(error);
    } finally {
        btnPrincipal.disabled = false;
    }
}

/** Convierte los errores en una lista HTML escapada para SweetAlert2. */
function listaHtml(errores) {
    const mensajes = Object.values(errores);
    if (mensajes.length === 0) return '';
    return '<ul class="text-start mb-0">' + mensajes.map((m) => `<li>${escapar(m)}</li>`).join('') + '</ul>';
}

/* ------------------------------------------------------------------ */
/* Buscar / listar                                                     */
/* ------------------------------------------------------------------ */

/**
 * Consulta la API: si el término está vacío lista todo; si no, busca.
 */
async function buscarProductos(termino = '') {
    termino = termino.trim();
    const url = termino === ''
        ? `${API_URL}?accion=listar`
        : `${API_URL}?accion=buscar&q=${encodeURIComponent(termino)}`;

    try {
        const json = await peticion(url);

        if (!json.success) {
            Swal.fire({ icon: 'error', title: 'Error al buscar', text: json.message });
            return;
        }

        pintarTabla(json.data);
        resumenBusqueda.textContent = termino === ''
            ? `Mostrando todos los productos (${json.data.length}).`
            : `${json.message} Búsqueda: "${termino}".`;
    } catch (error) {
        resumenBusqueda.textContent = 'No se pudieron cargar los productos.';
        mostrarErrorRed(error);
    }
}

/**
 * Dibuja las filas de la tabla. Se usa textContent para que ningún dato
 * se interprete como HTML (protección contra XSS).
 */
function pintarTabla(productos) {
    cuerpoTabla.replaceChildren();

    if (!Array.isArray(productos) || productos.length === 0) {
        const fila = cuerpoTabla.insertRow();
        const celda = fila.insertCell();
        celda.colSpan = 6;
        celda.className = 'text-center text-muted py-4';
        celda.textContent = 'No se encontraron productos.';
        return;
    }

    productos.forEach((producto) => {
        const fila = cuerpoTabla.insertRow();

        const celdaCodigo = fila.insertCell();
        celdaCodigo.className = 'fw-semibold font-monospace';
        celdaCodigo.textContent = producto.codigo;

        const celdaNombre = fila.insertCell();
        celdaNombre.textContent = producto.nombre;
        if (producto.descripcion) {
            const detalle = document.createElement('div');
            detalle.className = 'small text-muted text-truncate descripcion-corta';
            detalle.textContent = producto.descripcion;
            detalle.title = producto.descripcion;
            celdaNombre.appendChild(detalle);
        }

        const celdaCategoria = fila.insertCell();
        const insignia = document.createElement('span');
        insignia.className = 'badge rounded-pill text-bg-light border';
        insignia.textContent = producto.categoria;
        celdaCategoria.appendChild(insignia);

        const celdaPrecio = fila.insertCell();
        celdaPrecio.className = 'text-end';
        celdaPrecio.textContent = formatearPrecio(producto.precio);

        const celdaCantidad = fila.insertCell();
        celdaCantidad.className = 'text-end';
        celdaCantidad.textContent = producto.cantidad;

        // Botón Editar: la acción y el id se guardan en atributos data-*
        const celdaAccion = fila.insertCell();
        celdaAccion.className = 'text-center';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-sm btn-outline-primary';
        boton.dataset.accion = 'editar';
        boton.dataset.id = producto.id;
        boton.innerHTML = '<i class="bi bi-pencil"></i> Editar';
        celdaAccion.appendChild(boton);
    });
}

/* ------------------------------------------------------------------ */
/* Editar (obtener y cargar en el formulario)                          */
/* ------------------------------------------------------------------ */

/**
 * Pide un producto por id y lo carga en el formulario en modo Modificar.
 */
async function cargarProducto(id) {
    try {
        const json = await peticion(`${API_URL}?accion=obtener&id=${encodeURIComponent(id)}`);

        if (!json.success) {
            Swal.fire({ icon: 'error', title: 'No se pudo cargar', text: json.message });
            return;
        }

        const producto = json.data;
        limpiarErrores();
        campoId.value = producto.id;
        CAMPOS.forEach((campo) => {
            formProducto.elements[campo].value = producto[campo] ?? '';
        });

        establecerModo(MODO.MODIFICAR);
        formProducto.scrollIntoView({ behavior: 'smooth', block: 'start' });
        formProducto.elements.codigo.focus({ preventScroll: true });
    } catch (error) {
        mostrarErrorRed(error);
    }
}

/**
 * Atiende los clics de los botones de la tabla según su data-accion.
 */
function manejarAccionTabla(evento) {
    const boton = evento.target.closest('button[data-accion]');
    if (!boton) return;

    switch (boton.dataset.accion) {
        case 'editar':
            cargarProducto(boton.dataset.id);
            break;

        default:
            console.warn('Acción de tabla no reconocida:', boton.dataset.accion);
            break;
    }
}

/* ------------------------------------------------------------------ */
/* Eventos                                                             */
/* ------------------------------------------------------------------ */

formProducto.addEventListener('submit', enviarFormulario);
btnCancelar.addEventListener('click', reiniciarFormulario);
cuerpoTabla.addEventListener('click', manejarAccionTabla);

// Al corregir un campo se quita su marca de error
formProducto.addEventListener('input', (evento) => {
    evento.target.classList.remove('is-invalid');
});

// Buscar al presionar el botón o Enter
formBuscar.addEventListener('submit', (evento) => {
    evento.preventDefault();
    clearTimeout(temporizadorBusqueda);
    buscarProductos(campoBusqueda.value);
});

// Buscar mientras se escribe, esperando 300 ms desde la última tecla
campoBusqueda.addEventListener('input', () => {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(() => buscarProductos(campoBusqueda.value), 300);
});

// Carga inicial de la tabla
document.addEventListener('DOMContentLoaded', () => buscarProductos());
