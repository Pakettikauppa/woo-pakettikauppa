jQuery(document).ready( function($) {
    /* Manifest actions */
    $('.manifest_action').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        var table = $(this).parents('table');
        var valid = true;
        table.find('input[type=text]').each(function(){
            if ($(this).val()) {
                $(this).removeClass('error');
            } else {
                valid = false;
                $(this).addClass('error');
            }
        });
        if (valid) {
            var data = {
                action: 'pk_manifest_call_courier',
                date: table.find(".manifest-date" ).val(),
                time_from: table.find(".manifest-time-from" ).val(),
                time_to: table.find(".manifest-time-to" ).val(),
                additional_info: table.find(".manifest-additional-info" ).val(),
                id: $(this).attr('data-id')
              };
            jQuery.post(ajaxurl, data, function(response) {
                if (response.error !== undefined) {
                    alert(response.error);
                } else {
                    location.reload();
                }
                btn.prop('disabled', false);
            }, 'json');
        }
    });
    $( ".manifest-date" ).datetimepicker({
        timepicker:false,
        format:'Y-m-d',
        minDate:0
    });
    $( ".manifest-time-from" ).datetimepicker({
        datepicker:false,
        format:'H:i',
        onShow:function( ct, el ){
         var to_val = el.parents('tr').find(".manifest-time-to").val();
         this.setOptions({
          maxDate:to_val?to_val:false
         });
        }
    });

    $( ".manifest-time-to" ).datetimepicker({
        datepicker:false,
        format:'H:i',
        onShow:function( ct, el ){
         var from_val = el.parents('tr').find(".manifest-time-from").val();
         this.setOptions({
          minTime:from_val?from_val:false
         });
        }
    });
    $('.xdsoft_datetimepicker').css('z-index', 999999999);
} );