<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="">
<link rel="icon" type="image/svg+xml" href="<?= e(url('favicon.svg')) ?>">
<title>SysTec - <?= e(!empty($title) ? $title : 'Inicio') ?></title>

<link rel="stylesheet" href="<?= e(url('vendor/bootstrap/5.3.2/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('vendor/bootstrap/5.3.2/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(url('vendor/bootstrap/5.3.2/icon/css/bootstrap-icons.css')) ?>">

<link rel="stylesheet" href="<?= e(url('vendor/bootstrap/5.3.2/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(url('vendor/bootstrap/5.3.2/css/cover.css')) ?>">
<link rel="stylesheet" href="<?= e(url('responsive.css')) ?>">

<link rel="stylesheet" href="<?= e(url('vendor/datatables/datatables.min.css')) ?>" />
<script src="<?= e(url('vendor/datatables/datatables.min.js')) ?>"></script>


<style nonce="<?= e(systec_csp_nonce()) ?>">
    .property-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.4rem;
        height: 2.4rem;
        padding: 0;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .property-action-btn:hover {
        transform: translateY(-1px);
    }

    .property-action-btn .bi {
        font-size: 1.15rem;
        line-height: 1;
    }

    .bar-chart-vertical {
    display: flex;
    justify-content: space-evenly;
    align-items: flex-end;
    height: 280;
    }

    .bar {
    background: #007bff;
    width: 60px;
    display: flex;
    justify-content: center;
    align-items: flex-end;
    border-radius: 5px;
    }

    .title {
    font-weight: bold;
    font-size: 14px;
    text-align: center; 
    width: 60px;
    }

    .value {
    font-size: 14px;
    padding: 5px 0;
    }
</style>