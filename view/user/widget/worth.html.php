<?php use Goteo\Library\Text; ?>
<div class="widget worthcracy user-worthcracy"> 
<h3 class="title"><?php echo Text::get('profile-my_worth-header'); ?></h3>
<?php if (isset($this['amount'])) : ?>
    <div class="worth-amount"><?php echo $this['amount'] ?></div>
<?php endif ?>
<?php include 'view/worth/base.html.php' ?>
</div>