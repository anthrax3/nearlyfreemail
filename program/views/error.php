<?php $BODY_CLASS='dialog'; ?>
<?php include 'common_header.php'; ?>

<!-- Error Dialog -->

<div id="dialog">

    <h1><img src="<?php u('public/images/logo_32px.png'); ?>" alt="nearlyfreemail" /></h1>
    <h2><?php e($title); ?></h2>
    
    <div class="rounded box">
    
        <p><?php e($message, true); ?></p>
        
        <p class="margin center"><br /><a href="javascript:history.back()">Go Back</a></p>
    
    </div>
    
</div>

<?php include 'common_footer.php'; ?>
