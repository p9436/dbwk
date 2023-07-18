<?php

namespace App\Lib;
use \Validator;

/**
 * ModelBase Class
 *
 * This class serves as the base class for all models in your application. It provides common functionality and methods for interacting with the database.
 */
class ModelBase
{

  public $table_name;
  public $attributes;
  public $errors   = array();
  public $map_a_c  = array();
  public $map_c_a  = array();
  public $map_a_dv = array();

  public $belongs_to;

  public $has_many = array();
  public $nested_attributes_for = array();
  public $nested_attributes = array();

  public $attachments_for = array();
  public $attachments = array();
  public $attachments_uploads = [];

  public $related_objects = [];

  /**
   * Get the validation errors for the model.
   *
   * @return array
   */
  public function errors()
  {
    return $this->errors;
  }

  /**
   * Get the validation rules for the model attributes.
   *
   * @return array
   */
  public function validators()
  {
    return [];
  }

  /**
   * Validate the model attributes.
   *
   * @return bool
   */
  public function validate()
  {
    foreach ($this->validators() as $key => $rule) {
      $value = (isset($this->attributes[$key]) ? $this->attributes[$key] : '' );
      $result = Validator::Validate($value, $rule);
      if ($result !== true)
        $this->errors[$key] = $result;
    }

    if (\method_exists($this, 'custom_validations'))
      $this->custom_validations();

    return (empty($this->errors) ? true : false);
  }

  /**
   * Set a specific attribute value.
   *
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function set($key, $value) {
    $this->assignAttr($key, $value);
  }

  /**
   * Define the attribute mapping for the model.
   *
   * @param string $attr_name
   * @param string $column_name
   * @param mixed $default_value
   * @return void
   */
  protected function defAttr($attr_name, $column_name, $default_value = null)
  {
    $this->map_a_c[$attr_name]   = $column_name;
    $this->map_c_a[$column_name] = $attr_name;
    $this->map_a_dv[$attr_name]  = $default_value;
    $this->$attr_name = $default_value;
  }

  /**
   * Assign multiple attributes to the model.
   *
   * @param array $attrs
   * @return void
   */
  public function assignAttrs(array $attrs)
  {
    // Nested attributes
    foreach($this->nested_attributes_for as $attr_key => $nested_model_name) {
      $attr_name = "{$attr_key}_attributes";
      if (isset($attrs[$attr_name])) {
        $this->nested_attributes[$attr_key] = $attrs[$attr_name];
        unset($attrs[$attr_name]);
      }
    }

    // Attachment attributes
    foreach($this->attachments_for as $attr_key => $attachment_model_name) {
      $attr_name = "{$attr_key}_attributes";
      if (isset($attrs[$attr_name])) {
        $this->attachments[$attr_key] = $attrs[$attr_name];
        unset($attrs[$attr_name]);
      }
    }

    foreach ($attrs as $attr_name => $value) {
      $this->assignAttr($attr_name, $value);
    }
  }

  /**
   * Assign a specific attribute to the model.
   *
   * @param string $attr_name
   * @param mixed $value
   * @return bool
   */
  public function assignAttr($attr_name, $value)
  {
    if (isset($this->map_a_c[$attr_name]) || isset($this->map_c_a[$attr_name]))
    {
      if (!isset($this->map_a_c[$attr_name]))
        $attr_name = $this->map_c_a[$attr_name];
      $this->attributes[$attr_name] = $value;
      $this->$attr_name = $value;
    }
    return false;
  }

  /**
   * Get the value of a specific attribute.
   *
   * @param string $attr_name
   * @param mixed $default_value
   * @return mixed
   */
  protected function getAttr($attr_name, $default_value = null)
  {
    return (isset($this->attributes[$attr_name]) ? $this->attributes[$attr_name] : $default_value);
  }

  /**
   * Get the value of an attribute by its column name.
   *
   * @param string $column_name
   * @param mixed $default_value
   * @return mixed
   */
  protected function getAttrByColumnName($column_name, $default_value = null)
  {
    return (isset($this->attributes[$column_name]) ? $this->attributes[$column_name] : $default_value);
  }

  /**
   * Generate the SQL SET clause for INSERT and UPDATE queries.
   *
   * @param array $params
   * @return string
   */
  protected function attrsToInsert($params)
  {
    $result = array();
    foreach ($params as $key => $value) {
      $result[] = "`{$this->map_a_c[$key]}` = :$key";
    }
    return implode($result, ', ');
  }


