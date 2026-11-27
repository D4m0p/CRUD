# CRUD de Productos: API Fetch y MySQL (Guardar, Editar, Buscar)

Laboratorio de Desarrollo Web. Aplicación para administrar un inventario de productos:
permite **guardar** productos nuevos, **editar** los existentes y **buscarlos** por código,
nombre o categoría. El frontend se comunica con un único endpoint PHP mediante `fetch`
y los datos se almacenan en MySQL usando PDO.

## Tecnologías

- PHP 8.1 o superior (sin frameworks ni Composer)
- MySQL 8 con PDO y sentencias preparadas
- HTML5, Bootstrap 5.3.3 y Bootstrap Icons (CDN jsdelivr)
- SweetAlert2 11.14.5 (CDN jsdelivr)
- JavaScript puro con la API `fetch`

## Instalación

1. Cree la base de datos, la tabla y los productos de ejemplo:

   ```bash
   sudo mysql < sql/01_esquema.sql
   # o bien: pkexec mysql < sql/01_esquema.sql
   ```

2. Cree el usuario de la aplicación (solo SELECT, INSERT y UPDATE):

   ```bash
   sudo mysql < sql/02_usuario.sql
   ```

3. Si cambia el usuario o la contraseña, actualice `config/config.php`.

4. Inicie el servidor integrado de PHP desde la carpeta del proyecto:

   ```bash
   php -S localhost:8080
   ```

5. Abra <http://localhost:8080> en el navegador.

## Estructura

```
crud-productos/
├── index.php              Página principal: formulario, buscador y tabla
├── config/
│   └── config.php         Constantes de conexión a MySQL
├── clases/
│   ├── DB.php             Conexión PDO única (Singleton)
│   └── Producto.php       Modelo: validación y consultas del producto
├── api/
│   └── productos.php      Endpoint único con switch (guardar, modificar, buscar, listar, obtener)
├── assets/
│   ├── js/app.js          fetch, switch, validación y SweetAlert2
│   └── css/estilos.css    Ajustes visuales sobre Bootstrap
├── sql/
│   ├── 01_esquema.sql     Base de datos, tabla y datos de ejemplo
│   └── 02_usuario.sql     Usuario de la aplicación con permisos mínimos
└── README.md
```

## API

Todas las respuestas tienen la forma:

```json
{ "success": true, "message": "Texto para el usuario", "errors": {}, "data": null }
```

| Acción      | Método | Parámetros                                                        | Códigos HTTP        |
|-------------|--------|-------------------------------------------------------------------|---------------------|
| `guardar`   | POST   | codigo, nombre, descripcion, categoria, precio, cantidad          | 201, 400, 409, 500  |
| `modificar` | POST   | id + los mismos campos de `guardar`                               | 200, 400, 404, 409  |
| `buscar`    | GET    | `q` (texto; vacío devuelve todos)                                 | 200, 400            |
| `listar`    | GET    | (ninguno)                                                         | 200                 |
| `obtener`   | GET    | `id`                                                              | 200, 400, 404       |

Una acción con un método incorrecto responde `405`, y una acción desconocida, `400`.
Los errores de base de datos responden `500` con un mensaje genérico; el detalle solo
se registra en el log del servidor.

## Cómo se cumple cada criterio

| Criterio | Pts | Dónde |
|----------|-----|-------|
| Formulario que guarda, edita y busca correctamente | 10 | `index.php` (formulario, buscador y tabla); `assets/js/app.js`: `enviarFormulario()`, `cargarProducto()`, `buscarProductos()` |
| Bootstrap para diseño y botones, con componente Alert | 10 | `index.php`: grilla, cards, `table-responsive`, botones `btn-*`, alert informativo `alert-info alert-dismissible` y alert de errores `#alertaErrores` dentro del formulario |
| Lógica Guardar / Modificar centralizada con `switch` en PHP | 15 | `api/productos.php`: `switch ($accion)` con `guardar`, `modificar`, `buscar`, `listar`, `obtener` y `default` |
| Uso de `switch` en JavaScript | 10 | `assets/js/app.js`: `switch (modoActual)` en `enviarFormulario()`, `switch (modo)` en `establecerModo()` y `switch (boton.dataset.accion)` en `manejarAccionTabla()` |
| Clase DB implementada | 10 | `clases/DB.php`: Singleton PDO con `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES` en `false` y `utf8mb4` |
| Clase Producto implementada | 10 | `clases/Producto.php`: `cargar()`, `validar()`, `guardar()`, `modificar()`, `buscar()`, `listar()`, `obtenerPorId()`, `existeCodigo()`, todo con sentencias preparadas |
| Validaciones y control de errores (cliente y servidor) | 10 | Cliente: atributos `required`, `min`, `maxlength`, `pattern`, clases `is-invalid` / `invalid-feedback` y `validarFormulario()`. Servidor: `Producto::validar()`, `try/catch (PDOException)` y códigos 400/404/405/409/500 |
| Respuestas JSON uniformes | 10 | `api/productos.php`: función `responder($success, $message, $errors, $data, $status)` con `Content-Type: application/json; charset=utf-8` |
| SweetAlert2 para éxitos y errores | 10 | `assets/js/app.js`: `Swal.fire` con íconos `success`, `error`, `warning` y `question`; los errores de validación se listan en el mensaje |
| Estructura de carpetas clara | 5 | Ver sección **Estructura** |

## Seguridad

- Todas las consultas usan sentencias preparadas con parámetros.
- En la búsqueda se escapan los comodines `%` y `_` de `LIKE`.
- Los datos se pintan en la tabla con `textContent` y los mensajes HTML de SweetAlert2
  pasan por la función `escapar()`, lo que evita inyección de HTML (XSS).
- El usuario `crud_app` no tiene permiso `DELETE` ni acceso a otras tablas.
