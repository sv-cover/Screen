$(document).on('click', 'a[data-popup]', function(e) {
    if (e.shiftKey || e.ctrlKey || e.metaKey)
        return;

    e.preventDefault();

    var $link = $(this);
    var $placeholder = $('<div class="modal">').appendTo(document.body);

    if($link.find('i.fa').length){
        var $icon = $link.find('i.fa').first();
        $icon.data('icon', $icon.attr('class'));
        $icon.removeClass();
        $icon.addClass('fa fa-circle-o-notch fa-spin fa-fw');
    }else{
        $link.button('loading');
    }

    switch ($link.data('popup')){
        case 'modal':
            $placeholder.load($link.prop('href') + ' .modal-dialog', function() {
                $placeholder.modal('show');
            });
            break;

        default:
            throw Error("Unknown type '" + $link.data('popup') + "'");
    }

    $placeholder.on('click', '*[data-dismiss=modal]', function(e) {
        e.preventDefault();
        $placeholder.modal('hide');
    });

    $placeholder.on('shown.bs.modal', function() {
        $placeholder.trigger('partial-ready');
    });

    // Remove dialog from HTML when hidden
    $placeholder.on('hidden.bs.modal', function() {
        if($link.find('i.fa').length){
            var $icon = $link.find('i.fa').first();
            $icon.removeClass();
            $icon.addClass($icon.data('icon'));
        }else{
            $link.button('reset');
        }
        $placeholder.remove();
    });
});