  // Basic database routines
  //
  //

  // STATIC

  public static $page = 1;
  public static $per_page = 10;

  /**
   * Find a model by its ID.
   *
   * @param mixed $id
   * @return Model|null|array
   */
  public static function find($id) {
    $dbwk = new DBWK((new static()));
    $dbwk->find_by(['id' => $id]);
    return $dbwk->result;
  }


  public function append_files($params)
  {
    foreach ($this->attachments_for as $a_key => $a_class) {
      $attr_name = "{$a_key}_attributes";
      $this->attachments_uploads[$a_key] = $params[$attr_name];
    }
  }


  /**
   * Save the model to the database.
   *
   * @return $this
   */
  public function save()
  {
    $dbwk = new DBWK(new static);
    $dbwk->begin();

    $this->_save();
    if (empty($this->errors))
      $dbwk->commit();
    else
      $dbwk->rollback();

    return $this;
  }


  protected function _save()
  {
    $dbwk = new DBWK($this);
    $params = $this->attributes;

    if (false == $this->validate($params))
      return $this;

    try {

      if ($this->id)
        $dbwk->where(['id' => $this->id])->update()->exec();
      else
        $dbwk->insert();

      foreach ($this->nested_attributes_for as $n_attrs_key => $n_attrs_class) {
        if (!isset($this->nested_attributes[$n_attrs_key]))
          continue;

        foreach ($this->nested_attributes[$n_attrs_key] as $nested_attributes) {
          $nested_attributes[$this->foreign_key()] = $this->id;

          $nested_obj = new $n_attrs_class($nested_attributes);
          if ($nested_attributes['_delete'] == '1') {
            $nested_obj->delete();
            continue;
          }

          $nested_obj->_save();

          if (!empty($nested_obj->errors))
          {
            $this->id = null;
            $this->errors["{$n_attrs_key}_attributes"] = $nested_obj->errors;
          }
        }
      }


      if (!empty($this->errors))
        throw new \Exception("Error", 1);

      $this->attachments_delete();

      $this->attachments_save();

      if (!empty($this->errors))
        throw new \Exception("Error", 1);

      return $this;
    }
    catch(\Exception $e)
    {

    }

    return $this;
  }

  /**
   * Delete the model from the database.
   *
   * @return $this
   */
  public function delete()
  {
    foreach ($this->attachments_for as $cls) {
      $dir = (new $cls(['content_id' => $this->id]))->directory();
      if (is_dir($dir))
        rrmdir($dir);
    }

    $dbwk = new DBWK($this);
    $dbwk->delete()->where(['id' => $this->id])->limit(1)->exec();
    return $this;
  }

  /**
   * Get the foreign key for related models.
   *
   * @param object|null $obj
   * @return string
   * @throws \ReflectionException
   */
  protected function foreign_key($obj = null)
  {
    if ($obj === null)
      $obj = $this;
    return strtolower((new \ReflectionClass($obj))->getShortName()) . '_id';
  }

  /**
   * Delete attachments associated with the model.
   *
   * @return void
   */
  protected function attachments_delete()
  {
    foreach ($this->attachments_for as $n_attrs_key => $n_attrs_class) {
      if (!isset($this->attachments[$n_attrs_key])) continue;

      foreach ($this->attachments[$n_attrs_key] as $attachment_attributes) {
        if (!isset($attachment_attributes['_delete'])) continue;

        $attachment_attributes[$this->foreign_key()] = $this->id;

        $obj = new $n_attrs_class($attachment_attributes);
        $obj->delete();

        if ($obj->errors) $this->errors["{$n_attrs_key}_attributes"] = $obj->errors;
      }
    }
  }

  /**
   * Save attachments associated with the model.
   *
   * @return void
   */
  protected function attachments_save()
  {
    foreach ($this->attachments_for as $n_attrs_key => $n_attrs_class) {
      if (!isset($this->attachments_uploads[$n_attrs_key])) continue;

      foreach ($this->attachments_uploads[$n_attrs_key] as $upl_file) {
        if ($upl_file->file) {
          $obj = new $n_attrs_class([$this->foreign_key() => $this->id]);
          $obj->upload($upl_file);
          if ($obj->errors) $this->errors["{$n_attrs_key}_attributes"] = $obj->errors;
        }
      }
    }
  }
}
