<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include_once($base.'/conn.php');

// Obtener el nombre del archivo CSV
$filenameCSV = "properties.csv";
$handle = fopen($filenameCSV, "r"); //var_dump($handle);die();

?>

<table class="table">
    <tbody>
        <?php while (($row = fgetcsv($handle,0,";")) !== false) { ?>
            <?php                
                $db = new DB($base.'/db.db');
                $ident = $row[3];
                $condition = " WHERE ident=".$ident;
                $property = $db->getFirstForConditions('properties',$condition);
            ?>
            <tr>
                <?php foreach ($row as $item) : ?>
                    <td><?php echo $item; ?></td>
                <?php endforeach; ?>
                <td><?php echo ($property) ? 'SI':'NO' ; ?></td>
            </tr>
        <?php }; ?>
    </tbody>
</table>