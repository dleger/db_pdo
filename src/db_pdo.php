<?php
// *************************************************************************************************************************
// * Object Model Database                                                                                                 *
// *                                                                                                                       *
// * Examples:                                                                                                             *
// *                                                                                                                       *
// * Create the connection:                                                                                                *
// * $bdd = new db_pdo();                                                                                                  *
// *                                                                                                                       *
// * Returns 1 row:                                                                                                        *
// * $user = $bdd->getOne("SELECT firstname FROM users WHERE id = '1'");                                                   *
// *                                                                                                                       *
// * Same thing but sanitasing parameters in WHERE statement:                                                              *
// * $user = $bdd->getOne("SELECT firstname FROM users WHERE id = :id", array(':id' => array(1, 'int')));                  *
// *                                                                                                                       *
// * Returns several rows:                                                                                                 *
// * $user = $bdd->getOne("SELECT firstname FROM users WHERE gender = :gender", array(':gender' => array('male', 'str'))); *
// *                                                                                                                       *
// * Insert 1 row:                                                                                                         *
// * $result = $bdd->insert('logs_jobserve_submit', array(                                                                 *
// * 'general_log_id' => 30,                                                                                               *
// * 'xml_received' => 'blabla bis')                                                                                       *
// * );                                                                                                                    *
// * $result contains the last ID                                                                                          *
// *                                                                                                                       *
// * Update 1 row:                                                                                                         *
// * $result = $bdd->update('logs_jobserve_submit', array(                                                                 *
// * 'general_log_id' => 10,                                                                                               *
// * 'xml_received' => 'test'                                                                                              *
// * ), "WHERE id = :id", array(                                                                                           *
// * ':id' => array(15, 'int'))                                                                                            *
// * );                                                                                                                    *
// * $result contains TRUE or FALSE                                                                                        *
// *                                                                                                                       *
// * Delete 1 row:                                                                                                         *
// * $result = $bdd->delete('logs_jobserve_submit', "WHERE id = :id", array(':id' => array(15, 'int')));                   *
// *************************************************************************************************************************
namespace dleger\db_pdo;

class db_pdo
{
    // *******************************
    // * Database Connect Parameters *
    // *******************************
    const DB_SERVERNAME = 'localhost';
    const DB_USERNAME = 'root';
    const DB_PASSWORD = '********';
    const DB_DBNAME = 'databasename';
    const DB_ENCODING = 'utf8';

    // ********************
    // * Other parameters *
    // ********************
    private $conn;

    // **********************************************
    // * Construct Method -> init and connect to DB *
    // **********************************************
    public function __construct($param = array()) {
        $this->conn = FALSE;
        $this->connect();
    }

    // ******************************************
    // * Destruct Method -> Ends the connection *
    // ******************************************
    public function __destruct() {
        $this->disconnect();
    }

