<?php
/**
 * Endpoint único de la API de productos.
 * Lee el parámetro `accion` y, mediante un switch, decide qué operación ejecutar:
 *   guardar   (POST) Crea un producto
 *   modificar (POST) Actualiza un producto existente
 *   buscar    (GET)  Busca por código, nombre o categoría (?q=)
 *   listar    (GET)  Devuelve todos los productos
 *   obtener   (GET)  Devuelve un producto por su id (?id=)
 *
 * Todas las respuestas son JSON con la forma:
 *   {"success": bool, "message": string, "errors": object|array, "data": ...}
 */

declare(strict_types=1);

require_once __DIR__ . '/../clases/Producto.php';

/**
 * Envía una respuesta JSON uniforme y termina la ejecución.
 *
 * @param bool   $success Resultado de la operación
 * @param string $message Mensaje para mostrar al usuario
 * @param array  $errors  Errores por campo (si los hay)
 * @param mixed  $data    Datos devueltos
 * @param int    $status  Código de estado HTTP
 */
function responder(bool $success, string $message, array $errors = [], mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        // Un arreglo vacío se envía como objeto {} para mantener el mismo tipo
        'errors'  => $errors === [] ? new stdClass() : $errors,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Comprueba que la petición use el método HTTP esperado; si no, responde 405.
 */
function exigirMetodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        header('Allow: ' . $metodo);
        responder(false, "Método no permitido. Esta acción solo acepta {$metodo}.", [], null, 405);
    }
}

/**
 * Obtiene los datos enviados por POST, ya sea como FormData o como JSON.
 */
function leerDatosPost(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($tipo, 'application/json')) {
        $datos = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            responder(false, 'El cuerpo de la petición no es un JSON válido.', [], null, 400);
        }
        return $datos;
    }

    return $_POST;
}

// La acción puede venir en la URL (GET) o en el cuerpo (POST)
$datosPost = $_SERVER['REQUEST_METHOD'] === 'POST' ? leerDatosPost() : [];
$accion = trim((string) ($_GET['accion'] ?? $datosPost['accion'] ?? ''));

try {
    $producto = new Producto();

    switch ($accion) {
        case 'guardar':
            exigirMetodo('POST');
            $producto->cargar($datosPost);
            $producto->id = null; // Al guardar siempre se crea un registro nuevo

            $errores = $producto->validar();
            if ($errores !== []) {
                responder(false, 'Revise los datos del formulario.', $errores, null, 400);
            }
            if ($producto->existeCodigo($producto->codigo)) {
                responder(false, 'Ya existe un producto con ese código.', ['codigo' => 'Este código ya está registrado.'], null, 409);
            }

            $id = $producto->guardar();
            responder(true, 'Producto guardado correctamente.', [], $producto->obtenerPorId($id), 201);

        case 'modificar':
            exigirMetodo('POST');
            $producto->cargar($datosPost);

            $errores = $producto->validar(true);
            if ($errores !== []) {
                responder(false, 'Revise los datos del formulario.', $errores, null, 400);
            }
            if ($producto->existeCodigo($producto->codigo, $producto->id)) {
                responder(false, 'Ya existe otro producto con ese código.', ['codigo' => 'Este código ya está registrado.'], null, 409);
            }
            if (!$producto->modificar()) {
                responder(false, 'El producto que intenta modificar no existe.', [], null, 404);
            }

            responder(true, 'Producto modificado correctamente.', [], $producto->obtenerPorId((int) $producto->id));

        case 'buscar':
            exigirMetodo('GET');
            $termino = (string) ($_GET['q'] ?? '');
            if (mb_strlen($termino) > 100) {
                responder(false, 'El texto de búsqueda es demasiado largo.', ['q' => 'Use como máximo 100 caracteres.'], null, 400);
            }

            $resultados = $producto->buscar($termino);
            $total = count($resultados);
            responder(true, $total === 1 ? 'Se encontró 1 producto.' : "Se encontraron {$total} productos.", [], $resultados);

        case 'listar':
            exigirMetodo('GET');
            $resultados = $producto->listar();
            responder(true, 'Listado de productos.', [], $resultados);

        case 'obtener':
            exigirMetodo('GET');
            $id = (string) ($_GET['id'] ?? '');
            if (!ctype_digit($id) || (int) $id <= 0) {
                responder(false, 'El identificador del producto no es válido.', ['id' => 'Debe ser un número entero positivo.'], null, 400);
            }

            $datos = $producto->obtenerPorId((int) $id);
            if ($datos === null) {
                responder(false, 'No se encontró el producto solicitado.', [], null, 404);
            }
            responder(true, 'Producto encontrado.', [], $datos);

        default:
            responder(false, 'Acción no válida.', ['accion' => 'Use guardar, modificar, buscar, listar u obtener.'], null, 400);
    }
} catch (PDOException $e) {
    // 23000 = violación de restricción (p. ej. código duplicado por una inserción simultánea)
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate')) {
        responder(false, 'Ya existe un producto con ese código.', ['codigo' => 'Este código ya está registrado.'], null, 409);
    }

    // El detalle técnico se registra en el servidor y no se muestra al usuario
    error_log('Error de base de datos: ' . $e->getMessage());
    responder(false, 'Ocurrió un error en la base de datos. Intente nuevamente más tarde.', [], null, 500);
} catch (Throwable $e) {
    error_log('Error inesperado: ' . $e->getMessage());
    responder(false, 'Ocurrió un error inesperado. Intente nuevamente más tarde.', [], null, 500);
}
