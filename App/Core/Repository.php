<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Repository — Base class for all data-access repositories.
 */
abstract class Repository
{
    /**
     * The database table this repository manages.
     * Declared in each concrete repository.
     *
     * Example: protected string $table = 'courses';
     */
    protected string $table;
    protected string $entityClass = '';
    protected PDO $db;


    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    // -------------------------------------------------------------------------
    // Core CRUD Operations
    // -------------------------------------------------------------------------

    /**
     * Find a single record by its primary key.
     * Returns an Entity object (or associative array) if found, null otherwise.
     */
    public function find(int $id): mixed
    {
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Retrieve all records from the table.
     * Returns an array of Entity objects (or associative arrays).
     *
     * Usage:
     *   $courses = $this->courseRepository->findAll();
     *
     * Caution: do not call findAll() on large tables without a LIMIT.
     * Use paginate() instead.
     */
    public function findAll(): array
    {
        $stmt = $this->query("SELECT * FROM {$this->table}");
        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find all records matching a set of column = value conditions (AND logic).
     *
     * Usage:
     *   $courses = $this->courseRepository->findWhere(['status' => 'published', 'category_id' => 3]);
     *
     * @param array<string, mixed> $conditions Column → value pairs
     * @return array Entity objects or associative arrays
     */
    public function findWhere(array $conditions): array
    {
        [$whereClause, $params] = $this->buildWhereClause($conditions);
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE {$whereClause}", $params);
        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Find a single record matching conditions.
     * Returns null if not found.
     *
     * Usage:
     *   $user = $this->userRepository->findOneWhere(['email' => $email]);
     *
     * @param array<string, mixed> $conditions
     */
    public function findOneWhere(array $conditions): mixed
    {
        [$whereClause, $params] = $this->buildWhereClause($conditions);
        $stmt = $this->query(
            "SELECT * FROM {$this->table} WHERE {$whereClause} LIMIT 1",
            $params
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }


    /**'
     * Insert a new record and return the new row's ID.
     *
     * Usage:
     *   $newId = $this->courseRepository->create([
     *       'title'         => 'Intro to PHP',
     *       'instructor_id' => 1,
     *       'status'        => 'draft',
     *   ]);
     *
     * @param array<string, mixed> $data Column → value pairs
     * @return int The auto-incremented ID of the new record
     */
    public function create(array $data): int
    {
        $columns     = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $this->query(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})",
            $data
        );

        return (int) $this->db->lastInsertId();
    }


    /**
     * Update an existing record by its ID.
     * Only the columns present in $data are updated (partial update).
     *
     * Usage:
     *   $this->courseRepository->update(5, ['title' => 'Updated Title', 'status' => 'published']);
     *
     * @param int                  $id   The record's primary key
     * @param array<string, mixed> $data Columns to update
     */
    public function update(int $id, array $data): void
    {
        $setClauses = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['id'] = $id;

        $this->query(
            "UPDATE {$this->table} SET {$setClauses} WHERE id = :id",
            $data
        );
    }


    /**
     * Delete a record by its primary key.
     *
     * Usage:
     *   $this->courseRepository->delete(5);
     */
    public function delete(int $id): void
    {
        $this->query("DELETE FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }


    /**
     * Check whether a record with the given ID exists.
     *
     * Usage:
     *   if (!$this->courseRepository->exists($id)) $this->abort404();
     */
    public function exists(int $id): bool
    {
        $stmt = $this->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
        return (int) $stmt->fetchColumn() > 0;
    }


    /**
     * Count all records in the table, with optional conditions.
     *
     * Usage:
     *   $total          = $this->courseRepository->count();
     *   $publishedCount = $this->courseRepository->count(['status' => 'published']);
     *
     * @param array<string, mixed> $conditions Optional WHERE conditions
     */
    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $stmt = $this->query("SELECT COUNT(*) FROM {$this->table}");
        } else {
            [$whereClause, $params] = $this->buildWhereClause($conditions);
            $stmt = $this->query("SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}", $params);
        }

        return (int) $stmt->fetchColumn();
    }


    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    /**
     * Fetch a page of records with total count metadata.
     *
     * Returns an array with two keys:
     *   'data'  → array of Entity objects for the current page
     *   'meta'  → pagination metadata (total, perPage, currentPage, lastPage)
     *
     * Usage in a controller:
     *   $result   = $this->courseRepository->paginate(page: 2, perPage: 20);
     *   $courses  = $result['data'];
     *   $meta     = $result['meta'];
     *   // $meta['lastPage'] tells the view how many page links to render
     *
     * @param int                  $page       The current page number (1-indexed)
     * @param int                  $perPage    Records per page
     * @param array<string, mixed> $conditions Optional WHERE conditions
     *
     * @return array{data: array, meta: array{total: int, perPage: int, currentPage: int, lastPage: int}}
     */
    public function paginate(int $page = 1, int $perPage = 15, array $conditions = []): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $total  = $this->count($conditions);

        if (empty($conditions)) {
            $stmt = $this->query(
                "SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset",
                ['limit' => $perPage, 'offset' => $offset]
            );
        } else {
            [$whereClause, $params] = $this->buildWhereClause($conditions);
            $params['limit']        = $perPage;
            $params['offset']       = $offset;
            $stmt = $this->query(
                "SELECT * FROM {$this->table} WHERE {$whereClause} LIMIT :limit OFFSET :offset",
                $params
            );
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => array_map([$this, 'hydrate'], $rows),
            'meta' => [
                'total'       => $total,
                'perPage'     => $perPage,
                'currentPage' => $page,
                'lastPage'    => (int) ceil($total / $perPage),
            ],
        ];
    }


    // -------------------------------------------------------------------------
    // Raw Query — escape hatch for complex SQL
    // -------------------------------------------------------------------------

    /**
     * Execute a raw parameterised SQL query.
     *
     * @param string               $sql    The SQL statement with :named placeholders
     * @param array<string, mixed> $params The parameter values
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            // Support both ':key' and 'key' style parameter names
            $stmt->bindValue(str_starts_with($key, ':') ? $key : ":{$key}", $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }


    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a WHERE clause string and its parameter array from a conditions map.
     *
     * ['status' => 'published', 'category_id' => 3]
     * becomes:
     *   "status = :status AND category_id = :category_id"
     *   ['status' => 'published', 'category_id' => 3]
     *
     * @param array<string, mixed> $conditions
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhereClause(array $conditions): array
    {
        $clauses = array_map(fn($col) => "{$col} = :{$col}", array_keys($conditions));
        return [implode(' AND ', $clauses), $conditions];
    }


    /**
     * Hydrate a raw database row into the repository's Entity class.
     *
     * If $entityClass is set, a new Entity is instantiated and its properties
     * are populated from the row data. Otherwise, the raw array is returned.
     *
     * This keeps raw associative arrays out of the rest of the application —
     * controllers and services work with typed objects, not raw DB rows.
     *
     * @param array<string, mixed> $row Raw row from PDO::FETCH_ASSOC
     * @return object|array Entity object or raw array if no entityClass is set
     */
    private function hydrate(array $row): mixed
    {
        if (empty($this->entityClass)) {
            return $row;
        }

        $entity = new $this->entityClass();

        foreach ($row as $column => $value) {
            // Convert snake_case column names to camelCase property names:
            // 'instructor_id' → 'instructorId'
            $property = lcfirst(str_replace('_', '', ucwords($column, '_')));

            if (property_exists($entity, $property)) {
                $entity->$property = $value;
            } elseif (property_exists($entity, $column)) {
                // Fallback: try the original column name as-is
                $entity->$column = $value;
            }
        }

        return $entity;
    }
}