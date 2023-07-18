<?php
/**
 * Table: Article
 * 
 * Attributes:
 * - ID         integer
 * - PubDate    integer
 * - CategoryID integer
 * - Created    integer
 * - Updated    integer
 *
 */

namespace App\Models;
use \App\Lib\Validator;
use \App\Lib\DBWK;

class Article extends ModelBase
{
  public $table_name = 'Article';

  public $has_many = ['article_content' => ArticleContent::class];

  public $nested_attributes_for = ['article_content' => ArticleContent::class];

  public function __construct(array $attributes = [])
  {
    if (isset($attributes['published_at']))
      $attributes['published_at'] = strtotime($attributes['published_at']);

    $this->defAttr('id',           'ID');
    $this->defAttr('published_at', 'PubDate', strtotime(date('Y-m-d 00:00')));
    $this->defAttr('category_id',  'CategoryID');
    $this->defAttr('created_at',   'Created');
    $this->defAttr('updated_at',   'Updated');

    $this->defAttr('title',        'Title');
    $this->defAttr('locale',       'Lang');
    $this->defAttr('text_short',   'TextShort');
    $this->defAttr('full_text',    'FullText');

    $this->assignAttrs($attributes);
  }


  public function validators()
  {
    return [
      'published_at'  => array('type' => 'string', 'required' => true, 'error_empty' => 'content_err_published_at_empty'),
    ];
  }
  

  /**
   *
   * @return Model|null
   */
  public function category()
  {
    $dbwk = new DBWK(new Category());
    return $dbwk->find_by(['id' => $this->category_id]);
  }


  public function articleContent()
  {
    if (empty($this->id))
      return [];
    if (isset($this->related_objects['article_content']))
      return $this->related_objects['article_content'];

    $dbwk = new DBWK(new ContentData());
    $this->related_objects['article_content'] = $dbwk->select()->where(['content_id' => $this->id])->fetch_all()->result;
    return $this->related_objects['article_content'];
  }


  public function pictures()
  {
    if (!isset($this->id)) return [];
    if (!empty($this->related_objects['pictures']))
      return $this->related_objects['pictures'];

    $tmp_picture_obj = new ContentPicture(['content_id' => $this->id]);
    $directory = $tmp_picture_obj->directory();

    $this->related_objects['pictures'] = array();

    if (is_dir($directory) && ($files = scandir($directory))) {
      foreach ($files as $file) {
        if (($obj = $this->new_picture($directory, $file)))
          $this->related_objects['pictures'][] = $obj;
      }
    }
    return $this->related_objects['pictures'];
  }


  private function new_picture($directory, $file)
  {
    if ($file != "." && $file != ".." && 'th_' != substr($file, 0, 3)
      && ($imginfo = @getimagesize($directory.DIRECTORY_SEPARATOR.$file))
      && ('image/jpeg' == $imginfo['mime'])
    ) {
      return new ContentPicture([
        'content_id' => $this->id,
        'file_name' => $file,
        'width' => $imginfo[0],
        'height' => $imginfo[1],
      ]);
    }
    return null;
  }


  public function files()
  {
    if (!isset($this->id)) return [];
    if (!empty($this->related_objects['files']))
      return $this->related_objects['files'];

    $tmp_picture_obj = new ContentFile(['content_id' => $this->id]);
    $directory = $tmp_picture_obj->directory();

    $this->related_objects['files'] = array();

    if (is_dir($directory) && ($files = scandir($directory))) {
      foreach ($files as $file) {
        if (($obj = $this->new_file($file)))
          $this->related_objects['files'][] = $obj;
      }
    }
    return $this->related_objects['files'];
  }


  private function new_file($file)
  {
    if ($file != "." && $file != "..") {
      return new ContentFile(['content_id' => $this->id, 'file_name' => $file]);
    }
    return null;
  }

  public static function find_by_alias($params)
  {
    $dbwk = new DBWK(new static());

    $dbwk->select('*, de_content.ID')
      ->joins('LEFT JOIN ArticleContent ON Article.ID = ArticleContent.ArticleID')
      ->where('category_id = :category_id AND alias = :alias', $params)
      ->limit(1)
      ->with(['article_content']);
    $result = $dbwk->get();

    if (empty($result))
      throw new \Exception("Record not found", 404);

    return $result;
  }
}
