<?php
/**
 * Simple DB framework
 */

namespace App\Lib;


use Monolog\Logger;

class DBWK
{
  public static $pdo;

  public static $logger;

  public $model_class;
  public $table_name;

  public $model_object;
  public $sql_params = [];
  public $sql_chunks = [];
  public $pagination = [];
  public $result     = [];
  public $ids        = [];

  protected $eager_loading = [];

  public function __construct($model_object)
  {
    $this->model_object = $model_object;
    $this->model_class  = get_class($model_object);
    $this->table_name   = $this->table_name();
  }


  public function table_name()
  {
    return $this->model_object->table_name;
  }


  /**
   * Generates SQL query based on the chunks
   * 
   * @return string
   */
  public function sql()
  {
    $result = [];
    if (isset($this->sql_chunks[':action'])) $result[] = $this->sql_chunks[':action'];
    if (isset($this->sql_chunks[':joins'])) $result[] = $this->sql_chunks[':joins'];
    if (isset($this->sql_chunks[':set'])) $result[] = $this->sql_chunks[':set'];
    if (isset($this->sql_chunks[':where'])) $result[] = $this->sql_chunks[':where'];
    if (isset($this->sql_chunks[':order'])) $result[] = $this->sql_chunks[':order'];
    if (isset($this->sql_chunks[':limit'])) $result[] = $this->sql_chunks[':limit'];
    if (isset($this->sql_chunks[':offset'])) $result[] = $this->sql_chunks[':offset'];

    // print_r($this->sql_params);

    return implode(' ', $result);
  }


  /**
   * Constructs SELECT SQL query
   *
   * @example
   * $dbwk->select()
   * $dbwk->select(['id', ':name', ':created_at'])
   *
   * @param string $select
   * @return $this
   */
  public function select($select = '*')
  {
    $this->sql_chunks[':action'] = "SELECT SQL_CALC_FOUND_ROWS $select FROM $this->table_name";
    return $this;
  }


  /**
   * Adds LIMIT clause to the query
   * 
   * @example
   * $dbwk->select()->limit(100);
   *
   * @param int $limit
   * @return $this
   */
  public function limit($limit)
  {
    $this->sql_chunks[':limit'] = "LIMIT " . (int) $limit;
    return $this;
  }


  /**
   * Adds OFFSET clause to the query
   * 
   * @example
   * $dbwk->select()->limit(10)->offset(50)
   *
   * @param $offset
   * @return $this
   */
  public function offset($offset)
  {
    $this->sql_chunks[':offset'] = "OFFSET " . (int) $offset;
    return $this;
  }


  /**
   * Adds LIMIT and OFFSET clauses for pagination
   *
   * @example
   * $dbwk->select()->paginate(1, 5) // first page, 5 records per page
   *
   * @param int $page
   * @param int $per_page
   * @return $this
   */
  public function paginate($page = 1, $per_page = 10)
  {
    if ((int) $page < 1) $page = 1;

    $this->limit($per_page);
    $this->offset(((int) $page - 1) * (int) $per_page);

    $this->pagination = [
      'page'          => $page,
      'per_page'      => $per_page,
    ];
    return $this;
  }


  /**
   * Adds JOIN clauses to the query
   * 
   * @example
   * $dbwk = new DBWK(new Article());
   * $dbwk->select('*, articles.id')
   *      ->joins('LEFT JOIN users ON users.id = article.author_id');
   * 
   * @param $joins
   * @return $this
   */
  public function joins($joins)
  {
    $this->sql_chunks[':joins'] = $joins;
    return $this;
  }

  /**
   * Retrieves the count of total records
   *
   * @return integer
   */
  public function records_count()
  {
    $stmt = self::$pdo->prepare("SELECT FOUND_ROWS()");
    $stmt->execute();
    return $stmt->fetchColumn();
  }

  /**
   * Eager loading for related models
   *
   * @example
   * $dbwk->select()->where(['name' => 'Tank'])->with('weapons')
   *
   * @param array $relations
   * @return $this
   */
  public function with(array $relations)
  {
    $this->eager_loading = $relations;
    return $this;
  }

