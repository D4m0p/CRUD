<?php
/**
 * Clase Producto
 * Representa un producto del inventario y encapsula su validación
 * y todas las operaciones con la tabla `productos`.
 */

declare(strict_types=1);

require_once __DIR__ . '/DB.php';

class Producto
{
    /** Categorías permitidas (deben coincidir con el <select> del formulario) */
    public const CATEGORIAS = ['Electrónica', 'Hogar', 'Oficina', 'Alimentos', 'Ropa', 'Otros'];

    /** Formato del código: letras, números y guion (p. ej. PRD-001) */
    public const PATRON_CODIGO = '/^[A-Za-z0-9-]{3,20}$/';

    /** Valor máximo permitido para el precio (DECIMAL(10,2)) */
    private const PRECIO_MAXIMO = 99999999.99;

    /** Valor máximo permitido para la cantidad (INT UNSIGNED) */
    private const CANTIDAD_MAXIMA = 4294967295;

    // Propiedades del producto
    public ?int $id = null;
    public string $codigo = '';
    public string $nombre = '';
    public ?string $descripcion = null;
    public string $categoria = '';
    public string $precio = '';
    public string $cantidad = '';

    /** @var PDO Conexión a la base de datos */
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::conectar();
    }

    /**
     * Carga los datos recibidos (formulario o JSON) y los sanea:
     * recorta espacios y normaliza el código a mayúsculas.
     *
     * @param array $datos Datos sin procesar
     */
    public function cargar(array $datos): void
    {
        $id = trim((string) ($datos['id'] ?? ''));
        $this->id = ctype_digit($id) && (int) $id > 0 ? (int) $id : null;

        $this->codigo      = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $this->nombre      = trim((string) ($datos['nombre'] ?? ''));
        $descripcion       = trim((string) ($datos['descripcion'] ?? ''));
        $this->descripcion = $descripcion === '' ? null : $descripcion;
        $this->categoria   = trim((string) ($datos['categoria'] ?? ''));
        $this->precio      = str_replace(',', '.', trim((string) ($datos['precio'] ?? '')));
        $this->cantidad    = trim((string) ($datos['cantidad'] ?? ''));
    }

    /**
     * Valida los datos cargados.
     *
     * @param bool $requiereId Indica si el id es obligatorio (al modificar)
     * @return array<string, string> Errores por campo; vacío si todo es válido
     */
    public function validar(bool $requiereId = false): array
    {
        $errores = [];

        if ($requiereId && $this->id === null) {
            $errores['id'] = 'El identificador del producto no es válido.';
        }

        // Código
        if ($this->codigo === '') {
            $errores['codigo'] = 'Ingrese el código del producto.';
        } elseif (!preg_match(self::PATRON_CODIGO, $this->codigo)) {
            $errores['codigo'] = 'El código debe tener de 3 a 20 caracteres: letras, números o guion (p. ej. PRD-001).';
        }

        // Nombre
        if ($this->nombre === '') {
            $errores['nombre'] = 'Ingrese el nombre del producto.';
        } elseif (mb_strlen($this->nombre) < 3 || mb_strlen($this->nombre) > 100) {
            $errores['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
        }

        // Descripción (opcional)
        if ($this->descripcion !== null && mb_strlen($this->descripcion) > 255) {
            $errores['descripcion'] = 'La descripción no puede superar los 255 caracteres.';
        }

        // Categoría: debe pertenecer a la lista fija
        if ($this->categoria === '') {
            $errores['categoria'] = 'Seleccione una categoría.';
        } elseif (!in_array($this->categoria, self::CATEGORIAS, true)) {
            $errores['categoria'] = 'La categoría seleccionada no es válida.';
        }

        // Precio: número mayor que cero con hasta 2 decimales
        if ($this->precio === '') {
            $errores['precio'] = 'Ingrese el precio.';
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $this->precio)) {
            $errores['precio'] = 'El precio debe ser un número con hasta 2 decimales.';
        } elseif ((float) $this->precio <= 0) {
            $errores['precio'] = 'El precio debe ser mayor que cero.';
        } elseif ((float) $this->precio > self::PRECIO_MAXIMO) {
            $errores['precio'] = 'El precio supera el valor máximo permitido.';
        }

        // Cantidad: entero mayor o igual a cero
        if ($this->cantidad === '') {
            $errores['cantidad'] = 'Ingrese la cantidad.';
        } elseif (!ctype_digit($this->cantidad)) {
            $errores['cantidad'] = 'La cantidad debe ser un número entero mayor o igual a cero.';
        } elseif ((int) $this->cantidad > self::CANTIDAD_MAXIMA) {
            $errores['cantidad'] = 'La cantidad supera el valor máximo permitido.';
        }

        return $errores;
    }

    /**
     * Inserta el producto en la base de datos.
     *
     * @return int Id del producto creado
     */
    public function guardar(): int
    {
        $sql = 'INSERT INTO productos (codigo, nombre, descripcion, categoria, precio, cantidad)
                VALUES (:codigo, :nombre, :descripcion, :categoria, :precio, :cantidad)';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($this->parametros());

        $this->id = (int) $this->db->lastInsertId();
        return $this->id;
    }

    /**
     * Actualiza el producto existente identificado por su id.
     *
     * @return bool true si el producto existe (aunque no haya cambiado ningún dato)
     */
    public function modificar(): bool
    {
        if ($this->obtenerPorId((int) $this->id) === null) {
            return false;
        }

        $sql = 'UPDATE productos
                   SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
                       categoria = :categoria, precio = :precio, cantidad = :cantidad
                 WHERE id = :id';

        $parametros = $this->parametros();
        $parametros[':id'] = $this->id;

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($parametros);

        return true;
    }

    /**
     * Busca productos por código, nombre o categoría.
     * Si el término está vacío devuelve todos los productos.
     *
     * @param string $termino Texto a buscar
     * @return array<int, array> Productos encontrados
     */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return $this->listar();
        }

        // Se escapan los comodines de LIKE para buscar el texto literal
        $patron = '%' . addcslashes($termino, '%_\\') . '%';

        $sql = 'SELECT ' . $this->columnas() . '
                  FROM productos
                 WHERE codigo LIKE :t1 OR nombre LIKE :t2 OR categoria LIKE :t3
                 ORDER BY nombre';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute([':t1' => $patron, ':t2' => $patron, ':t3' => $patron]);

        return $sentencia->fetchAll();
    }

    /**
     * Devuelve todos los productos ordenados por nombre.
     *
     * @return array<int, array>
     */
    public function listar(): array
    {
        $sentencia = $this->db->prepare('SELECT ' . $this->columnas() . ' FROM productos ORDER BY nombre');
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * Obtiene un producto por su id.
     *
     * @return array|null Datos del producto o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        $sentencia = $this->db->prepare('SELECT ' . $this->columnas() . ' FROM productos WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        $producto = $sentencia->fetch();
        return $producto === false ? null : $producto;
    }

    /**
     * Indica si ya existe un producto con el código indicado.
     *
     * @param string   $codigo    Código a comprobar
     * @param int|null $excluirId Id que se ignora (el propio producto al modificar)
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM productos WHERE codigo = :codigo';
        $parametros = [':codigo' => $codigo];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $excluirId;
        }

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    /**
     * Parámetros comunes para INSERT y UPDATE.
     */
    private function parametros(): array
    {
        return [
            ':codigo'      => $this->codigo,
            ':nombre'      => $this->nombre,
            ':descripcion' => $this->descripcion,
            ':categoria'   => $this->categoria,
            ':precio'      => $this->precio,
            ':cantidad'    => (int) $this->cantidad,
        ];
    }

    /**
     * Columnas que se devuelven en las consultas.
     */
    private function columnas(): string
    {
        return 'id, codigo, nombre, descripcion, categoria, precio, cantidad, creado_en, actualizado_en';
    }
}
