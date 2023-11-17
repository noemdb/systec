<?php
$base = $_SERVER['DOCUMENT_ROOT'];
include_once($base.'/conn.php');

// Obtener el nombre del archivo CSV (grupo;subgrupo;seccion;ident;description;model;serial;color;status)
$handle = fopen($filenameCSV, "r"); //var_dump($handle);die();

?>

<table class="table">
    <thead>
        <tr>
            <th>N</th>
            <th>Gr</th>
            <th>SG</th>
            <th>Sec</th>
            <th>Ident.</th>
            <th>Descripción</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Serial</th>
            <th>Material</th>
            <th>Color</th>
            <th>Estado</th>
            <!-- <th>SI/NO</th> -->
        </tr>
    </thead>
    <tbody>
        <?php $i=1; ?>
        <?php while (($row = fgetcsv($handle,0,";")) !== false) { ?>            
            <?php                
                $dbCvs = new DB($base.'/db.db');
                $dbCvs->createOrUpdate($row);
                $dbCvs->close();
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <?php foreach ($row as $item) : ?>
                    <td><?php echo $item; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php }; ?>
    </tbody>
</table>