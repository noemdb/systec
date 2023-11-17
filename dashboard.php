<html lang="en" class="h-100" data-bs-theme="light">

<?php
$title = "Dashboard";
$base = $_SERVER['DOCUMENT_ROOT']; 
include_once($base.'/conn.php');
$db = new DB($base.'/db.db');

$properties = $db->index("properties");

?>

<head>

    <?php include('include/head.php'); ?>

</head>

<body class="d-flex h-100 text-center shadow-none">

<div class=" d-flex w-100 h-100 mx-auto flex-column">

        <header class="bg-secondary-subtle border-bottom shadow-sm py-2 px-2">
            <?php include('include/header.php'); ?>
        </header>

        <main class="px-3 w-100 mt-2">
            <div class="container-fluid">

                <h2 class="">Dashboard</h2>
                <p class="">Bienvenido!</p>

                <div class="card">
                    <div class="card-body">

                        <h3>Lista de Bienes Registrados</h3>

                        <?php include('dashboard/table/index.php'); ?>                        

                    </div>
                </div>

            </div>
        </main>

        <footer class="mt-auto text-white-50 bg-secondary-subtle py-2">
            <?php include('include/footer.php'); ?>
        </footer>

    </div>

    <?php include('include/script.php'); ?>

    <script type="text/javascript">
        const search = "<?php echo $_GET['search']; ?>";

        const table = new DataTable('#myTable', {
            search: {
                search: search ?? ''
            },
            order: [4, 'asc']
        });

        table.on('search', () => {
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url);

            params.set('search', table.search())

            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newUrl);
        })
    </script>

    <?php $db->close(); ?>

</body>

</html>

