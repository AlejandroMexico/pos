$('#id_btn_tranfer').click(function () {
    var tranf_money = parseFloat(ajaxDataSend('getMoneyCash', null));
    if (tranf_money == 0) {
        _TPV.showError(_TPV.translations['ErrTranferAmmountis0']);
        return;
    }
    $('#id_terminal_cash_available').val(displayPrice(tranf_money));
    $('#id_money_tranfer').val(displayPrice(tranf_money));
    $('#idTransf').dialog({modal: true});
    $('#idTransf').dialog({width: 440});
    $('#id_btn_tranfer_money').unbind('click');

    $('#id_btn_tranfer_money').click(function () {
        var tranfer = new Object();
        tranfer.ammount = $('#id_money_tranfer').val()    
        if (tranfer.ammount > tranf_money ) {    
            _TPV.showError(_TPV.translations['ErrTranferAmmountExceedAvailable']);
        }
        else {
            var result = ajaxDataSend('doTranfer', tranfer);
            if (result != null && typeof result != 'undefined'){
                if (result['line_from'] != null && typeof result['line_from'] != 'undefined'){    
                    if (_TPV.defaultConfig['module']['print'] > 0 || _TPV.defaultConfig['module']['mail'] > 0) {
                        $('#idTransf').dialog("close");
                        $('#idCashMode').dialog({modal: true});
                        $('#idCashMode').dialog({width: 400});

                        $('#id_btn_cashPrint').click(function () {
                            $('#id_btn_cashPrint').unbind('click');
                            _TPV.printing('tranfer', result);
                            $('#idCashMode').dialog("close");
                        });    
                        
                    
                    }
                }
            }                 
        }
    });
});