# DBWK database PHP framework

This is a simple PHP framework that provides basic functionality for interacting with a database. 
It includes a class called `DBWK` that allows you to perform SELECT, INSERT, UPDATE, and DELETE operations 
on a database table. It also provides support for eager loading of related models and transactions.

## Installation

To use this framework, you need to have PHP installed on your system. You also need to have the Monolog library installed for logging purposes.

1. Clone this repository to your local machine.
2. Install the required dependencies using Composer: `composer install`
3. Include the necessary files in your project: 
   ```php
   require_once 'path/to/lib/dbwk.php';
   require_once 'path/to/lib/model_base.php';
   require_once 'path/to/lib/validator.php';

   $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['password']);
   \App\Lib\DBWK::$pdo = $pdo;
   ```
   
## Usage
Here's an example of how to use the framework:

```php
use App\Lib\DBWK;
use App\Lib\ModelBase;
use App\Lib\Validator;

$pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['password']);
DBWK::$pdo = $pdo

// Create a model class
class MyModel extends ModelBase
{
    public function __construct()
    {
        // Define attribute mapping and relationships
        $this->defAttr('name', 'name');
        $this->belongs_to = ['category'];
        $this->has_many = ['articles'];
    }
}

// Create an instance of the model
$model = new MyModel();

// Create a DBWK instance for the model
$dbwk = new DBWK($model);

// Perform a SELECT query
$result = $dbwk->select()->where(['name' => 'example'])->get();

// Perform an INSERT query
$newModel = new MyModel();
$newModel->assignAttr('name', 'new example');
$newModel->save();

// Perform an UPDATE query
$model->assignAttr('name', 'updated example');
$model->save();

// Perform a DELETE query
$model->delete();
```

See /examples/*.php for more details

## License

This framework is open-source and released under the MIT License. You can use it for both personal and commercial projects.
