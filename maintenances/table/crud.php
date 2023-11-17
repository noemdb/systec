<table class="table table-striped table-sm table-hover" id="myTable">
    <thead>
        <tr>
            <th>N</th>
            <th>Código</th>
            <th>type</th>
            <th>description</th>
            <th>date</th>
            <th>time_taken(Hrs)</th>
            <th>technician</th>
            <th>status</th>
            <th>next_maintenance_date</th>
            <th>failure_reason</th>
            <th>notes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $i=1; ?>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr class=" <?php echo ($maintenance['id'] == $id) ? " table-secondary " : null; ?> ">
                <td><?php echo $i++; ?></td>
                <td class=" text-nowrap"><?php echo $maintenance['code']; ?></td>
                <td><?php echo $maintenance['type']; ?></td>
                <td><?php echo $maintenance['description']; ?></td>
                <td><?php echo $maintenance['date']; ?></td>
                <td><?php echo $maintenance['time_taken']; ?></td>
                <td><?php echo $maintenance['technician']; ?></td>
                <td class="<?php echo ($maintenance['status'] == 'finalizado') ? "text-uppercase fw-bold text-success " : null; ?>"><?php echo $maintenance['status']; ?></td>
                <td><?php echo $maintenance['next_maintenance_date']; ?></td>
                <td><?php echo $maintenance['failure_reason']; ?></td>
                <td><?php echo $maintenance['notes']; ?></td>
                <td>
                    <div class="d-flex justify-content-evenly">
                        <a class="btn btn-warning btn-sm mx-1" href="./maintenances.php?modeEdit=true&id=<?php echo $maintenance['id']; ?>" role="button">Edit</a>
                        <a class="btn btn-info btn-sm mx-1" href="./maintenances/actions/status.php?status=revision&id=<?php echo $maintenance['id']; ?>" role="button">Revisión</a>
                        <a class="btn btn-success btn-sm mx-1" href="./maintenances/actions/status.php?status=finalizado&id=<?php echo $maintenance['id']; ?>" role="button">Finalizar</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>