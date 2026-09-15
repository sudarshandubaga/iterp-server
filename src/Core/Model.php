<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;
use PDOStatement;

/**
 * Lightweight, framework-free ActiveRecord-style base model.
 *
 * Usage:
 *   User::query()->where('email', $email)->first();
 *   User::find(1);
 *   $user = new User(); $user->fill([...])->save();
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array  $fillable = [];
    public array     $attributes = [];
    public bool      $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        return new QueryBuilder($instance, $instance->table);
    }

    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $builder  = new QueryBuilder($instance, $instance->table);
        return $builder->where($instance->primaryKey, $id)->first();
    }

    public static function all(): array
    {
        return self::query()->get();
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function id(): int
    {
        return (int) ($this->attributes[$this->primaryKey] ?? 0);
    }

    public function save(): bool
    {
        $attributes = $this->attributes;

        if ($this->exists) {
            return $this->performUpdate($attributes);
        }
        return $this->performInsert($attributes);
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id"
        );
        $result = $stmt->execute(['id' => $this->attributes[$this->primaryKey]]);
        $this->exists = false;
        return $result;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toJson(): string
    {
        return json_encode($this->attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function performInsert(array $attributes): bool
    {
        $columns = array_keys($attributes);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $this->table . '` (`' . implode('`,`', $columns) . '`)
             VALUES (' . implode(',', $placeholders) . ')'
        );

        $result = $stmt->execute(self::buildBinds($attributes));

        if ($result) {
            $this->attributes[$this->primaryKey] = (int) $pdo->lastInsertId();
            $this->exists = true;
        }

        return $result;
    }

    private function performUpdate(array $attributes): bool
    {
        $columns = array_keys($attributes);
        $set = array_map(fn ($c) => '`' . $c . '` = :' . $c, $columns);

        [$hasTimestamps, $timestamp] = $this->hasUpdatedAt($columns);
        if ($hasTimestamps) {
            $set[] = '`updated_at` = NOW()';
        }

        $binds = self::buildBinds($attributes);
        $binds[':pk'] = $this->attributes[$this->primaryKey];

        $stmt = Database::pdo()->prepare(
            'UPDATE `' . $this->table . '`
             SET ' . implode(',', $set) . '
             WHERE `' . $this->primaryKey . '` = :pk'
        );

        return $stmt->execute($binds);
    }

    private function hasUpdatedAt(array $columns): array
    {
        return [in_array('updated_at', $columns, true), 'updated_at'];
    }

    private static function buildBinds(array $attributes): array
    {
        $binds = [];
        foreach ($attributes as $key => $value) {
            $binds[':' . $key] = $value === null ? null : (string) $value;
        }
        return $binds;
    }

    /**
     * Helper to hydrate an array of rows into model instances.
     */
    public static function hydrate(array $rows): array
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new static();
            $model->attributes = $row;
            $model->exists = true;
            $models[] = $model;
        }
        return $models;
    }
}