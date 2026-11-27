<?php
/**
 * Clase DB
 * Administra una única conexión PDO a MySQL (patrón Singleton).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class DB
{
    /** @var PDO|null Instancia única de la conexión */
    private static ?PDO $conexion = null;

    /**
     * El constructor es privado para impedir crear instancias con "new".
     */
    private function __construct()
    {
    }

    /**
     * Devuelve la conexión PDO; la crea solo la primera vez que se solicita.
     *
     * @throws PDOException si no es posible conectarse
     */
    public static function conectar(): PDO
    {
        if (self::$conexion === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Los errores lanzan excepciones
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Resultados como arreglos asociativos
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Sentencias preparadas reales
            ];

            self::$conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        }

        return self::$conexion;
    }

    /**
     * Evita que la conexión se clone.
     */
    private function __clone()
    {
    }
}
