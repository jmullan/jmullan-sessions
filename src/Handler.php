<?php
namespace Jmullan\Sessions;

/**
 * A PHP session handler to keep session data within a MySQL database
 */

class Handler implements \SessionHandlerInterface
{

    /**
     * a database MySQLi connection resource
     * @var resource
     */
    protected $db;

    /**
     * the name of the DB table which handles the sessions
     * @var string
     */
    protected $dbTable;

    protected $session_name;

    /**
     * Set db data if no connection is being injected
     * @param 	string	$dbHost
     * @param	string	$dbUser
     * @param	string	$dbPassword
     * @param	string	$dbDatabase
     */
    public function setDbDetails($dsn, $username, $password, $options = array())
    {
        //create db connection
        $this->setDbConnection(new \PDO($dsn, $username, $password, $options));
    }

    /**
     * Inject DB connection from outside
     * @param 	object	$db	expects MySQLi object
     */
    public function setDbConnection($db)
    {
        $this->db = $db;
    }

    /**
     * Inject DB connection from outside
     * @param 	object	$db	expects MySQLi object
     */
    public function setDbTable($dbTable)
    {
        $this->dbTable = $dbTable;
    }

    /**
     * Open the session
     * @return bool
     */
    public function open($save_path, $session_name)
    {
        $this->session_name = $session_name;
    }

    /**
     * Close the session
     */
    public function close()
    {
        $this->db = null;
    }

    /**
     * Read the session
     * @param int session id
     * @return string string of the sessoin
     */
    public function read($session_id)
    {
        $data = '';
        $query = sprintf("SELECT `data` FROM %s WHERE `session_name` = ? AND `session_id` = ?", $this->dbTable);
        $prepared = $this->db->prepare($query);
        $result = $prepared->execute(array($this->session_name, $session_id));
        if ($result) {
            $data = $prepared->fetchColumn(0);
            $prepared->closeCursor();
            unset($prepared);
            if ($data === null) {
                $data = '';
            }
        }
        return $data;
    }


    /**
     * Write the session
     * @param int session id
     * @param string data of the session
     */
    public function write($session_id, $data)
    {
        if ($data === '' || $data === null) {
            $this->destroy($session_id);
            return true;
        }
        $sql = "
             INSERT INTO %s (`session_name`, `session_id`, `data`)
             VALUES(?, ?, ?)
             ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `update_count` = `update_count` + 1
        ";
        $query = sprintf($sql, $this->dbTable);
        $prepared = $this->db->prepare($query);
        $result = $prepared->execute(array($this->session_name, $session_id, $data));
        $prepared->closeCursor();
        unset($prepared);
        return $result;
    }

    /**
     * Destoroy the session
     * @param int session id
     * @return bool
     */
    public function destroy($session_id)
    {
        $query = sprintf("DELETE FROM %s WHERE `session_name` = ? AND `session_id` = ?", $this->dbTable);
        $prepared = $this->db->prepare($query);
        $result = $prepared->execute(array($this->session_name, $session_id));
        $prepared->closeCursor();
        unset($prepared);
        return $result;
    }

    /**
     * Garbage Collector
     * @param int life time (sec.)
     * @return bool
     * @see session.gc_maxlifetime 1440
     */
    public function gc($max)
    {
        $query = sprintf("DELETE FROM %s WHERE `mtime` < NOW() - INTERVAL ? SECOND", $this->dbTable);
        $prepared = $this->db->prepare($query);
        $result1 = $prepared->execute(array($max));
        $prepared->closeCursor();
        unset($prepared);

        $prune = "
             DELETE FROM %s
             WHERE
                 `mtime` < NOW() - INTERVAL 1 HOUR
                 AND (data = '' OR data IS null)
        ";
        $query = sprintf($prune, $this->dbTable);
        $prepared = $this->db->prepare($query);
        $result2 = $prepared->execute(array($max));
        $prepared->closeCursor();
        unset($prepared);
        return $result1 && $result2;
    }
}
