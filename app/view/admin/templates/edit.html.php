<?php

use Goteo\Library\Text;

?>
<p><strong><?php echo $this['template']->name; ?></strong>: <?php echo $this['template']->purpose; ?></p>

<div class="widget board">
    <form method="post" action="/admin/templates/edit/<?php echo $this['template']->id; ?>">
        <input type="hidden" name="group" value="<?php echo $this['template']->group; ?>" />
        <p>
            <label for="tpltitle">Título:</label><br />
            <input id="tpltitle" type="text" name="title" size="120" value="<?php echo $this['template']->title; ?>" />
        </p>

        <p>
            <label for="tpltext">Contenido:</label><br />
            <textarea id="tpltext" name="text" cols="100" rows="20"><?php echo $this['template']->text; ?></textarea>
        </p>

        <input type="submit" name="save" value="Guardar" />

        <p>
            <label for="mark-pending">Marcar como pendiente de traducir</label>
            <input id="mark-pending" type="checkbox" name="pending" value="1" />
        </p>

    </form>
</div>