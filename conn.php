<?php
class DB
{
    private SQLite3 $db;

    function __construct()
    {
        $this->db = new SQLite3('./db.db');
        $this->db->exec('CREATE TABLE IF NOT EXISTS properties (id INTEGER PRIMARY KEY AUTOINCREMENT, grupo STRING, subgrupo STRING, seccion STRING, ident STRING, description STRING, adscription STRING, serial STRING, status STRING)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS maintenances (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER, type STRING, description STRING, date DATETIME, time_taken INTEGER, technician VARCHAR(255), next_maintenance_date DATETIME, failure_reason TEXT, notes TEXT, status STRING)');
    }

    function select(string $query)
    {
        $stmt = $this->db->query($query);
        $result = [];

        if ($stmt) {
            while ($row = $stmt->fetchArray()) {
                array_push($result, $row);
            }
        }

        $this->db->close();

        return $result;
    }

    function create(string $query, $values)
    {
        $stmt = $this->db->prepare($query);

        foreach ($values as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        $result = $stmt->execute();

        $this->db->close();

        var_dump($result);

        return $result;
    }

    function execSQL(string $stringSQL)
    {
        return $this->db->exec($stringSQL);
    }

    function getListPropertyStatus()
    {
        return ['bueno'=>'Bueno','regular'=>'Regular','malo'=>'Malo'];
    }

    function getListMaintenanceStatus()
    {
        return ['iniciado'=>'Iniciado','revision'=>'Revisión','finalizado'=>'Finalizado'];
    }

    function getListMaintenanceType()
    {
        return ['preventivo'=>'Preventivo','correctivo'=>'Correctivo','predictivo'=>'Predictivo'];
    }

    function getListMaintenanceTechnician()
    {
        return ['jmedina'=>'jmedina','ndominguez'=>'ndominguez'];
    }

}

/*


properties
grupo: Un campo de tipo VARCHAR(255) para almacenar el grupo del bien.
subgrupo: Un campo de tipo VARCHAR(255) para almacenar el subgrupo del bien.
seccion: Un campo de tipo VARCHAR(255) para almacenar la sección del bien.
ident: Un campo de tipo VARCHAR(255) para almacenar el identificador del bien.
adscription: Un campo de tipo VARCHAR(255) para almacenar la dirección del bien.
description: Un campo de tipo TEXT para almacenar la descripción del bien.
serial: Un campo de tipo TEXT para almacenar el serial del bien.
status: Un campo de tipo VARCHAR(255) para almacenar el estado del bien.

maintenances
id: Un campo de tipo INTEGER para almacenar el identificador único del registro.
property_id: Un campo de tipo VARCHAR(255) para almacenar el identificador del bien a la que se refiere el registro.
type: Un campo de tipo VARCHAR(255) para almacenar el tipo de mantenimiento, ya sea preventivo/correctivo.
description: Un campo de tipo TEXT para almacenar una descripción detallada del mantenimiento.
date: Un campo de tipo DATETIME para almacenar la fecha en la que se realizó el mantenimiento.
time_taken: Un campo de tipo INTEGER para almacenar el tiempo que tardó el mantenimiento.
technician: Un campo de tipo VARCHAR(255) para almacenar el nombre del técnico que realizó el mantenimiento.
status: Un campo de tipo VARCHAR(255) para almacenar el estado del mantenimiento, ya sea completado o pendiente.
next_maintenance_date: Un campo de tipo DATETIME para almacenar la fecha del próximo mantenimiento programado.
failure_reason: Un campo de tipo TEXT para almacenar la razón del mantenimiento preventivo/correctivo.
notes: Un campo de tipo TEXT para almacenar cualquier información adicional sobre el mantenimiento.

*/