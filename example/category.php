<?php

/**
 * Table: Categories
 * 
 * Fields:
 * - ID
 * - Title
 * - Alias
 * - Description
 * 
 */

namespace App\Lib;
use \I18n;

class Category extends ModelBase
{
  public $table_name = 'Categories';
  public function __construct(array $attributes = []) {
    // Define models attributes and link to database fields 
    $this->defAttr('id',          'ID');
    $this->defAttr('title',       'Title');
    $this->defAttr('alias',       'Alias');
    $this->defAttr('description', 'Description');

    $this->assignAttrs($attributes);
  }

  public function validators()
  {
    return [
      'title'  => array('type' => 'string', 'required' => true, 'error_empty' => 'Category name is empty'),
      'alias'  => array('type' => 'string', 'required' => true, 'error_empty' => 'Category alias id empty'),
    ];
  }

  /**
   * Get all records
   */
  public function getAll()
  {
    $dbwk = new DBWK(new static());
    return $dbwk->fetchAll();
  }

  /**
   * Find record by alias
   */
  public static function findByAlias($alias)
  {
    $dbwk = new DBWK(new static());
    return $dbwk->find_by(['alias' => $alias]);
  }
}
