<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;
use PDOStatement;

/**
 * Minimal query builder for the base Model.
 *
 * Supports where (AND/OR), whereIn, whereNull, orderBy, limit/offset,
 * get/first/count/value/pluck, and update/delete on a filtered set.
 * Always parameterised.
 */
class QueryBuilder
{
    protected string $table;
    protected Model  $model;
    protected array  $wheres = [];
    protected array  $orderBy = [];
    protected array  $bindings = [];
    protected array  $search = []; // ['columns' => [...], 'term' => '']
    protected ?int   $limit = null;
    protected ?int   $offset = null;

    public function __construct(Model $model, string $table)
    {
        $this->model = $model;
        $this->table = $table;
    }

    public function where(string $column, $value, string $operator = '='): self
    {
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orWhere(string $column, $value, string $operator = '='): self
    {
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'OR'];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = ['column' => $column, 'operator' => 'IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['column' => $column, 'operator' => 'NULL', 'value' => null, 'boolean' => 'AND'];
        return $this;
    }

    /**
     * Add a cross-column, case-insensitive `%term%` search grouped inside
     * parentheses so it ANDs with the surrounding filters:
     *
     *   WHERE deleted_at IS NULL AND (name LIKE :s OR short_name LIKE :s) ...
     */
    public function search(array $columns, string $term): self
    {
        $columns = array_values(array_filter($columns));
        if ($columns === []) {
            return $this;
        }
        $this->search = ['columns' => $columns, 'term' => $term];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = ['column' => $column, 'direction' => strtoupper($direction)];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    /**
     * @return Model[] array of model instances (empty array when none).
     */
    public function get(): array
    {
        $stmt = Database::pdo()->prepare($this->toSql());
        $this->bindAll($stmt);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = [];
        $class = get_class($this->model);
        foreach ($rows as $row) {
            $model = new $class();
            $model->attributes = $row;
            $model->exists = true;
            $models[] = $model;
        }
        return $models;
    }

    public function first(): ?Model
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $stmt = Database::pdo()->prepare($this->toSqlCount());
        $this->bindAll($stmt);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function value(string $column)
    {
        $stmt = Database::pdo()->prepare($this->toSql(['first' => $column]));
        $this->bindAll($stmt);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function pluck(string $column): array
    {
        $plucked = [];
        foreach ($this->get() as $row) {
            $plucked[] = $row->{$column};
        }
        return $plucked;
    }

    public function update(array $values): int
    {
        $set = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $token = ':upd_' . str_replace('.', '_', $column);
            $set[] = '`' . $column . '` = ' . $token;
            $bindings[$token] = $value;
        }

        $sql = 'UPDATE `' . $this->table . '` SET ' . implode(', ', $set)
            . $this->buildWhereClause();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(array_merge($bindings, $this->bindings));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM `' . $this->table . '`' . $this->buildWhereClause();
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }
    private function toSql(array $select = []): string
    {
        $columns = isset($select['first']) ? '`' . $select['first'] . '`' : '*';
        return 'SELECT ' . $columns . ' FROM `' . $this->table . '`'
            . $this->buildWhereClause()
            . $this->buildOrderClause()
            . $this->buildLimitOffset();
    }

    private function toSqlCount(): string
    {
        return 'SELECT COUNT(*) FROM `' . $this->table . '`' . $this->buildWhereClause();
    }

    private function buildWhereClause(): string
    {
        $clauses = [];
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : ($where['boolean'] === 'OR' ? 'OR' : 'AND');
            $token = ':w_' . $i;

            switch ($where['operator']) {
                case 'IN':
                    $placeholders = [];
                    foreach ((array) $where['value'] as $k => $v) {
                        $p = $token . '_' . $k;
                        $placeholders[] = $p;
                        $this->bindings[$p] = $v;
                    }
                    $clause = '`' . $where['column'] . '` IN (' . implode(',', $placeholders) . ')';
                    break;

                case 'NULL':
                    $clause = '`' . $where['column'] . '` IS NULL';
                    break;

                default:
                    $this->bindings[$token] = $where['value'];
                    $clause = '`' . $where['column'] . '` ' . $where['operator'] . ' ' . $token;
            }

            $clauses[] = $boolean === '' ? $clause : $boolean . ' ' . $clause;
        }

        // Cross-column search is added as a parenthesised OR group, so it ANDs
        // with any existing filters regardless of the boolean logic used above.
        if (isset($this->search['columns']) && $this->search['columns'] !== []) {
            $parts = [];
            foreach ($this->search['columns'] as $i => $col) {
                $token = ':s_' . $i;
                // Same %term% bound to every column; MySQL automatically handles
                // case-insensitive comparisons for most collations.
                $this->bindings[$token] = '%' . $this->search['term'] . '%';
                $parts[] = '`' . $col . '` LIKE ' . $token;
            }
            $group = '(' . implode(' OR ', $parts) . ')';
            // The where items carry their own AND/OR prefix; the search group
            // needs one explicitly so it joins cleanly when other clauses exist.
            $clauses[] = ($clauses === [] ? '' : 'AND ') . $group;
        }

        if ($clauses === []) {
            return '';
        }
        return ' WHERE ' . implode(' ', $clauses);
    }

    private function buildOrderClause(): string
    {
        if ($this->orderBy === []) {
            return '';
        }
        $parts = array_map(
            fn($o) => '`' . $o['column'] . '` ' . $o['direction'],
            $this->orderBy
        );
        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function buildLimitOffset(): string
    {
        $sql = '';
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    private function bindAll(PDOStatement $stmt): void
    {
        foreach ($this->bindings as $token => $value) {
            if ($value === null) {
                $stmt->bindValue($token, null, PDO::PARAM_NULL);
            } elseif (is_int($value) || is_bool($value)) {
                $stmt->bindValue($token, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($token, (string) $value, PDO::PARAM_STR);
            }
        }
    }
}