  /**
   * Handle WHERE conditions
   *
   * @example
   * $dbwk->select()->where(['name' => 'Flash'])
   * $dbwk->select()->where(['category_id' => array(1,3,8)])  # -> WHERE category_id IN (:c_0, :c_1, :c_2)
   * $dbwk->select()->where(['id[>]' => 10000])    # -> WHERE id > :id
   * $dbwk->select()->where(['category_id' => 1, 'visible' => true, 'published_at[<]' => time(), 'text_short[<>]' => ''])
   * $dbwk->select()->where('(id > :id OR name = :name) OR category IN :category_id', ['id' => 10, 'name' => 'Supes', 'category_id' => [2,5] ])
   *
   * @return $this
   */
  public function where()
  {
    if (func_num_args() == 2) {
      if (is_string(func_get_arg(0)) && is_array(func_get_arg(1))) {
        $this->where_string(func_get_arg(1), func_get_arg(0));
      }
    }

    if (func_num_args() == 1 && is_array(func_get_arg(0)) && !empty(func_get_arg(0))) {
      $this->where_array(func_get_arg(0));
    }

    return $this;
  }



  /**
   * Set the ORDER conditions
   *
   * @example
   * $dbwk->select()->order([':name' => 'ASC', 'id' => 'DESC']) // ... ORDER BY name ASC, id DESC
   * 
   *
   * @param array $order The ORDER conditions
   * 
   * @return $this
   */
  public function order($order)
  {
    $order_params = [];
    foreach ($order as $key => $direction) {
      $order_params[] = "`{$this->model_object->map_a_c[$key]}` $direction";
    }
    $this->sql_chunks[':order'] = "ORDER BY ".implode(', ', $order_params);
    return $this;
  }


  /**
   * Perform the INSERT query
   *
   * @return Model
   */
  public function insert()
  {
    $this->model_object->assignAttrs(['created_at' => time(), 'updated_at' => time()]);
    $this->sql_chunks[':action'] = "INSERT INTO ".$this->table_name();
    $this->sql_chunks[':set'] = $this->sql_set();

    $this->exec();
    $this->model_object->set('id', $this->last_insert_id());

    return $this->model_object;
  }

  /**
   * Perform the UPDATE query
   *
   * @return $this
   */
  public function update()
  {
    $this->model_object->assignAttr('updated_at', time());
    $this->sql_chunks[':action'] = "UPDATE ".$this->table_name();
    $this->sql_chunks[':set'] = $this->sql_set();
    return $this;
  }

  /**
   * Perform the DELETE query
   *
   * @example
   * $dbwk->where(['id' => 100])->delete();
   *
   * @return $this
   */
  public function delete()
  {
    $this->sql_chunks[':action'] = "DELETE FROM ".$this->table_name();
    return $this;
  }

  /**
   * Find a single record by the conditions
   *
   * @example
   * $dbwk->find_by(['id' => 23])
   *
   * @param array $params The find conditions
   * @return Model|null
   */
  public function find_by($params)
  {
    return $this->select()->where($params)->limit(1)->get();
  }


  /**
   * Execute query and return model or collection
   *
   * @return $this
   */
  public function fetch_all()
  {
    // Execute the query and fetch all records
    $stmt = $this->exec();
    foreach ($stmt->fetchAll() as $attrs) {
      $obj = new $this->model_class($attrs);
      $this->result[] = $obj;
      $this->ids[] = $obj->id;
    }

    if (isset($this->pagination['per_page'])) {
      // Calculate pagination information
      $records_count = $this->records_count();
      $pages_count = ceil($records_count / $this->pagination['per_page']);

      $this->pagination = array_merge($this->pagination, [
        'records_count' => $records_count,
        'pages_count'   => ( $pages_count == 0 ? 1 : $pages_count )
      ]);
    }

    $this->eager_load();

    return $this;
  }


  /**
   * Get a single record from the query
   * 
   * @return Model|array|null The single model object, array, or null
   */
  public function get()
  {
    $stmt = $this->exec();

    $raw_data = $stmt->fetch();
    if ($raw_data)
      $this->result = new $this->model_class($raw_data);
    else
      $this->result = null;

    return $this->result;
  }


