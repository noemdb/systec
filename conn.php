<?php

date_default_timezone_set('America/Caracas');

class DB extends SQLite3
{
    function __construct($filename) {
        $this->open($filename);
        $this->exec('CREATE TABLE IF NOT EXISTS properties (id INTEGER PRIMARY KEY AUTOINCREMENT, grupo STRING, subgrupo STRING, seccion STRING, ident STRING, adscription STRING, description STRING, model STRING, color STRING, serial STRING, status STRING)');
        $this->exec('CREATE TABLE IF NOT EXISTS maintenances (id INTEGER PRIMARY KEY AUTOINCREMENT, property_id INTEGER, code STRING, type STRING, description STRING, date DATETIME, time_taken INTEGER, technician VARCHAR(255), next_maintenance_date DATETIME, failure_reason TEXT, notes TEXT, status STRING)');
    }

    public function createOrUpdate($dataCsv)
    {
        $table = "properties";
        $ident = $dataCsv[3];
        $condition = " WHERE ident=".$ident;
        $property = $this->getFirstForConditions($table,$condition);       

        if ($property) {
            $id = $property['id'];
            $data = array(
                'status' => $dataCsv[8]                
            );
            $this->update($table, $id, $data);            
        } else {
            $data = array(
                'grupo' => $dataCsv[0],
                'subgrupo' => str_pad($dataCsv[1], 4, "0", STR_PAD_LEFT),
                'seccion' => str_pad($dataCsv[2], 3, "0", STR_PAD_LEFT),
                'ident' => str_pad($dataCsv[3], 4, "0", STR_PAD_LEFT),
                'description' => $dataCsv[4],
                'model' => $dataCsv[5],
                'serial' => $dataCsv[6],
                'color' => $dataCsv[7],
                'status' => $dataCsv[10],                
            );
            $this->create($table,$data);
        }
        $this->close();
    }    

    function create($table,$data)
    {    
        $keys = implode(', ', array_keys($data));
        $values = ':' . implode(', :', array_keys($data));     
        $query = "INSERT INTO $table ($keys) VALUES ($values)";
        $stmt = $this->prepare($query); //var_dump($stmt);die();
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }    
        $result = $stmt->execute(); 
        $this->close();
    }

    public function update($table, $id, $data) {
        $prepare = array();
        foreach ($data as $key => $value) {
            $prepare[] = $key."=:{$key}";
        } 
        $query = "UPDATE ".$table." SET " . implode(", ", $prepare) . " WHERE id={$id}"; //var_dump($query)  ; die();
        $stmt = $this->prepare($query); //var_dump($stmt);die();   
        foreach ($data as $key => $value) {
            $stmt->bindValue(':'.$key, $value);
        }
        $stmt->execute();
        
    }

    public function index($table,$conditions=null,$fields="*",$sort=null) {
        $stmt = $this->query("SELECT ".$fields." FROM ".$table." ".$conditions." ".$sort);
        $tabla_index = [];
        if ($stmt) {
            while ($row = $stmt->fetchArray()) {
                $tabla_index[] = $row;
            }
        }
        return $tabla_index;
    }

    public function getListProperties($table) {
        $stmt = $this->query("SELECT * FROM ".$table." ");
        $list_arr = [];
        if ($stmt) {
            while ($row = $stmt->fetchArray()) {
                $code = $this->getCodeId($row['id']);
                $list_arr[$row['id']] = $code;
            }
        }        
        return $list_arr;
    }
    ////////////////////////////////////////////////////

    function getCodeId($id)
    {
        $stmt = $this->query("SELECT * FROM properties WHERE id = ".$id." order by ident ASC"); //var_dump($stmt);die();
        if ($stmt) {
            $arr = $stmt->fetchArray();
            $grupo = $arr['grupo'];
            $subgrupo = str_pad($arr['subgrupo'], 4, "0", STR_PAD_LEFT);
            $seccion = str_pad($arr['seccion'], 4, "0", STR_PAD_LEFT);
            $ident = str_pad($arr['ident'], 4, "0", STR_PAD_LEFT);
            return ($arr) ? $grupo.'-'.$subgrupo.'-'.$seccion.'-'.$ident: null;
        }        
    }

    function select(string $query)
    {
        $stmt = $this->query($query);
        $result = [];
        if ($stmt) {
            while ($row = $stmt->fetchArray()) {
                array_push($result, $row);
            }
        }        
        return $result;
    }    

    function getFirstForId($table,$id)
    {
        $query = "SELECT * FROM ".$table." WHERE id = ".$id.""; //die();
        $stmt = $this->query($query);
        $fetchArray = ($stmt) ? $stmt->fetchArray() : null ;
        return $fetchArray ;
    }

    function getFirstForConditions($table,$conditions)
    {
        $query = "SELECT * FROM ".$table. $conditions; //die();
        $stmt = $this->query($query);
        $fetchArray = ($stmt) ? $stmt->fetchArray() : null ;
        return $fetchArray ;
    }

    //INI List select////////////////////////////////////////////////////////////////
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
    //FIN List select////////////////////////////////////////////////////////////////

    function restarFechasEnHoras(DateTime $fecha1, DateTime $fecha2) {
        $intervalo = $fecha1->diff($fecha2);
        return $intervalo->h;
    }

    // function restarFechasEnHoras(DateTime $fecha1, DateTime $fecha2) {
    //     return round(($fecha1->getTimestamp() - $fecha2->getTimestamp()) / 3600);
    // }
}

/*


properties
grupo: Un campo de tipo VARCHAR(255) para almacenar el grupo del bien.
subgrupo: Un campo de tipo VARCHAR(255) para almacenar el subgrupo del bien.
seccion: Un campo de tipo VARCHAR(255) para almacenar la sección del bien.
ident: Un campo de tipo VARCHAR(255) para almacenar el identificador del bien.
adscription: Un campo de tipo VARCHAR(255) para almacenar la dirección del bien.
description: Un campo de tipo TEXT para almacenar la descripción del bien.
model: Un campo de tipo TEXT para almacenar el modelo del bien.
color: Un campo de tipo TEXT para almacenar el color del bien.
serial: Un campo de tipo TEXT para almacenar el serial del bien.
status: Un campo de tipo VARCHAR(255) para almacenar el estado del bien.

maintenances
id: Un campo de tipo INTEGER para almacenar el identificador único del registro.
property_id: Un campo de tipo VARCHAR(255) para almacenar el identificador del bien a la que se refiere el registro.
code: Un campo de tipo VARCHAR(255) para almacenar el código del bien a la que se refiere el registro.
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