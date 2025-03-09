<?php
/**
 * Vegan Messenger Social Network
 * Base Model Class
 */

namespace VeganMessenger;

abstract class Model {
    /**
     * @var Database The database instance
     */
    protected $db;
    
    /**
     * @var string The table name
     */
    protected $table;
    
    /**
     * @var string The primary key column
     */
    protected $primaryKey = 'id';
    
    /**
     * @var array The model data
     */
    protected $data = [];
    
    /**
     * @var array The original data (used for detecting changes)
     */
    protected $originalData = [];
    
    /**
     * @var array Validation rules
     */
    protected $rules = [];
    
    /**
     * @var array Validation errors
     */
    protected $errors = [];
    
    /**
     * @var array Fields that can be mass assigned
     */
    protected $fillable = [];
    
    /**
     * @var array Fields that cannot be mass assigned
     */
    protected $guarded = ['id'];
    
    /**
     * @var bool Whether the model is a new record
     */
    protected $isNew = true;
    
    /**
     * Constructor
     * 
     * @param array $data The model data
     */
    public function __construct(array $data = []) {
        // Get database instance from app
        $this->db = $GLOBALS['app']->getDb();
        
        // Set initial data
        if (!empty($data)) {
            $this->fill($data);
            
            // If the primary key is set, this is not a new record
            if (isset($data[$this->primaryKey])) {
                $this->isNew = false;
                $this->originalData = $this->data;
            }
        }
    }
    
