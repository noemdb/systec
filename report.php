<?php
$title = "Reporte de Bienes Dañados";
$base = $_SERVER['DOCUMENT_ROOT'];
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$properties = $db->indexWithMaintenances("properties.*", " order by date ASC ");


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include($base.'/include/head.php'); ?>

    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                color-adjust: exact;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-page {
                page-break-inside: avoid;
                break-inside: avoid;
                padding: 20px;
            }

            .print-page + .print-page {
                page-break-before: always;
            }

            .btn, .dataTables_wrapper, .table-hover thead, a {
                display: none !important;
            }

            #reportTable {
                border: none !important;
            }
        }
    </style>

</head>
<body class="d-flex h-100 text-center">

<div class="container-fluid bg-white p-0">

    <div class="table-responsive bg-white p-0">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th> </th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 0; ?>                
                <?php foreach ($properties as $property): ?>
                <?php $count++; ?></small>
                <tr>
                    <td>
                        <div>
                            <!-- Contenido completo del historial -->
                            <div class="d-flex w-100 h-100 mx-auto flex-column">
                                <header class="bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
                                    <div class="container-fluid">
                                        <?php include('historials/membrete.php'); ?> 
                                    </div>
                                </header>

                                <main class="px-0 mx-0 w-100 mt-2">
                                    <div class="container-fluid">
                                        <h4>Historial de mantenimiento </h4>
                                        <?php include('historials/report.php'); ?>

                                        
                                    </div>

                                    
                                </main>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>

</div>

<?php $db->close(); ?>
</body>
</html>