    // ******************
    // * Connect Method *
    // ******************
    public function connect() {
        try {
            $this->conn = new \PDO('mysql:host=' . self::DB_SERVERNAME .';dbname=' . self::DB_DBNAME . ';charset=' . self::DB_ENCODING, self::DB_USERNAME, self::DB_PASSWORD);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            die('Error: ' . $e->getMessage());
        }

        $this->conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES, FALSE);
    }

    // ***********************
    // * Ends the Connection *
    // ***********************
    public function disconnect() {
        if ($this->conn) {
            unset($this->conn);
        }
    }

    // *****************
    // * Returns 1 row *
    // *****************
    public function getOne($query, $params = array()) {
        $result = $this->conn->prepare($query);

        // ***********************
        // * Binding the options *
        // ***********************
        foreach ($params as $param_name => $array_value) {
            if ($array_value[1] == 'int') {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_INT);
            } else {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_STR);
            }
        }

        if (!$result) {
            echo 'PDO error: ' . "\n";
            print_r($this->conn->errorInfo());
            echo "\n" . 'Query was: ' . $query;
            die();
        }

        if (!$result->execute()) {
            echo 'Query seems correct but error executing it: ' . $query;
            error_log('Error executing the query: ' . $query . implode(' - ', $result->errorInfo()));
            die();
        }

        $result->setFetchMode(\PDO::FETCH_ASSOC);

        $record = $result->fetch();

        if ($record == NULL || $record == FALSE) {
            return array();
        } else {
            return $record;
        }
    }

    // ************************
    // * Returns several rows *
    // ************************
    public function getMany($query, $params = array()) {
        $result = $this->conn->prepare($query);

        if (!$result) {
            echo 'PDO error: ' . "\n";
            print_r($this->conn->errorInfo());
            echo "\n" . 'Query was: ' . $query;
            die();
        }

        // ***********************
        // * Binding the options *
        // ***********************
        foreach ($params as $param_name => $array_value) {
            if ($array_value[1] == 'int') {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_INT);
            } else {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_STR);
            }
        }

        if (!$result->execute()) {
            echo 'Query seems correct but error executing it: ' . $query;
            error_log('Error executing the query: ' . $query . implode(' - ', $result->errorInfo()));
            die();
        }

        $result->setFetchMode(\PDO::FETCH_ASSOC);

        $record = $result->fetchAll();

        if ($record == NULL || $record == FALSE) {
            return array();
        } else {
            return $record;
        }
    }

    // *********************************************
    // * Insert one row and returns last ID number *
    // *********************************************
    public function insert($table, $values) {
        // ***********************
        // * Get all field types *
        // ***********************
        $array_found_types = self::getParamTypes($this->conn, $table, $values);

        // **********************
        // * Building the Query *
        // **********************
        $string_insert_params = $string_insert_values = '';

        foreach ($values as $field_name => $value) {
            if (!empty($string_insert_params)) {
                $string_insert_params .= ', ';
                $string_insert_values .= ', ';
            }

            $string_insert_params .= $field_name;
            $string_insert_values .= ':' . $field_name;
        }

        $query = "INSERT INTO " . $table . " (" . $string_insert_params . ") VALUES (" . $string_insert_values . ")";

        $result = $this->conn->prepare($query);

        // **********************
        // * Binding the values *
        // **********************
        $cpt_type = 0;

        foreach ($values as $field_name => $value) {
            if ($array_found_types[$cpt_type] == 'int') {
                if ($value === NULL) {
                    $result->bindValue(':' . $field_name, NULL, \PDO::PARAM_INT);
                } else {
                    $result->bindValue(':' . $field_name, (int)$value, \PDO::PARAM_INT);
                }
            } else {
                $result->bindValue(':' . $field_name, (string) $value, \PDO::PARAM_STR);
            }

            $cpt_type++;
        }

        if (!$result->execute()) {
            echo 'Error executing the query: ' . $query;
            error_log('Error executing the query: ' . $query . implode(' - ', $result->errorInfo()));
            die();
        }

        return $this->conn->lastInsertId();
    }

    // *********************************************************
    // * Update one row and returns TRUE no error is triggered *
    // *********************************************************
    public function update($table, $values, $options, $params = array()) {
        // ***********************
        // * Get all field types *
        // ***********************
        $array_found_types = self::getParamTypes($this->conn, $table, $values);

        // **********************
        // * Building the Query *
        // **********************
        $string_update_params = '';

        foreach ($values as $field_name => $value) {
            if (!empty($string_update_params)) {
                $string_update_params .= ', ';
            } else {
                $string_update_params .= 'SET ';
            }

            $string_update_params .= $field_name . ' = :f_' . $field_name;
        }

        $query = "UPDATE " . $table . " " . $string_update_params . " " . $options;

        $result = $this->conn->prepare($query);

        // **********************
        // * Binding the values *
        // **********************
        $cpt_type = 0;

        foreach ($values as $field_name => $value) {
            if ($array_found_types[$cpt_type] == 'int') {
                if ($value === NULL) {
                    $result->bindValue(':f_' . $field_name, NULL, \PDO::PARAM_INT);
                } else {
                    $result->bindValue(':f_' . $field_name, (int) $value, \PDO::PARAM_INT);
                }
            } else {
                $result->bindValue(':f_' . $field_name, (string) $value, \PDO::PARAM_STR);
            }

            $cpt_type++;
        }

        // ***********************
        // * Binding the options *
        // ***********************
        foreach ($params as $param_name => $array_value) {
            if ($array_value[1] == 'int') {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_INT);
            } else {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_STR);
            }
        }

        $check = $result->execute();

        if (!$check) {
            echo 'Error executing the query: ' . $query;
            error_log('Error executing the query: ' . $query . implode(' - ', $result->errorInfo()));
            die();
        }

        return $check;
    }

    // ************************************
    // * Delete Values in specified Table *
    // ************************************
    public function delete($table, $options, $params = array()) {
        $query = "DELETE FROM " . $table . " " . $options;

        $result = $this->conn->prepare($query);

        // ***********************
        // * Binding the options *
        // ***********************
        foreach ($params as $param_name => $array_value) {
            if ($array_value[1] == 'int') {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_INT);
            } else {
                $result->bindValue($param_name, $array_value[0], \PDO::PARAM_STR);
            }
        }

        $check = $result->execute();

        if (!$check) {
            echo 'Error executing the query: ' . $query;
            error_log('Error executing the query: ' . $query . implode(' - ', $result->errorInfo()));
            die();
        }

        return $check;
    }

    // ***************************
    // * Getting All Param Types *
    // ***************************
    private function getParamTypes($conn, $table, $values) {
        $result = $conn->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . self::DB_DBNAME . "' AND TABLE_NAME = '" . $table . "'");
        $result->execute();

        $result->setFetchMode(\PDO::FETCH_ASSOC);
        $response_columns = $result->fetchAll();

        $array_found_types = [];
        $array_ids_datatypes = [];
        $all_keys = array_keys($values);

        for ($cpt = 0; $cpt < count($response_columns); $cpt++) {
            $array_ids_datatypes[$response_columns[$cpt]['COLUMN_NAME']] = $response_columns[$cpt]['DATA_TYPE'];
        }

        for ($cpt_keys = 0; $cpt_keys < count($all_keys); $cpt_keys++) {
            $array_found_types[$cpt_keys] = $array_ids_datatypes[$all_keys[$cpt_keys]];
        }

        return $array_found_types;
    }
}