  /**
   * Execute sql query
   * 
   * @return mixed The executed statement
   */
  public function exec()
  {
    $sql = $this->sql();
    self::$logger->debug("\e[32m$sql\e[0m");
    self::$logger->debug("\e[37mparams: " . preg_replace('/\s+/', ' ', print_r($this->sql_params,true)) . "\e[0m");
    $stmt = self::$pdo->prepare($sql);
    $stmt->execute($this->sql_params);
    return $stmt;
  }


  /**
   * Get the last insert ID
   *
   * @return integer
   */
  public function last_insert_id()
  {
    return self::$pdo->lastInsertId();
  }


  /**
   * Begin a transaction
   */
  public function begin()
  {
    self::$pdo->beginTransaction();
  }


  /**
   * Commit a transaction
   */
  public function commit()
  {
    self::$pdo->commit();
  }


  /**
   * Rollback a transaction
   */
  public function rollback()
  {
    self::$pdo->rollBack();
  }


  /**
   * Helper method to construct the SQL IN clause
   * 
   * @param string $key The parameter key
   * @param array $value The parameter value
   * @return string The SQL IN clause
   */
  protected function sql_in($key, $value)
  {
    $ins = [];
    foreach ($value as $k => $v){
      $ins[] = ":{$key}_$k";
      $this->sql_params["{$key}_$k"] = $v;
    }
    return "(".implode(', ', $ins).")";
  }


  /**
   * Helper method to generate the SQL SET clause
   *
   * @return string
   */
  protected function sql_set()
  {
    $set = array();

    foreach ($this->model_object->map_a_c as $key => $column) {
      if (isset($this->model_object->attributes[$key])) {
        $set[] = "`$column` = :$key";
        $this->sql_params[$key] = $this->model_object->attributes[$key];
      }
    }
    return "SET ".implode($set, ', ');
  }


  /**
   * Helper method to construct the WHERE clauses from an array
   *
   * @param array $params
   * @param string $conditions
   */
  private function where_string(array $params, $conditions)
  {
    foreach ($this->model_object->map_a_c as $attr_name => $column_name) {
      $conditions = preg_replace("/(^|\(|\s)$attr_name\b/", "$1`$column_name`", $conditions);
    }

    foreach ($params as $key => $value) {
      if (is_array($value)) {
        $conditions = str_replace(":$key", $this->sql_in($key, $value), $conditions);
      } else {
        $this->sql_params[$key] = $value;
      }
    }

    $this->sql_chunks[':where'] = "WHERE " . $conditions;
  }


  /**
   * Helper method to construct the WHERE clauses from an array
   *
   * @param array $params
   */
  private function where_array(array $params)
  {
    $conditions = [];
    foreach ($params as $key => $value) {
      $operator = "="; //by default

      if (preg_match("/(.+)\[(.+)\]$/", $key, $matches)) {
        $key = $matches[1];
        $operator = $matches[2];
      }

      $sql_column = "`{$this->model_object->map_a_c[$key]}`";

      if (is_array($value)) {
        $conditions[] = $sql_column.' IN '.$this->sql_in($key, $value);
      }
      else
      {
        $this->sql_params[$key] = $value;
        $conditions[] = "$sql_column $operator :$key";
      }
    }
    $this->sql_chunks[':where'] = "WHERE " . implode(' AND ', $conditions);
  }


  /**
   * Eager loading of related models
   *
   * @return bool
   */
  private function eager_load()
  {
    if (empty($this->ids))
      return false;

    try {
      $foreign_key = strtolower((new \ReflectionClass($this->model_object))->getShortName()) . '_id';
      foreach ($this->eager_loading as $relation) {
        if ($this->model_object->has_many[$relation]) {

          $dbwk = new static(new $this->model_object->has_many[$relation]);
          $related_objects = $dbwk->select()->where([$foreign_key => $this->ids])->fetch_all()->result;

          foreach ($this->result as $k => $result_object) {
            $this->result[$k]->related_objects[$relation] = [];

            foreach ($related_objects as $related_object) {
              if ($result_object->id == $related_object->$foreign_key) {
                $this->result[$k]->related_objects[$relation][] = $related_object;
              }
            }
          }
        }
      }
    } catch (\ReflectionException $e) {

    }
    return true;
  }
}