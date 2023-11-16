<div class="card p-2 m-2">
    <h5 class="card-title text-dark pb-2 mb-2">Matenimiento por tipo (Preventivo, Correctivo y Predictivo)</h5>
    <div class="card-body p-2">
        <div class="bar-chart-vertical">
            <?php $total = count($maintenances); ?>
            <?php foreach ($types as $k=>$v) : ?>
                <?php 
                    $height = (! empty($total)) ? $v / $total: null ;
                    $height = 100 * round($height,2);
                ?>
                <div class="item pt-2 mt-2">
                    <div class="bar" style="height:<?php echo $height; ?>%">
                        <div class="value text-white small"><?php echo $height; ?>%</div>
                    </div>
                    <div class="title text-nowrap"><?php echo $k; ?></div> 
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>