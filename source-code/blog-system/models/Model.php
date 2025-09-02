<?php
/**
 * Base Model Class
 * Klasa bazowa dla wszystkich modeli
 */

namespace App\Models;

use PDO;

abstract class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    protected $cache;
    protected $queryBuilder;
    protected $useCache = true;
    protected $cacheTtl = 3600;
    
    public function __construct()
    {
        $this->db = \App\Config\Database::getInstance()->getConnection();
        $this->cache = \App\Core\Cache::getInstance();
        $this->queryBuilder = new \App\Core\QueryBuilder($this->table);
    }
    
    /**
     * Znajdź rekord po ID
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Znajdź wszystkie rekordy
     */
    public function all()
    {
        $sql = "SELECT * FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Znajdź rekordy z warunkami
     */
    public function where($column, $value, $operator = '=', $orderBy = null, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Znajdź pierwszy rekord z warunkami
     */
    public function whereFirst($column, $value, $operator = '=')
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Utwórz nowy rekord
     */
    public function create($data)
    {
        $data = $this->filterFillable($data);
        $data = $this->applyCasts($data);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Zaktualizuj rekord
     */
    public function update($id, $data)
    {
        $data = $this->filterFillable($data);
        $data = $this->applyCasts($data);
        
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Usuń rekord
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Liczba rekordów
     */
    public function count($where = null, $params = [])
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Paginacja
     */
    public function paginate($page = 1, $perPage = 10, $where = null, $params = [])
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = $this->count($where, $params);
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }
    
    /**
     * Sortowanie
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$column} {$direction}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Limit wyników
     */
    public function limit($limit)
    {
        $sql = "SELECT * FROM {$this->table} LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Wyszukiwanie
     */
    public function search($columns, $query)
    {
        $searchConditions = [];
        $params = [];
        
        foreach ($columns as $column) {
            $searchConditions[] = "{$column} LIKE ?";
            $params[] = "%{$query}%";
        }
        
        $whereClause = implode(' OR ', $searchConditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Relacja belongs to
     */
    public function belongsTo($relatedModel, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: strtolower(class_basename($relatedModel)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        
        $relatedModelInstance = new $relatedModel();
        return $relatedModelInstance->find($this->$foreignKey);
    }
    
    /**
     * Relacja has many
     */
    public function hasMany($relatedModel, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: strtolower(class_basename($this)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        
        $relatedModelInstance = new $relatedModel();
        return $relatedModelInstance->where($foreignKey, $this->$localKey);
    }
    
    /**
     * Relacja has one
     */
    public function hasOne($relatedModel, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: strtolower(class_basename($this)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        
        $relatedModelInstance = new $relatedModel();
        return $relatedModelInstance->whereFirst($foreignKey, $this->$localKey);
    }
    
    /**
     * Relacja belongs to many
     */
    public function belongsToMany($relatedModel, $pivotTable = null, $foreignKey = null, $relatedKey = null)
    {
        $pivotTable = $pivotTable ?: $this->getPivotTableName($relatedModel);
        $foreignKey = $foreignKey ?: strtolower(class_basename($this)) . '_id';
        $relatedKey = $relatedKey ?: strtolower(class_basename($relatedModel)) . '_id';
        
        $sql = "SELECT r.* FROM {$pivotTable} p 
                JOIN {$relatedModel::getTable()} r ON p.{$relatedKey} = r.id 
                WHERE p.{$foreignKey} = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Filtrowanie fillable pól
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Ukrywanie pól
     */
    protected function hideFields($data)
    {
        if (empty($this->hidden)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->hidden));
    }
    
    /**
     * Aplikowanie castów
     */
    protected function applyCasts($data)
    {
        foreach ($this->casts as $field => $cast) {
            if (isset($data[$field])) {
                switch ($cast) {
                    case 'int':
                    case 'integer':
                        $data[$field] = (int) $data[$field];
                        break;
                    case 'float':
                    case 'double':
                        $data[$field] = (float) $data[$field];
                        break;
                    case 'bool':
                    case 'boolean':
                        $data[$field] = (bool) $data[$field];
                        break;
                    case 'json':
                        $data[$field] = json_encode($data[$field]);
                        break;
                    case 'date':
                        $data[$field] = date('Y-m-d', strtotime($data[$field]));
                        break;
                    case 'datetime':
                        $data[$field] = date('Y-m-d H:i:s', strtotime($data[$field]));
                        break;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Pobranie nazwy tabeli pivot
     */
    protected function getPivotTableName($relatedModel)
    {
        $tables = [strtolower(class_basename($this)), strtolower(class_basename($relatedModel))];
        sort($tables);
        return implode('_', $tables);
    }
    
    /**
     * Pobranie nazwy tabeli
     */
    public static function getTable()
    {
        $instance = new static();
        return $instance->table;
    }
    
    /**
     * Wykonanie surowego zapytania SQL
     */
    public function raw($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Wykonanie zapytania z cache
     */
    public function cachedRaw($key, $sql, $params = [], $ttl = null)
    {
        if (!$this->useCache) {
            return $this->raw($sql, $params);
        }
        
        $ttl = $ttl ?? $this->cacheTtl;
        
        return $this->cache->remember($key, $ttl, function() use ($sql, $params) {
            return $this->raw($sql, $params);
        });
    }
    
    /**
     * Pobranie z cache lub wykonanie callback
     */
    public function remember($key, $ttl, $callback)
    {
        if (!$this->useCache) {
            return $callback();
        }
        
        return $this->cache->remember($key, $ttl, $callback);
    }
    
    /**
     * Wyłączenie cache dla tego modelu
     */
    public function noCache()
    {
        $this->useCache = false;
        return $this;
    }
    
    /**
     * Włączenie cache dla tego modelu
     */
    public function withCache($ttl = null)
    {
        $this->useCache = true;
        if ($ttl !== null) {
            $this->cacheTtl = $ttl;
        }
        return $this;
    }
    
    /**
     * Pobranie z użyciem QueryBuilder
     */
    public function query()
    {
        return $this->queryBuilder->table($this->table);
    }
    
    /**
     * Eager loading relacji
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        $this->queryBuilder->with($relations);
        return $this;
    }
    
    /**
     * Pobranie z eager loading
     */
    public function getWith($relations)
    {
        return $this->with($relations)->get();
    }
    
    /**
     * Pobranie pierwszego z eager loading
     */
    public function firstWith($relations)
    {
        return $this->with($relations)->first();
    }
    
    /**
     * Transakcje
     */
    public function transaction($callback)
    {
        try {
            $this->db->beginTransaction();
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
