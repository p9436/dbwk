<?php
/**
 * Table: ArticleContent
 * 
 * Attributes
 * - ID        integer
 * - Alias     string
 * - ArticleID integer
 * - Lang      string
 * - Title     string
 * - FullText  text
 * - Keywords
 *
 */

namespace App\Models;
use \Validator;

class ArticleContent extends ModelBase
{
  public $table_name = 'ArticleContent';

  public function __construct(array $attributes = [])
  {
    $this->defAttr('id',          'ID');
    $this->defAttr('locale',      'Lang');
    $this->defAttr('article_id',  'ArticleId');
    $this->defAttr('title',       'Title');
    $this->defAttr('alias',       'Alias');
    $this->defAttr('full_text',   'TextFull');

    $this->assignAttrs($attributes);
  }

  public function validators()
  {
    return [
      'article_id' => array('type' => 'int', 'required' => true, 'error_empty' => 'Article ID is empty'),
      'alias'  => array('type' => 'string', 'required' => true, 'error_empty' => 'Alias cannot be empty')
    ];
  }
}
