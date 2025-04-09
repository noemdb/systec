<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include_once($base.'/conn.php');
?>


<table class="table">
    <thead>
        <tr>
            <th>N</th>
            <th>Código</th>
            <th>type</th>
            <th>description</th>
            <th>technician</th>
            <th>failure_reason</th>
            <th>date</th>
            <th>notes</th>
        </tr>
    </thead>
    <tbody>
        <?php $i=1; ?>
        <?php while (($row = fgetcsv($handle,0,",")) !== false) { ?> 
            <?php
                $db = new DB($base.'/db.db') ;
                $ident = substr($row[0], -4); 
                $table = "properties";
                $condition = " WHERE ident=".$ident;
                $property = $db->getFirstForConditions($table,$condition); 

                if ($property) {
                    $data = array();
                    $code = $db->getCodeId($property['id']);
                    $time_taken = rand(20,100);
                    if ($code) {
                        $table = "maintenances";

                        $dateString = $row[6];
                        $timestamp = strtotime($dateString);
                        $date_convert = date("Y-m-d h:i:s",$timestamp);
                        //echo "breakpoint<br>"; print("<pre>".var_dump($row[6],$date_convert)."</pre>"); die();

                        $data = array(
                            'property_id' => $property['id'],
                            'code' => $row[0],
                            'type' => strtolower($row[1]),
                            'description' => $row[2],
                            'technician' => $row[3],
                            'failure_reason' => $row[5],
                            'date' => $date_convert,
                            'notes' => $row[7],
                            'status' => 'finalizado',
                            'time_taken' => $time_taken,
                        );
                        $db->create($table,$data);                        
                    } 
                    
                    $db->close();
                }                
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $row[0]; ?></td>
                <td><?php echo $row[1]; ?></td>
                <td><?php echo $row[2]; ?></td>
                <td><?php echo $row[3]; ?></td>
                <td><?php echo $row[5]; ?></td>
                <td><?php echo $row[6]; ?></td>
                <td><?php echo $row[7]; ?></td>
                
            </tr>
        <?php }; ?>
    </tbody>
</table>

<!-- id_mantenimiento,tipo,descripcion,fname,usuario,coment_user,fecha,obs,estado -->