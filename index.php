<?php
/**
 * Página principal del CRUD de productos.
 * Muestra el formulario (Guardar / Modificar), el buscador y la tabla de resultados.
 * Toda la comunicación con el servidor se hace con fetch desde assets/js/app.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/clases/Producto.php';

/**
 * Escapa texto para imprimirlo de forma segura en HTML.
 */
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRUD de Productos | API Fetch y MySQL</title>

    <!-- Bootstrap 5.3 e íconos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos propios -->
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>

<!-- Encabezado -->
<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-semibold">
            <i class="bi bi-box-seam me-2"></i>Inventario de Productos
        </span>
        <span class="navbar-text small text-white-50">CRUD con API Fetch y MySQL</span>
    </div>
</nav>

<main class="container py-4">

    <!-- Alert informativo de Bootstrap (se puede cerrar) -->
    <div class="alert alert-info alert-dismissible fade show d-flex align-items-start" role="alert">
        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
        <div>
            <strong>Instrucciones:</strong> complete el formulario y presione <strong>Guardar</strong>
            para registrar un producto. Para cambiar uno existente, presione <strong>Editar</strong> en la tabla,
            ajuste los datos y presione <strong>Modificar</strong>. Use el buscador para filtrar por código,
            nombre o categoría.
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>

    <div class="row g-4">

        <!-- Formulario de producto -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0" id="tituloFormulario">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo producto
                    </h2>
                    <span class="badge text-bg-success" id="indicadorModo">Modo: Guardar</span>
                </div>
                <div class="card-body">

                    <!-- Alert de Bootstrap con los errores de validación (oculto por defecto) -->
                    <div class="alert alert-danger d-none" role="alert" id="alertaErrores">
                        <h3 class="h6 alert-heading mb-1">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Corrija los siguientes errores:
                        </h3>
                        <ul class="mb-0 small" id="listaErrores"></ul>
                    </div>

                    <form id="formProducto" novalidate>
                        <!-- Id del producto (vacío en modo Guardar) -->
                        <input type="hidden" id="id" name="id" value="">

                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="codigo" name="codigo"
                                       required minlength="3" maxlength="20" pattern="[A-Za-z0-9\-]{3,20}"
                                       placeholder="PRD-001" autocomplete="off">
                                <div class="invalid-feedback" data-error="codigo">
                                    Ingrese un código de 3 a 20 caracteres (letras, números o guion).
                                </div>
                            </div>

                            <div class="col-sm-7">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre"
                                       required minlength="3" maxlength="100" placeholder="Nombre del producto">
                                <div class="invalid-feedback" data-error="nombre">
                                    Ingrese un nombre de 3 a 100 caracteres.
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"
                                          maxlength="255" placeholder="Opcional (máximo 255 caracteres)"></textarea>
                                <div class="invalid-feedback" data-error="descripcion">
                                    La descripción no puede superar los 255 caracteres.
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria" name="categoria" required>
                                    <option value="">Seleccione una categoría...</option>
                                    <?php foreach (Producto::CATEGORIAS as $categoria): ?>
                                        <option value="<?= e($categoria) ?>"><?= e($categoria) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" data-error="categoria">Seleccione una categoría.</div>
                            </div>

                            <div class="col-sm-6">
                                <label for="precio" class="form-label">Precio ($) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="precio" name="precio"
                                       required min="0.01" max="99999999.99" step="0.01" placeholder="0.00">
                                <div class="invalid-feedback" data-error="precio">Ingrese un precio mayor que cero.</div>
                            </div>

                            <div class="col-sm-6">
                                <label for="cantidad" class="form-label">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad"
                                       required min="0" max="4294967295" step="1" placeholder="0">
                                <div class="invalid-feedback" data-error="cantidad">
                                    Ingrese una cantidad entera mayor o igual a cero.
                                </div>
                            </div>
                        </div>

                        <!-- Botones: el principal cambia entre Guardar y Modificar -->
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-success flex-grow-1" id="btnPrincipal">
                                <i class="bi bi-save me-1"></i><span>Guardar</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btnCancelar">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Cancelar / Nuevo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Buscador y tabla de productos -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0"><i class="bi bi-list-ul me-1"></i>Productos registrados</h2>
                </div>
                <div class="card-body">

                    <!-- Buscador -->
                    <form id="formBuscar" class="mb-3" role="search">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="q" name="q" maxlength="100"
                                   placeholder="Buscar por código, nombre o categoría" autocomplete="off">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                        </div>
                        <div class="form-text" id="resumenBusqueda">Cargando productos...</div>
                    </form>

                    <!-- Tabla de resultados -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="text-end">Precio</th>
                                <th scope="col" class="text-end">Cantidad</th>
                                <th scope="col" class="text-center">Acción</th>
                            </tr>
                            </thead>
                            <tbody id="cuerpoTabla">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Cargando...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="text-center text-muted small py-3">
    Laboratorio de Desarrollo Web: CRUD con API Fetch y MySQL (Guardar, Editar, Buscar)
</footer>

<!-- Scripts: Bootstrap, SweetAlert2 (incluye sus estilos) y la lógica de la aplicación -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
