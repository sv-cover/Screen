<?php
require_once 'include/init.php';

$model = get_model('DataModelFotoboek');

$boek = $model->get_random_book();

$fotos = $model->get_photos($boek);

shuffle($fotos);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cover Photoalbum</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="collage">
    <h1 class="text-outline-thick-white"><?=$boek->get('titel')?></h1>
    <ul class="flow-gallery">
        <?php foreach (array_slice($fotos, 0, 30) as $foto): ?>
        <li class="foto">
            <img src="<?=markup_format_attribute($foto->get_url(null,400))?>" <?=vsprintf('width="%d" height="%d"', $foto->get_scaled_size(null,400))?>>
            <span class="description"><?=markup_format_text($foto->get('beschrijving'))?></span>
        </li>
        <?php endforeach ?>
    </ul>
</div>

<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<script src="FlowGallery.js"></script>
<script>
function layout_photos(slide)
{
    $('.flow-gallery').each(function() {
        var $area = $(this);

        var items = $(this).find('li').map(function() {
            var $thumb = $(this).find('img').first(),
                tw = parseInt($thumb.attr('width')),
                th = parseInt($thumb.attr('height'));

            // Scale height to 3rd of window height
            var theight = parseInt(window.innerHeight / 3),
                twidth = tw * theight / th;

            return {
                'twidth': twidth,
                'theight': theight,
                'title': '',
                'el': $(this)
            };
        }).get();

        FlowGallery.showImages($area, items);
    });
}

layout_photos();
</script>
</body>
</html>
