<table class="table">
    <thead>
        <tr>
            <th>grupo</th>
            <th>subgrupo</th>
            <th>seccion</th>
            <th>ident</th>
            <th>adscription</th>
            <th>description</th>
            <th>model</th>
            <th>color</th>
            <th>serial</th>
            <th>status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($properties as $property) : ?>
            <tr>
                <td><?php echo $property['grupo']; ?></td>
                <td><?php echo $property['subgrupo']; ?></td>
                <td><?php echo $property['seccion']; ?></td>
                <td><?php echo $property['ident']; ?></td>
                <td><?php echo $property['adscription']; ?></td>
                <td><?php echo $property['description']; ?></td>
                <td><?php echo $property['model']; ?></td>
                <td><?php echo $property['color']; ?></td>
                <td><?php echo $property['serial']; ?></td>
                <td><?php echo $property['status']; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>