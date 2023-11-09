<?php
class DB
{
    private SQLite3 $db;

    function __construct()
    {
        $this->db = new SQLite3('./db/systec.db');
        $this->db->exec('CREATE TABLE IF NOT EXISTS users (id INT unsigned auto_increment, firstname STRING, lastname STRING, CONSTRAINT `PRIMARY` PRIMARY KEY (id))');
    }

    function select(string $query)
    {
        $stmt = $this->db->query($query);
        $result = [];

        while ($row = $stmt->fetchArray()) {
            array_push($result, $row);
        }

        $this->db->close();

        return $result;
    }
}
