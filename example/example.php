<?php

use \App\Models\Article;
use \App\Models\Categories;
use \App\Lib\DBWK;

$pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['password']);
DBWK::$pdo = $pdo

//
// Search all articles with category id '2' and contatin 'hello world' in related table
//
$page           = 1;
$per_page       = 5;
$category_id    = 2;
$search_keyword = 'hello world';

$dbwk = new DBWK(new Article());
$params = ['category_id' => $category_id, 'q' => "%$search%"];
$dbwk->select('*, Article.ID')
      ->joins('LEFT JOIN ArticleContent ON Article.ID = ArticleContent.ArticleID')
      ->where('category_id = :category_id AND (ArticleContent.Title LIKE :q OR Alias LIKE :q)', $params)
      ->paginate($page, 20)->order(['published_at' => 'DESC'])->with(['content_data'])->fetch_all();
}
var_dump($dbwk->attributes);

//
// Retrieve Article by ID
//
$article_id = 17;
$result = Article::find($article_id];
var_dump($result);

//
// Create Article
//
$params = $request->getParsedBody()['article'];

$obj = new Article($params);
$obj->save();

if (empty($obj->errors())) {
  echo 'Success!';
  var_dump($obj->attributes);
} else {
  echo 'Fail :-('
  var_dump($obj->errors());
}

// 
// Update Article
//
$params = $request->getParsedBody()['article'];
$obj = Content::find($args['id']);
$obj->assignAttrs($params);
$result = $obj->save();

//
// Delete Article
///
$obj = Content::find($args['id']);
$obj->delete();
