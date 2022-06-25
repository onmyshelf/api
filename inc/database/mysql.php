<?php
/*
*  MySQL/MariaDB database support
*/

require_once('global/sqlDatabase.php');

class Database extends SqlDatabase
{
    private $connection;

    public function __construct()
    {
        try {
            $this->connection = new PDO(DATABASE.':host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASSWORD);
        } catch (Exception $e) {
            Logger::fatal("error while testing database connection: ".$e);
            exit(1);
        }
    }


    /**
     * Initialize database
     * @return bool  Success
     */
    public function install()
    {
        $sql = file_get_contents(__DIR__.'/init/'.DATABASE.'.sql');

        # allow multiple queries
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

        if (!$this->connection->exec($sql)) {
            Logger::fatal('Database initialization failed!');
            return false;
        }

        return parent::install();
    }


    /**
     * Runs a SELECT query
     * @param string $query SQL query
     * @param array $args
     * @return array|bool
     */
    protected function select(string $query, array $args=[])
    {
        $stmt = $this->connection->prepare($query);
        if ($stmt === false) {
            Logger::error("Error while create statement for query: $query");
            return false;
        }

        //Logger::debug("SQL query: $query");
        //Logger::var_dump($args);

        if ($stmt->execute($args) === false) {
            return false;
        }

        return $stmt->fetchAll();
    }


    /**
     * Creates a prepared query, binds the given parameters and returns the result of the executed
     * @param string $query SQL query
     * @param array $args   Array key => value
     * @return bool
     */
    protected function write(string $query, array $args=[])
    {
        if (count($args) == 0) {
            return false;
        }

        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            Logger::error("Error while create statement for query: $query");
            return false;
        }

        //Logger::debug("SQL query: $query");
        //Logger::var_dump($args);

        return $stmt->execute($args);
    }


    /**
     * Creates a prepared query, binds the given parameters and returns the result of the executed
     * @param  string $table    Table name
     * @param  array  $values   Array or arrays key => value
     * @param  array  $orUpdate Array of fields to update on duplicate key
     * @return bool
     */
    protected function insert(string $table, array $values, array $orUpdate=[])
    {
        if (count($values) == 0) {
            return false;
        }

        $query = 'INSERT INTO `'.$table.'` (';

        foreach ($values[0] as $key => $v) {
            $query .= '`'.$key.'`,';
        }
        // delete last comma
        $query = substr($query, 0, -1).') VALUES ';

        // avoid errors if data not correct (missing keys)
        try {
            $queryValues = '('.implode(',', array_values(array_fill(0,count($values[0]),'?'))).'),';
        } catch (Throwable $t) {
            Logger::fatal('Bad array in insert values');
            return false;
        }

        $query .= str_repeat($queryValues, count($values));
        // delete last comma
        $query = substr($query, 0, -1);

        // or update
        if (count($orUpdate) > 0) {
            $query .= ' ON DUPLICATE KEY UPDATE ';
            foreach ($orUpdate as $key) {
                // WARNING: this is working only from MariaDB 10.3.3!
                $query .= '`'.$key.'`=VALUE(`'.$key.'`),';
            }
            // delete last comma
            $query = substr($query, 0, -1);
        }

        $params = [];
        foreach ($values as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        if (!$this->write($query, $params)) {
            return false;
        }

        // returns last inserted ID if any
        if ($this->connection->lastInsertId()) {
            return $this->connection->lastInsertId();
        }

        return true;
    }
}