    /**
     * Fill the model with data
     * 
     * @param array $data The data to fill
     * @return Model This model instance
     */
    public function fill(array $data) {
        foreach ($data as $key => $value) {
            // Skip guarded fields
            if (\in_array($key, $this->guarded)) {
                continue;
            }
            
            // Skip non-fillable fields if fillable is specified
            if (!empty($this->fillable) && !\in_array($key, $this->fillable)) {
                continue;
            }
            
            $this->data[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Get all data
     * 
     * @return array The model data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get original data
     * 
     * @return array The original data
     */
    public function getOriginal() {
        return $this->originalData;
    }
    
    /**
     * Get the value of a field
     * 
     * @param string $key The field name
     * @param mixed $default The default value if not found
     * @return mixed The field value
     */
    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * Set the value of a field
     * 
     * @param string $key The field name
     * @param mixed $value The field value
     * @return Model This model instance
     */
    public function set($key, $value) {
        // Skip guarded fields
        if (\in_array($key, $this->guarded)) {
            return $this;
        }
        
        // Skip non-fillable fields if fillable is specified
        if (!empty($this->fillable) && !\in_array($key, $this->fillable)) {
            return $this;
        }
        
        $this->data[$key] = $value;
        
        return $this;
    }
    
    /**
     * Check if a field exists
     * 
     * @param string $key The field name
     * @return bool True if the field exists
     */
    public function has($key) {
        return isset($this->data[$key]);
    }
    
    /**
     * Get the primary key value
     * 
     * @return mixed The primary key value
     */
    public function getId() {
        return $this->get($this->primaryKey);
    }
    
    /**
     * Check if the model is a new record
     * 
     * @return bool True if the model is a new record
     */
    public function isNew() {
        return $this->isNew;
    }
    
    /**
     * Get the changed fields
     * 
     * @return array The changed fields
     */
    public function getChanges() {
        $changes = [];
        
        foreach ($this->data as $key => $value) {
            // Skip fields that haven't changed
            if (isset($this->originalData[$key]) && $this->originalData[$key] === $value) {
                continue;
            }
            
            $changes[$key] = $value;
        }
        
        return $changes;
    }
    
    /**
     * Check if the model has changed
     * 
     * @return bool True if the model has changed
     */
    public function hasChanged() {
        return !empty($this->getChanges());
    }
    
    /**
     * Validate the model
     * 
     * @return bool True if validation passed
     */
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->get($field);
            
            foreach ($fieldRules as $rule => $ruleValue) {
                $error = $this->applyValidationRule($field, $value, $rule, $ruleValue);
                
                if ($error) {
                    $this->errors[$field] = $error;
                    break; // Stop validating this field after first error
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply a validation rule to a field
     * 
     * @param string $field The field name
     * @param mixed $value The field value
     * @param string $rule The rule name
     * @param mixed $ruleValue The rule value
     * @return string|null Error message or null if validation passed
     */
    protected function applyValidationRule($field, $value, $rule, $ruleValue) {
        $fieldLabel = \str_replace('_', ' ', \ucfirst($field));
        
        switch ($rule) {
            case 'required':
                if ($ruleValue && ($value === null || $value === '')) {
                    return "$fieldLabel is required.";
                }
                break;
                
            case 'min_length':
                if (\strlen($value) < $ruleValue) {
                    return "$fieldLabel must be at least $ruleValue characters.";
                }
                break;
                
            case 'max_length':
                if (\strlen($value) > $ruleValue) {
                    return "$fieldLabel cannot exceed $ruleValue characters.";
                }
                break;
                
            case 'email':
                if ($ruleValue && $value && !\filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "$fieldLabel must be a valid email address.";
                }
                break;
                
            case 'numeric':
                if ($ruleValue && $value && !\is_numeric($value)) {
                    return "$fieldLabel must be a number.";
                }
                break;
                
            case 'unique':
                if ($value) {
                    // Check if a column with the same value exists
                    // But exclude the current record
                    $query = "SELECT COUNT(*) FROM {$this->table} WHERE $field = :value";
                    $params = ['value' => $value];
                    
                    if (!$this->isNew && $this->getId()) {
                        $query .= " AND {$this->primaryKey} != :id";
                        $params['id'] = $this->getId();
                    }
                    
                    $count = $this->db->queryValue($query, $params);
                    
                    if ($count > 0) {
                        return "$fieldLabel is already taken.";
                    }
                }
                break;
                
            case 'exists':
                if ($value) {
                    $tableName = \is_array($ruleValue) ? $ruleValue[0] : $this->table;
                    $column = \is_array($ruleValue) ? $ruleValue[1] : $field;
                    
                    $query = "SELECT COUNT(*) FROM $tableName WHERE $column = :value";
                    $count = $this->db->queryValue($query, ['value' => $value]);
                    
                    if ($count === 0) {
                        return "$fieldLabel does not exist.";
                    }
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Get validation errors
     * 
     * @return array The validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Save the model
     * 
     * @param bool $validate Whether to validate before saving
     * @return bool True if the model was saved
     */
    public function save($validate = true) {
        // Validate the model
        if ($validate && !$this->validate()) {
            return false;
        }
        
        // Insert or update based on whether this is a new record
        if ($this->isNew) {
            // Insert
            $id = $this->db->insert($this->table, $this->data);
            
            if (!$id) {
                return false;
            }
            
            // Set the ID
            $this->data[$this->primaryKey] = $id;
            $this->isNew = false;
        } else {
            // Only update changed fields
            $changes = $this->getChanges();
            
            // No changes, return success
            if (empty($changes)) {
                return true;
            }
            
            // Update
            $result = $this->db->update(
                $this->table,
                $changes,
                "{$this->primaryKey} = :id",
                ['id' => $this->getId()]
            );
            
            if (!$result) {
                return false;
            }
        }
        
        // Update original data
        $this->originalData = $this->data;
        
        return true;
    }
    
    /**
     * Delete the model
     * 
     * @return bool True if the model was deleted
     */
    public function delete() {
        // Can't delete a new record
        if ($this->isNew) {
            return false;
        }
        
        // Delete
        $result = $this->db->delete(
            $this->table,
            "{$this->primaryKey} = :id",
            ['id' => $this->getId()]
        );
        
        if (!$result) {
            return false;
        }
        
        // Reset model
        $this->data = [];
        $this->originalData = [];
        $this->isNew = true;
        
        return true;
    }
    
    /**
     * Refresh the model from the database
     * 
     * @return bool True if the model was refreshed
     */
    public function refresh() {
        // Can't refresh a new record
        if ($this->isNew) {
            return false;
        }
        
        // Get from database
        $data = $this->db->queryOne(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1",
            ['id' => $this->getId()]
        );
        
        if (!$data) {
            return false;
        }
        
        // Update data
        $this->data = $data;
        $this->originalData = $data;
        
        return true;
    }
    
    /**
     * Find a model by ID
     * 
     * @param mixed $id The ID to find
     * @return Model|null The model or null if not found
     */
    public static function find($id) {
        $model = new static();
        
        $data = $model->db->queryOne(
            "SELECT * FROM {$model->table} WHERE {$model->primaryKey} = :id LIMIT 1",
            ['id' => $id]
        );
        
        if (!$data) {
            return null;
        }
        
        return new static($data);
    }
    
    /**
     * Find a model by a field value
     * 
     * @param string $field The field to search by
     * @param mixed $value The value to search for
     * @return Model|null The model or null if not found
     */
    public static function findBy($field, $value) {
        $model = new static();
        
        $data = $model->db->queryOne(
            "SELECT * FROM {$model->table} WHERE $field = :value LIMIT 1",
            ['value' => $value]
        );
        
        if (!$data) {
            return null;
        }
        
        return new static($data);
    }
    
    /**
     * Find all models
     * 
     * @param string $orderBy The field to order by
     * @param string $direction The order direction (ASC or DESC)
     * @return array The models
     */
    public static function all($orderBy = null, $direction = 'ASC') {
        $model = new static();
        
        $query = "SELECT * FROM {$model->table}";
        
        if ($orderBy) {
            $query .= " ORDER BY $orderBy $direction";
        }
        
        $data = $model->db->query($query);
        
        $models = [];
        
        foreach ($data as $row) {
            $models[] = new static($row);
        }
        
        return $models;
    }
    
    /**
     * Find models by a field value
     * 
     * @param string $field The field to search by
     * @param mixed $value The value to search for
     * @param string $orderBy The field to order by
     * @param string $direction The order direction (ASC or DESC)
     * @return array The models
     */
    public static function findAllBy($field, $value, $orderBy = null, $direction = 'ASC') {
        $model = new static();
        
        $query = "SELECT * FROM {$model->table} WHERE $field = :value";
        
        if ($orderBy) {
            $query .= " ORDER BY $orderBy $direction";
        }
        
        $data = $model->db->query($query, ['value' => $value]);
        
        $models = [];
        
        foreach ($data as $row) {
            $models[] = new static($row);
        }
        
        return $models;
    }
    
    /**
     * Find models by a custom query
     * 
     * @param string $query The query to execute
     * @param array $params The query parameters
     * @return array The models
     */
    public static function findByQuery($query, array $params = []) {
        $model = new static();
        
        $data = $model->db->query($query, $params);
        
        $models = [];
        
        foreach ($data as $row) {
            $models[] = new static($row);
        }
        
        return $models;
    }
    
    /**
     * Count all models
     * 
     * @return int The count
     */
    public static function count() {
        $model = new static();
        
        return (int)$model->db->queryValue("SELECT COUNT(*) FROM {$model->table}");
    }
    
    /**
     * Count models by a field value
     * 
     * @param string $field The field to search by
     * @param mixed $value The value to search for
     * @return int The count
     */
    public static function countBy($field, $value) {
        $model = new static();
        
        return (int)$model->db->queryValue(
            "SELECT COUNT(*) FROM {$model->table} WHERE $field = :value",
            ['value' => $value]
        );
    }
    
    /**
     * Check if a model exists with the given field value
     * 
     * @param string $field The field to search by
     * @param mixed $value The value to search for
     * @return bool True if the model exists
     */
    public static function exists($field, $value) {
        $model = new static();
        
        $count = (int)$model->db->queryValue(
            "SELECT COUNT(*) FROM {$model->table} WHERE $field = :value LIMIT 1",
            ['value' => $value]
        );
        
        return $count > 0;
    }
} 