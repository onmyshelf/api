<html>
<body>
  <h1>OnMyShelf core check</h1>
<?php

$required_database_methods = array(
  'getConfig',
  'setConfig',
  'getCollection',
  'deleteCollection',
  'getCollectionTemplate',
  'deleteCollectionTemplate',
  'getItems',
  'getItem',
  'getItemFields',
  'addItem',
  'deleteItem',
  'addItemField',
  'deleteItemField',
  'getFields',
  'addField',
  'deleteField',
  'install',
  'upgrade',
);

$required_import_methods = array(
  'scanFields',
  'import',
  'setFields',
  'setCollection',
  'report',
);

require_once('inc/init.php');
require_once('inc/api.php');
?>
  <h2>Core</h2>
  <ul>
    <li>Version: <?php echo VERSION ?></li>
  </ul>
  <h2>Configuration</h2>
  <ul>
    <li>API URL: <?php echo API_URL; if (substr(API_URL, -1) == '/') echo " <strong>ERROR: PLEASE REMOVE last '/'</strong>" ?></li>
    <li>Media URL: <?php echo MEDIA_URL; if (substr(MEDIA_URL, -1) == '/') echo " <strong>ERROR: PLEASE REMOVE last '/'</strong>" ?></li>
    <li>Database engine: <?php echo DATABASE ?></li>
  </ul>
  <h2>Database</h2>

<?php
// test database connection
try {
  $database = new Database();
} catch (Throwable $t) {
  echo "ERROR: cannot connect to database!";
  Logger::fatal("error while initializing database connection: ".$t);
  exit(1);
}

unset($database);

// check database class
function check_methods($class, $label, $required_methods)
{
  echo "Public methods for $label:<br/><ul>";

  foreach (get_class_methods($class) as $method) {
    if (in_array($method, $required_methods)) {
      $method = "<strong>$method</strong>";
    }
    echo "<li>$method</li>";
  }

  $ok = 0;
  foreach ($required_methods as $method) {
    if (method_exists($class, $method)) {
      $ok++;
    } else {
      echo "<li><b>MISSING method: $method</b></li>";
    }
  }

  echo "</ul>";

  echo "Required methods: $ok/".count($required_methods);
}

check_methods('Database', "database ".DATABASE, $required_database_methods);
?>

<h2>API</h2>
Routes:
<ul>
<?php
  $api = new Api();
  foreach ($api->getRoutes() as $route) {
    echo "<li>$route</li>";
  }
?>
</ul>

<h2>Import</h2>
<?php
if (isset($_GET['import'])) {
  $import = $_GET['import'];
  if (file_exists("inc/import/$import.php")) {
    require("inc/import/$import.php");
  } else {
    if (file_exists("inc/modules/import/$import.php")) {
      require("inc/modules/import/$import.php");
    } else {
      echo "Import $import not found!";
      exit(1);
    }
  }

  check_methods('Import', "import module $import", $required_import_methods);
} else {
  echo "No import specified.<br/>";
}
?>
</body>
</html>
