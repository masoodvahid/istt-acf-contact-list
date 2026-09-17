(function ($) {
    'use strict';

    $(document).on('click', '.rdsco-category-image-select', function (event) {
        event.preventDefault();

        const picker = $(this).closest('.rdsco-category-image-picker');
        const frame = wp.media({
            title: 'انتخاب تصویر دسته',
            button: { text: 'استفاده از این تصویر' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            const imageUrl = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            picker.find('.rdsco-category-image-id').val(attachment.id);
            picker.find('.rdsco-category-image-preview').html(
                '<img src="' + imageUrl + '" alt="" style="width:100%;height:100%;object-fit:cover;">'
            );
        });

        frame.open();
    });

    $(document).on('click', '.rdsco-category-image-remove', function (event) {
        event.preventDefault();
        const picker = $(this).closest('.rdsco-category-image-picker');
        picker.find('.rdsco-category-image-id').val('');
        picker.find('.rdsco-category-image-preview').empty();
    });
})(jQuery);
