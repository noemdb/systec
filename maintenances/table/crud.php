<table class="table">
    <thead>
        <tr>
            <th>id</th>
            <th>property_id</th>
            <th>type</th>
            <th>description</th>
            <th>date</th>
            <th>time_taken</th>
            <th>technician</th>
            <th>status</th>
            <th>next_maintenance_date</th>
            <th>failure_reason</th>
            <th>notes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($maintenances as $maintenance) : ?>
            <tr>
                <td><?php echo $maintenance['id']; ?></td>
                <td><?php echo $maintenance['property_id']; ?></td>
                <td><?php echo $maintenance['type']; ?></td>
                <td><?php echo $maintenance['description']; ?></td>
                <td><?php echo $maintenance['date']; ?></td>
                <td><?php echo $maintenance['time_taken']; ?></td>
                <td><?php echo $maintenance['technician']; ?></td>
                <td><?php echo $maintenance['status']; ?></td>
                <td><?php echo $maintenance['next_maintenance_date']; ?></td>
                <td><?php echo $maintenance['failure_reason']; ?></td>
                <td><?php echo $maintenance['notes']; ?></td>
                <td>
                    <!-- <div class="btn-group" role="group" aria-label="Basic example"> -->
                        <a class="btn btn-warning btn-sm " href="./maintenances.php?modeEdit=true&id=<?php echo $maintenance['id']; ?>" role="button">Edit</a>
                    <!-- </div> -->
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>