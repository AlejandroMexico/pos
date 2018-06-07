// CLASS CUSTOMER *******************************************************************
var Customer = jQuery.Class({
    init: function () {
        this.id = 0;
        this.nom = '';
        this.prenom = '';
        this.idprof1 = '';
        this.address = '';
        this.cp = '';
        this.ville = '';
        this.tel = '';
        this.email = '';
        this.discount = 0;
    }
});
// CLASS PRODUCT ********************************************************
var Product = jQuery.Class({
    init: function () {
        this.id = 0;
        this.label = '';
        this.price_ttc = 0;
        this.ref = '';
        this.tax = 0;
        this.price_min_ttc = 0;

    }
});
//CLASS CASH ************************************************************
var Cash = jQuery.Class({
    init: function () {
        this.moneyincash = 0;
        this.type = 1;
        this.printer = 1;
        this.employeeId = 0;
        this.mail = 1;
    }
});
// CLASS TPV  ***********************************************************
var TPV = jQuery.Class({
    init: function () {
        this.categories = new Array();
        this.products = new Array();
        this.places = new Array();
        this.activeIdProduct = 0;
        this.customerId = 0;
        this.employeeId = 0;
        this.barcode = 0;
        this.defaultConfig = new Array();
        this.ticketState = 0; // 0 => Normal, 1 => Blocked to add products, 2 => Return products
        this.cash = new Cash();
        this.cashId = 0;
        this.warehouseId = 0;
        this.fullscreen = 0;
        this.faclimit = 0;
        this.discount;
        this.points = 0;
        //this.coupon = 0;
        this.showingProd = 0;
        this.translations = new Array();
        this.ticket = new Ticket();
    },
    setButtonEvents: function () {

        $.getScript('js/tranfer.js');
      
        $('#btnNewTicket').click(function () {
            _TPV.ticket.newTicket();
        });

        $('#btnOkTicket').click(function () {
            _TPV.ticket.okTicket();
        });
        $('#btnHistory').click(function () {
            _TPV.getHistory();
        });
        $('#btnSaveTicket').click(function () {
            _TPV.ticket.saveTicket();
        });
        $('#btnCancelTicket').click(function () {
            _TPV.ticket.cancelTicket();
        });
        $('#btnReturnTicket').click(function () {
            if (_TPV.ticket.type==2) {
                _TPV.showError(_TPV.translations['InvoiceIsAReturnInvocice']);
            }
            var sourceTicket = new Ticket();
            jQuery.extend(true, sourceTicket, _TPV.ticket);
            _TPV.ticket.newTicket();
            jQuery.extend(true, _TPV.ticket, sourceTicket);
            _TPV.ticket.id = 0;
            _TPV.ticket.idsource = sourceTicket.id;
            _TPV.ticket.oldproducts = sourceTicket.lines;
            _TPV.ticketState = 2;
            _TPV.ticket.type = 1;
            _TPV.ticket.setReturnStock();
            if (_TPV.ticket.lines.length == 0 || _TPV.ticket.lines == null ) {
                _TPV.showError(_TPV.translations['NoItemsToReturn']);
                return;
            }
            _TPV.ticket.showLines();
            _TPV.setButtonState(true);
            _TPV.ticket.showCustomerInfo();
        });
        $('#btnViewTicket').click(function () {
            _TPV.ticket.viewTicket();
        });
        $('#btnAddCustomer').click(function () {
            _TPV.ticket.showAddCustomer();
        });
        $('#btnNewCustomer').click(function () {
            _TPV.ticket.showAddCustomer();
        });
        $('#btnAddDiscount').click(function () {
            _TPV.ticket.addDiscount();
        });
        $('#btnAddProduct').click(function () {
            _TPV.ticket.showAddProduct();
        });
        $('#btnTicketNote').click(function () {
            _TPV.ticket.addTicketNote();
        });
        $('#btnShowManualProducts').click(function () {
            _TPV.ticket.showManualProducts();
        });
        $('#btnLogout').click(function () {
            window.location.href = "./disconect.php";
        });
        $('#btnZoomCategories').click(function () {
            _TPV.ticket.showZoomProducts();
        });
        $('#btnHideInfo').click(function () {
            $('#short_description_content').toggle();
        });
        $('#btnHideInfoSt').click(function () {
            $('#short_description_content_st').toggle();
        });
        // Filter Product Search Events
        $('#id_product_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchProduct();
            }
            if (_TPV.defaultConfig['terminal']['barcode'] == 1) {
                $('#id_product_search').focus();
            }
        });
        $('#img_product_search').click(function () {
            _TPV.searchProduct();
        });
        // Filter Sotck products Search Events
        $('#id_stock_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchByStock(1, _TPV.warehouseId);
            }
        });
        $('#img_stock_search').click(function () {
            _TPV.searchByStock(1, _TPV.warehouseId);
        });
        // Filter Cusotmer Search
        $('#id_customer_search_').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchCustomer();
            }
        });
        $('#img_customer_search').click(function () {
            _TPV.searchCustomer();
        });
        $('#tabStock').click(function () {
            $('#info_product_st').hide();
            _TPV.countByStock();
            //_TPV.searchByStock();
        });
        $('#tabPlaces').click(function () {
            _TPV.searchByPlace();
        });
        $('#id_place_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchByPlace();
            }
        });
        $('#tabHistory').click(function () {
            _TPV.searchByRef(-1);
            _TPV.countByRef();
        });
        $('#tabHistoryFac').click(function () {
            _TPV.searchByRefFac(-1);
            _TPV.countByRefFac();
        });

        // Filter Reference Search
        $('#id_ref_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchByRef(-1);
            }
        });
        $('#img_ref_search').click(function () {
            _TPV.searchByRef(-1);
        });
        $('#id_ref_fac_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.searchByRefFac(-1);
            }
        });
        $('#img_ref_fac_search').click(function () {
            _TPV.searchByRefFac(-1);
        });

        $('#id_ref_coupon_search').live("keypress", function (e) {
            if (e.keyCode == 13 || e.which == 13) {
                _TPV.ticket.searchCoupon();
            }
        });
        $('#img_ref_coupon_search').click(function () {
            _TPV.ticket.searchCoupon();
        });

        $('#id_selectProduct').change(function () {
            if ($(this).val() != 0) {
                _TPV.ticket.addLine($(this).val());
                $('#divSelectProducts').hide();
                $('#id_product_search').val('');
            }
        });
		/*$('.payment_types').each(function(){
		 $(this).click(function() {
		 $('#payment_coupon').hide();
		 if(_TPV.points != null && _TPV.ticket.mode!=0){
		 $('#payment_points').show();
		 $('#payment_total_points').hide();
		 }
		 else
		 {$('#payment_total_points').show();}
		 $('#id_btn_add_ticket').show();
		 $('.payment_types').removeClass('btnon');
		 $(this).addClass('btnon');
		 _TPV.ticket.setPaymentType($(this).find('a:first').attr('id').substring(7));
		 });
		 });*/
        $('.series_types').each(function () {
            $(this).click(function () {

                $('.series_types').removeClass('btnon');
                $(this).addClass('btnon');
                _TPV.ticket.serie = $(this).find('a:first').attr('id').substring(9);
                showLeftContent('#payType');
                _TPV.ticket.showTotalBlock();
            });
        });
        $('#id_btn_coupon').click(function () {
            _TPV.ticket.showCoupons();
        });
        $('#line_quantity').keyup(function () {//normal mode
            _TPV.checkStock();
        });
        $('#line_quantity').blur(function () {//tactil mode
            _TPV.checkStock();
        });
        $('#points_client_id').keyup(function () {//normal mode
            _TPV.pointsClient();
        });
        $('#points_client_id').blur(function () {//tactil mode
            _TPV.pointsClient();
        });
        $('#pay_client_0').keyup(function () {//normal mode
            _TPV.payClient();
        });
        $('#pay_client_0').blur(function () {//tactil mode
            _TPV.payClient();
        });
        $('#pay_client_1').keyup(function () {//normal mode
            _TPV.payClient();
        });
        $('#pay_client_1').blur(function () {//tactil mode
            _TPV.payClient();
        });
        $('#pay_client_2').keyup(function () {//normal mode
            _TPV.payClient();
        });
        $('#pay_client_2').blur(function () {//tactil mode
            _TPV.payClient();
        });
        $('#pay_client_ret_0').keyup(function () {//normal mode
            _TPV.payClientRet();
        });
        $('#pay_client_ret_0').blur(function () {//tactil mode
            _TPV.payClientRet();
        });
        $('#pay_client_ret_1').keyup(function () {//normal mode
            _TPV.payClientRet();
        });
        $('#pay_client_ret_1').blur(function () {//tactil mode
            _TPV.payClientRet();
        });
        $('#pay_client_ret_2').keyup(function () {//normal mode
            _TPV.payClientRet();
        });
        $('#pay_client_ret_2').blur(function () {//tactil mode
            _TPV.payClientRet();
        });
        $('#pay_all_0').click(function () {//tactil mode
            if ($('#pay_client_0').val() != "")
                var prev = $('#pay_client_0').val();
            else
                var prev = 0;
            $('#pay_client_0').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClient();
        });
        $('#pay_all_1').click(function () {//tactil mode
            if ($('#pay_client_1').val() != "")
                var prev = $('#pay_client_1').val();
            else
                var prev = 0;
            $('#pay_client_1').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClient();
        });
        $('#pay_all_2').click(function () {//tactil mode
            if ($('#pay_client_2').val() != "")
                var prev = $('#pay_client_2').val();
            else
                var prev = 0;
            $('#pay_client_2').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClient();
        });
        $('#pay_all_ret_0').click(function () {//tactil mode
            if ($('#pay_client_ret_0').val() != "")
                var prev = $('#pay_client_ret_0').val();
            else
                var prev = 0;
            $('#pay_client_ret_0').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClientRet();
        });
        $('#pay_all_ret_1').click(function () {//tactil mode
            if ($('#pay_client_ret_1').val() != "")
                var prev = $('#pay_client_ret_1').val();
            else
                var prev = 0;
            $('#pay_client_ret_1').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClientRet();
        });
        $('#pay_all_ret_2').click(function () {//tactil mode
            if ($('#pay_client_ret_2').val() != "")
                var prev = $('#pay_client_ret_2').val();
            else
                var prev = 0;
            $('#pay_client_ret_2').val(displayPrice(_TPV.ticket.difpayment + parseFloat(prev)));
            _TPV.payClientRet();
        });

        $('#id_btn_tpvtactil').click(function () {
            if ($(this).hasClass('on')) {
                $(this).removeClass('on');
                $(this).addClass('off');
                _TPV.tpvTactil(false);
            }
            else {
                $(this).addClass('on');
                $(this).removeClass('off');
                _TPV.tpvTactil(true);
            }
        });
        $('#id_btn_barcode').click(function () {
            if ($(this).hasClass('on')) {
                $(this).removeClass('on');
                $(this).addClass('off');
                _TPV.barcode = 0;
            }
            else if ($(this).hasClass('off')) {
                $(this).addClass('on');
                $(this).removeClass('off');
                _TPV.barcode = 1;
            }
        });

        $('#id_btn_closeproduct').click(function () {
            $('#products').toggle();
            //$('#productSearch').toggle();
        });
        $('#id_btn_fullscreen').click(function () {

            if (_TPV.fullscreen == 0) {
                var docElm = document.documentElement;
                if (docElm.requestFullscreen) {
                    docElm.requestFullscreen();
                }
                else if (docElm.mozRequestFullScreen) {
                    docElm.mozRequestFullScreen();
                }
                else if (docElm.webkitRequestFullScreen) {
                    docElm.webkitRequestFullScreen(docElm.ALLOW_KEYBOARD_INPUT);
                }
                _TPV.fullscreen = 1;
            }
            else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
                else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                }
                else if (document.webkitCancelFullScreen) {
                    document.webkitCancelFullScreen();
                }
                _TPV.fullscreen = 0;
            }

        });
        $('#id_btn_closecash').click(function () {
            var money = ajaxDataSend('getMoneyCash', null);
            $('#id_terminal_cash').val(displayPrice(money));
            $('#id_money_cash').val('');
            $('#idCloseCash').dialog({modal: true});
            $('#idCloseCash').dialog({width: 440});
            $('#id_btn_close_cash').unbind('click');

            $('#id_btn_close_cash').click(function () {

                if ($('#id_money_cash').val())
                    _TPV.cash.moneyincash = $('#id_money_cash').val();
                _TPV.cash.employeeId = _TPV.employeeId;
                var result = ajaxDataSend('closeCash', _TPV.cash);
                $('#idCloseCash').dialog('close');
                if (!result)
                    return;
            
                if (_TPV.defaultConfig['module']['print'] > 0 || _TPV.defaultConfig['module']['mail'] > 0) {
                    $('#idCashMode').dialog({modal: true});
                    $('#idCashMode').dialog({width: 400});

                    $('#id_btn_cashPrint').click(function () {
                        $('#id_btn_cashPrint').unbind('click');
                        if (_TPV.cash.type == 1) {
                            _TPV.printing('closecash', result);
                        }
                        else {
                            _TPV.printing('arqcash', result);      
                        }    
                        $('#idCashMode').dialog("close");
                        if (_TPV.defaultConfig['module']['print_mode'] == 0 && _TPV.cash.type == 1) 
                            $('#btnLogout').click();
                    });

                    $('#id_btn_cashMail').click(function () {
                        $('#id_btn_cashMail').unbind('click');
                        _TPV.mailCash(result, _TPV.defaultConfig['terminal']['id'])
                        $('#idCashMode').dialog("close");

                    });
                }
                else {
                    if (_TPV.cash.type == 1) {
                        $('#btnLogout').click();
                    }
                }
            });
        });
        $('#btnTotalNote').click(function () {
            _TPV.showNotes();
        });
        $('#btnChangeCustomer').click(function () {
            _TPV.changeCustomer();
        });
        $('#btnChangePlace').click(function () {
            _TPV.searchByPlace();
        });
        $('.close_types').click(function () {
            $('.close_types').removeClass('btnon');
            $(this).addClass('btnon');
            _TPV.cash.type = $(this).find('a:first').attr('id').substring(9);
        });
        $('.print_close_types').click(function () {
            $('.print_close_types').removeClass('btnon');
            $(this).addClass('btnon');
            _TPV.cash.printer = $(this).find('a:first').attr('id').substring(14);
        });
        $('.mail_close_types').click(function () {
            $('.mail_close_types').removeClass('btnon');
            $(this).addClass('btnon');
            _TPV.cash.mail = $(this).find('a:first').attr('id').substring(14);
        });
        $('.type_discount').click(function () {
            $('.type_discount').removeClass('btnon');
            $(this).addClass('btnon');
            if ($(this).find('a:first').attr('id') == 'btnTypeDiscount0') {
                $('#typeDiscount0').show();
                $('#typeDiscount1').hide();
                $('#typeDiscount1').val(0);
            }
            else {
                $('#typeDiscount1').show();
                $('#typeDiscount0').hide();
                $('#typeDiscount0').val(0);
            }
        });
        $('#id_btn_employee').click(function () {


            $('#idEmployee').dialog({width: 400});
            $('#idEmployee a').unbind('click');

            $('#idEmployee a').click(function () {

                var login = $(this).attr('login');
                var userid = $(this).attr('id').substring(12);
                var username = $(this).html();
                var photo = $(this).attr('photo');

                $('#idEmpPass').dialog({modal: true});
                $('#idEmpPass').dialog({width: 400});

                $('#id_btn_empPass').unbind('click');
                $('#id_btn_empPass').click(function () {
                    var pass = new Object();
                    pass.pass = $('#password').val();
                    pass.login = login;
                    pass.userid = userid;

                    var result = ajaxDataSend('checkPassword', pass);

                    if (result > 0) {
                        _TPV.employeeId = userid;
                        $('#id_user_name').html(username);
                        $('#id_image').attr("src", photo);
                    }
                    $('#password').val('');
                    $('#idEmpPass').dialog('close');
                    $('#idEmployee').dialog('close');

                });
            });
        });
        $('#id_btn_opendrawer').click(function () {
            ajaxDataSend('addPrint', "D");
        });
    },
    setButtonState: function (hasTicket) {
        $('#btnReturnTicket').hide();
        $('#btnSaveTicket').hide();
        $('#btnAddDiscount').hide();
        $('#btnOkTicket').hide();
        $('#btnTicketNote').hide();
        $('#alertfaclim').hide();
        if (hasTicket) {
            if (this.ticketState == 0) {
                $('#btnOkTicket').show();
                $('#btnTicketNote').show();
                $('#btnSaveTicket').show();
                $('#btnAddDiscount').show();    
            }
            if (this.ticketState == 1) {
                if (this.ticket.type != 2) {
                    $('#btnReturnTicket').show();
                }        
            }
            if (this.ticketState == 2) {
                $('#btnOkTicket').show();
                $('#totalCartTicket').find('.discount_but').hide();    
            }else{
                $('#totalCartTicket').find('.discount_but').show();
            }
        }
    },
    checkStock: function () {
        cant = $('#line_quantity').val();
        if (_TPV.products[this.activeIdProduct]["stock"] != "all") {
            if (parseFloat(cant) > parseFloat(_TPV.products[_TPV.activeIdProduct]["stock"])) {
                $('#line_quantity').val(_TPV.products[_TPV.activeIdProduct]["stock"]);
            }
        }
    },
    pointsClient: function () {
        _TPV.ticket.points = $('#points_client_id').val();
        if (parseFloat(_TPV.ticket.points) > parseFloat(_TPV.points)) {
            _TPV.ticket.points = _TPV.points;
        }
        if (parseFloat(_TPV.ticket.points) * parseFloat(_TPV.defaultConfig['module']['points']) > parseFloat(_TPV.ticket.total_ttc)) {
            _TPV.ticket.points = parseFloat(_TPV.ticket.total) / parseFloat(_TPV.defaultConfig['module']['points']);
        }
        $('#points_client_id').val(_TPV.ticket.points);
        discount = _TPV.ticket.points * _TPV.defaultConfig['module']['points'];
        _TPV.ticket.total_with_points = _TPV.ticket.total_ttc - discount;

        $('.payment_total').html(displayPrice(_TPV.ticket.total_with_points));
        _TPV.ticket.calculeDifPayment();
        _TPV.ticket.customerpay = _TPV.ticket.total_with_points;
    },
    payClient: function () {
        if ($('#pay_client_0').val() != "")
            var mode1 = $('#pay_client_0').val();
        else
            var mode1 = 0;
        if ($('#pay_client_1').val() != "")
            var mode2 = $('#pay_client_1').val();
        else
            var mode2 = 0;
        if ($('#pay_client_2').val() != "")
            var mode3 = $('#pay_client_2').val();
        else
            var mode3 = 0;

        if (typeof mode1 == 'undefined') mode1 = 0; 
        if (typeof mode2 == 'undefined') mode2 = 0;
        if (typeof mode3 == 'undefined') mode3 = 0;

        _TPV.ticket.customerpay = parseFloat(mode1) + parseFloat(mode2) + parseFloat(mode3);
        _TPV.ticket.customerpay1 = parseFloat(mode1);
        _TPV.ticket.customerpay2 = parseFloat(mode2);
        _TPV.ticket.customerpay3 = parseFloat(mode3);
        _TPV.ticket.calculeDifPayment();
    },
    payClientRet: function () {
        if ($('#pay_client_ret_0').val() != "")
            var mode1 = $('#pay_client_ret_0').val();
        else
            var mode1 = 0;
        if ($('#pay_client_ret_1').val() != "")
            var mode2 = $('#pay_client_ret_1').val();
        else
            var mode2 = 0;
        if ($('#pay_client_ret_2').val() != "")
            var mode3 = $('#pay_client_ret_2').val();
        else
            var mode3 = 0;
        if (typeof mode1 == 'undefined') mode1 = 0;
        if (typeof mode2 == 'undefined') mode2 = 0;
        if (typeof mode3 == 'undefined') mode3 = 0;

        _TPV.ticket.customerpay = parseFloat(mode1) + parseFloat(mode2) + parseFloat(mode3);
        if (_TPV.ticket.customerpay > Math.min(_TPV.ticket.total_ttc, _TPV.ticket.ret_points)) {
            $('#pay_client_ret_0').val(0);
            $('#pay_client_ret_1').val(0);
            $('#pay_client_ret_2').val(0);
            _TPV.ticket.customerpay = 0;
            _TPV.ticket.customerpay1 = 0;
            _TPV.ticket.customerpay2 = 0;
            _TPV.ticket.customerpay3 = 0;
        }

        _TPV.ticket.customerpay1 = parseFloat(mode1);
        _TPV.ticket.customerpay2 = parseFloat(mode2);
        _TPV.ticket.customerpay3 = parseFloat(mode3);
        _TPV.ticket.calculeDifPayment();  
    },
    loadTicket: function (result, edit, mode = 0){
         $.each(result, function (id, item) {
            _TPV.ticket.init();
            _TPV.ticket.showCustomerInfo();
            jQuery.extend(true, _TPV.ticket, item);
            _TPV.ticket.lines = Array();
            _TPV.ticket.total_ttc = 0;
            _TPV.ticket.tot_price_ttc = 0;
            _TPV.ticket.mode = mode;
            //_TPV.ticket.id = item['id'];
            
            if (edit){
                //$('#idTotalNote').dialog("close");
                _TPV.ticketState = 0;
            }
            else{
                _TPV.ticketState = 1;    
            }
            _TPV.ticket.showCustomerInfo();
            _TPV.ticket.loadLines(item['lines']);
           
            /*$('#tablaTicket > tbody tr').remove();
            $.each(item['lines'], function (idline, line) {
                if (!edit) {
                    var newLine = new TicketLine();
                    jQuery.extend(true, newLine, line);
                    _TPV.ticket.setLine(newLine);
                    $('#tablaTicket > tbody:last').prepend(newLine.getHtmlCode());
                }
                else {
                    _TPV.ticket.addManualProduct(line);
                }
            });
            if (!edit) {
                _TPV.ticket.showTotalTicket();
            }*/
            if (item['id_place']) {
                $('#totalPlace').html(_TPV.places[item['id_place']]);
            }
            else {
                $('#totalPlace').html('');
            }

            showTicketContent();
        });        
    },
    getTicket: function (idTicket, edit) {
         
        if (typeof idTicket != 'undefined') {
            var result = ajaxDataSend('getTicket', idTicket);
            this.loadTicket(result, edit);
        }
    },
    getFacture: function (idTicket, edit) {
        if (typeof idTicket != 'undefined') {
            var result = ajaxDataSend('getFacture', idTicket);
            this.loadTicket(result, edit, 1);
        }
    },
    countByStock: function () {

        var result = ajaxDataSend('countProduct', _TPV.warehouseId);

        $('#stockNoSell').html(result["no_sell"]);

        $('#stockSell').html(result["sell"]);

        $('#stockWith').html(result["stock"]);

        $('#stockWithout').html(result["no_stock"]);

        $('#stockBest').html(result["best_sell"]);

        $('#stockWorst').html(result["worst_sell"]);
    },
    searchByStock: function (mode, warehouse) {
        var filter = new Object();
        filter.search = $('#id_stock_search').val();
        filter.mode = mode;
        filter.warehouse = warehouse;
        var result = ajaxDataSend('searchStocks', filter);
        var actionsHtml = '';
        $("#storeTable tr.data").remove();
        $.each(result, function (id, item) {
            actionsHtml = '';
            if (item['warehouseId'] == _TPV.warehouseId) {
                if (_TPV.defaultConfig['module']['pos_stock'] == 1 || item['stock'] > 0) {
                    actionsHtml = '<a class="accion addline" onclick="_TPV.ticket.addLine(' + item['id'] + ');"></a>';
                    actionsHtml += '<a class="accion moveProduct" onclick="_TPV.moveProduct(' + item['id'] + ');"></a>';
                }
            } else {
                if (_TPV.defaultConfig['module']['pos_stock'] == 1 || item['stock'] > 0) {
                    actionsHtml = '<a class="accion addline" onclick="_TPV.ticket.addLine(' + item['id'] + ');"></a>';
                }
            }
            var hide = "$('#info_product_st').toggle()";
            actionsHtml += '<a class="accion info" onclick="' + hide + '"></a>'; 
            actionsHtml += '<a class="action close" onclick="_TPV.ticket.hideStockOptions(' + item['id'] + '_' + item['warehouseId'] + ')"></a>';
            var line = '<tr id="stock' + item['id'] + '_' + item['warehouseId'] + '" onclick="_TPV.ticket.showStockOptions(' + item['id'] + ',' + item['warehouseId'] + ');_TPV.addInfoProductSt(' + item['id'] + ');" class="data">';
            line +=  '<td>' + item['id'] + '</td><td>' + item['ref'] + '</td><td>' + item['label'] + '</td><td class="cant">' + item['stock'] + '</td><td>' + item['warehouse'] + '</td><td class="colActions"  style="text-align:center">' + actionsHtml + '</tr>';           
            $('#storeTable').append( line);
                
            //$('#historyTable').append('<tr id="historyTicket'+item['id']+'" onclick="_TPV.ticket.showHistoryOptions('+item['id']+')" class="data"><td><a class="icontype state'+item['statut']+' type'+item['type']+'"></a>'+item['ticketnumber']+'</td><td>'+date+'</td><td>'+item['terminal']+'</td><td>'+item['seller']+'</td><td>'+item['client']+'</td><td style="text-align:right;">'+displayPrice(item['amount'])+'</td><td class="colActions"  style="text-align:center">'+actionsHtml+'</tr>');
        });
    },
    searchProduct: function () {
        var data = new Object;
        data['search'] = $('#id_product_search').val();
        data['warehouse'] = _TPV.warehouseId;
        data['ticketstate'] = _TPV.ticket.type;
        data['customer'] = _TPV.ticket.customerId;
        var result = ajaxDataSend('searchProducts', data);

        $("#id_selectProduct option").remove();
        $('#id_selectProduct').append(
            $('<option></option>').val(0).html('Productos ' + result.length)
        );
        $.each(result, function (id, item) {
            $('#id_selectProduct').append(
                $('<option></option>').val(item['id']).html(item['label'] + ' --> ' + displayPrice(item['price_ttc']) + '  -  ' + item['warehouseName'])
            );
        });
        if (_TPV.barcode == 1) {
            $('#id_product_search').val('');
            $('#divSelectProducts').show();

        }
        else if (result.length == 1) {

            _TPV.ticket.addLine(result[0]['id']);
            $('#divSelectProducts').hide();
            if (_TPV.defaultConfig['terminal']['barcode'] == 1) {
                $('#id_product_search').focus();
            }
            $('#id_product_search').val('');

        }
        else {
            $('#divSelectProducts').show();
        }
    },
    searchCustomer: function () {
        var result = ajaxDataSend('searchCustomer', $('#id_customer_search_').val());
        $("#customerTable_ tr.data").remove();
        var win = "$('#idChangeCustomer').dialog('close')";
        $.each(result, function (id, item) {
            $('#customerTable_').append('<tr class="data"><td class="itemId" style="display:none">' + item['id'] + '</td><td class="itemDni">' + item['profid1'] + '</td><td class="itemName">' + item['nom'] + '</td><td class="action add"><a class="action addcustomer" onclick="_TPV.ticket.addTicketCustomer(' + item['id'] + ',\'' + item['nom'] + '\',' + item['remise'] + ',' + item['points'] + ');' + win + ';"></a></td></tr>');
        });
    },
    searchByPlace: function () {
        var result = ajaxDataSend('getPlaces');
        $("#placeTable_ div").remove();


        $.each(result, function (id, item) {

            if (item['fk_ticket'] > 0) {
                _TPV.places[item['id']] = item['name'];
                $('#placeTable_').append('<div class="placeDiv placeDivFree" onclick="_TPV.getTicket(' + item['fk_ticket'] + ',true); ">' + item['name'] + '</div>');
            }
            else {
                _TPV.places[item['id']] = item['name'];
                $('#placeTable_').append('<div class="placeDiv" onclick="_TPV.ticket.newTicketPlace(' + item['id'] + '); ">' + item['name'] + '</div>');
            }
        });
        $('#idChangePlace').dialog({modal: true});
        $('#idChangePlace').dialog({width: 600});

        $('#idChangePlace').unbind('click');

        $('#idChangePlace').click(function () {

            $('#idChangePlace').dialog('close');

        });
    },
    countByRef: function () {

        var result = ajaxDataSend('countHistory', '');

        $('#histToday').html(result["today"]);

        $('#histYesterday').html(result["yesterday"]);

        $('#histThisWeek').html(result["thisweek"]);

        $('#histLastWeek').html(result["lastweek"]);

        $('#histTwoWeeks').html(result["twoweek"]);

        $('#histThreeWeeks').html(result["threeweek"]);

        $('#histThisMonth').html(result["thismonth"]);

        $('#histOneMonth').html(result["monthago"]);

        $('#histLastMonth').html(result["lastmonth"]);
    },
    countByRefFac: function () {

        var result = ajaxDataSend('countHistoryFac', '');

        $('#histFacToday').html(result["today"]);

        $('#histFacYesterday').html(result["yesterday"]);

        $('#histFacThisWeek').html(result["thisweek"]);

        $('#histFacLastWeek').html(result["lastweek"]);

        $('#histFacTwoWeeks').html(result["twoweek"]);

        $('#histFacThreeWeeks').html(result["threeweek"]);

        $('#histFacThisMonth').html(result["thismonth"]);

        $('#histFacOneMonth').html(result["monthago"]);

        $('#histFacLastMonth').html(result["lastmonth"]);
    },
    searchByRef: function (stat) {
        var filter = new Object();
        filter.search = $('#id_ref_search').val();
        filter.stat = stat;
        var result = ajaxDataSend('getHistory', filter);
        $("#historyTable tr.data").remove();


        $.each(result, function (id, item) {
            var edit = false;
            var delet = false;
            var actionsHtml = '';
            var strticket = "'ticket'";
            if (item['statut'] == 0) {
                edit = true;
                delet = true;
            }
            strticketgift = "'giftticket'";
            var date = '-';
            if (item['date_close'].length > 0 && item['date_close'] != '')
                date = item['date_close'];
            else if (item['date_creation'].length > 0 && item['date_creation'] != '')
                date = item['date_creation'];
            var blocked = '';
            var strticket = "'ticket'";
            if (item['type'] == 1)
                blocked = '_TPV.ticketState=1;';


            actionsHtml += '<a class="action edit" onclick="' + blocked + '_TPV.getTicket(' + item['id'] + ',' + edit + ');"></a>';

            if (delet) {
                actionsHtml += '<a class="action delete" onclick="_TPV.deletTicket(' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['print'] > 0) {
                actionsHtml += '<a class="action print" onclick="_TPV.printing(' + strticket + ',' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['print'] > 0 && !delet) {
                actionsHtml += '<a class="action printgift" onclick="_TPV.printing(' + strticketgift + ',' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['mail'] > 0) {
                actionsHtml += '<a class="action mail" onclick="_TPV.mailTicket(' + item['id'] + ');"></a>';
            }
            actionsHtml += '<a class="action close" onclick="_TPV.ticket.hideHistoryOptions(' + item['id'] + ')"></a>';
            $('#historyTable').append('<tr id="historyTicket' + item['id'] + '" onclick="_TPV.ticket.showHistoryOptions(' + item['id'] + ')" class="data"><td><a class="icontype state' + item['statut'] + ' type' + item['type'] + '"></a>' + item['ticketnumber'] + '</td><td>' + date + '</td><td>' + item['terminal'] + '</td><td>' + item['seller'] + '</td><td>' + item['client'] + '</td><td style="text-align:right;">' + displayPrice(item['amount']) + '</td><td class="colActions"  style="text-align:center">' + actionsHtml + '</tr>');
        });
    },
    searchByRefFac: function (stat) {
        var filter = new Object();
        filter.search = $('#id_ref_fac_search').val();
        filter.stat = stat;
        var result = ajaxDataSend('getHistoryFac', filter);
        $("#historyFacTable tr.data").remove();


        $.each(result, function (id, item) {
            var edit = false;
            var delet = false;
            var actionsHtml = '';
            var strticket = "'facture'";
            if (item['statut'] == 0) {
                edit = true;
                delet = true;
                strticket = "'ticket'";
            }
            strticketgift = "'giftfacture'";
            var date = '-';
            if (item['date_close'].length > 0 && item['date_close'] != '')
                date = item['date_close'];
            else if (item['date_creation'].length > 0 && item['date_creation'] != '')
                date = item['date_creation'];
            var blocked = '';
            if (item['type'] == 1)
                blocked = '_TPV.ticketState=1;';

            if (delet)
                actionsHtml += '<a class="action edit" onclick="' + blocked + '_TPV.getTicket(' + item['id'] + ',' + edit + ');"></a>';
            else
                actionsHtml += '<a class="action edit" onclick="' + blocked + '_TPV.getFacture(' + item['id'] + ',' + edit + ');"></a>';

            if (delet) {
                actionsHtml += '<a class="action delete" onclick="_TPV.deletTicket(' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['print'] > 0) {
                actionsHtml += '<a class="action print" onclick="_TPV.printing(' + strticket + ',' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['print'] > 0 && !delet) {
                actionsHtml += '<a class="action printgift" onclick="_TPV.printing(' + strticketgift + ',' + item['id'] + ');"></a>';
            }
            if (_TPV.defaultConfig['module']['mail'] > 0 && !delet) {
                actionsHtml += '<a class="action mail" onclick="_TPV.mailFacture(' + item['id'] + ');"></a>';
            }
            else if (_TPV.defaultConfig['module']['mail'] > 0 && delet) {
                actionsHtml += '<a class="action mail" onclick="_TPV.mailTicket(' + item['id'] + ');"></a>';
            }
            actionsHtml += '<a class="action close" onclick="_TPV.ticket.hideHistoryFacOptions(' + item['id'] + ')"></a>';
            $('#historyFacTable').append('<tr id="historyFacTicket' + item['id'] + '" onclick="_TPV.ticket.showHistoryFacOptions(' + item['id'] + ')" class="data"><td><a class="icontype state' + item['statut'] + ' type' + item['type'] + '"></a>' + item['ticketnumber'] + '</td><td>' + date + '</td><td>' + item['terminal'] + '</td><td>' + item['seller'] + '</td><td>' + item['client'] + '</td><td style="text-align:right;">' + displayPrice(item['amount']) + '</td><td class="colActions"  style="text-align:center">' + actionsHtml + '</tr>');
        });
    },
    showNotes: function () {
        var result = ajaxDataSend('getNotes', 1);
        $("#noteTable tr.data").remove();
        var blocked = ' ';
        var idtick = 0;
        $.each(result, function (id, item) {
            if (item['ticketid'] != idtick) {
                idtick = item['ticketid'];
                $('#noteTable').append('<tr class="data"><td class="itemId" style="display:none">' + item['id'] + '</td><td width=10px; id="noteCabe">' + item['ticketnumber'] + '</td><td colspan=2 id="noteCabe">' + item['note'] + '</td><td width=10px; id="noteCabe"><a class="action addNote" onclick="' + blocked + '_TPV.getTicket(' + item['ticketid'] + ',true);" ></a></td></tr>');
            }
            else {
                $('#noteTable').append('<tr class="data"><td class="itemId" style="display:none">' + item['id'] + '</td><td colspan=2>' + item['description'] + '</td><td colspan=2>' + item['note'] + '</td></tr>');
            }
        });
        $('#idTotalNote').dialog({modal: true});
        $('#idTotalNote').dialog({height: 450, width: 600});
    },
    changeCustomer: function () {
        $('#idChangeCustomer').dialog({modal: true});
        $('#idChangeCustomer').dialog({height: 450, width: 600});
    },
    getDataCategories: function (category) {
        this.getCategories(category);
    },
    getCategories: function (category) {
        $('#products').html('');

        var categories = this.categories;
        $.getJSON('./ajax_pos.php?action=getCategories&parentcategory=' + category, function (data) {

            if (category != 0) {
                $('#products').append('<div align="center" onclick="_TPV.getDataCategories(' + categories[category]['parent'] + ')" id="category_' + categories[category]['parent'] + '" title="Up" class="botonCategoria">'
                    + '<div align="center"></div>UP</div>');
            }

            $.each(data, function (key, val) {
                if (categories[val.id] == undefined) {
                    categories[val.id] = val;
                    categories[val.id]['parent'] = category;
                }
                $('#products').append('<div align="center" onclick="_TPV.getDataCategories(' + val.id + ')" id="category_' + val.id + '" title="' + val.label + '" class="botonCategoria">'
                    + '<div align="center"><img border="0" alt="" src="' + val.image + '"></div>' + val.label + '</div>');

            }, _TPV.getProducts(category));
            $('#showProducts').hide();    
            $('#hideProducts').show();    
        });
    },
    loadMoreProducts: function (category, pag) {
        //_TPV.showingProd = 0;
        $('#btnLoadMore').detach();
        var products = this.products;
        var categories = this.categories;
        var addProducts = true;

        $.getJSON('./ajax_pos.php?action=getMoreProducts&category=' + category + '&pag=' + pag + '&ticketstate=' + _TPV.ticket.type, function (data) {
            $.each(data, function (key, val) {
                if (products[val.id] == undefined)
                    products[val.id] = val;
                if (addProducts && categories[category] != undefined) {
                    var arrayItem = categories[category]['products'].length;
                    categories[category]['products'][arrayItem] = val.id;
                }
                $('#products').append('<div onclick="_TPV.ticket.addLine(' + val.id + ');_TPV.go_up();" align="center" id="produc_' + val.id + '"  class="botonProducto">'
                    + '<div align="center"><a ><img border="0"  src="' + val.thumb + '"></a></div>' + val.label + '</div>');
                _TPV.showingProd++;
            });
            if (_TPV.showingProd % 10 == 0 && _TPV.showingProd > 0) {
                var txt = ajaxDataSend('Translate', 'More');
                $('#products').append('<div class="butProd" id="btnLoadMore" onclick="_TPV.loadMoreProducts(' + category + ',' + _TPV.showingProd + ')">' + txt + '</div>');
            }
        });
    },
    getProducts: function (category) {
        _TPV.showingProd = 0;
        var products = this.products;
        var categories = this.categories;
        if (typeof category != 'undefined') {
            var addProducts = false;
            if (categories[category] != undefined) {
                if (categories[category]['products'] != undefined) {
                    categoryProducts = this.categories[category]['products'];

                    $.each(categoryProducts, function (key, val) {
                        product = products[val];
                        $('#products').append('<div onclick="_TPV.ticket.addLine(' + product.id + ');_TPV.go_up();" align="center" id="produc_' + product.id + '" class="botonProducto">'
                            + '<div align="center"><a ><img border="0"  src="' + product.thumb + '"></a></div>' + product.label + '</div>');
                        _TPV.showingProd++;
                    });
                    if (_TPV.showingProd % 10 == 0 && _TPV.showingProd > 0) {
                        var txt = ajaxDataSend('Translate', 'More');
                        $('#products').append('<div class="butProd" id="btnLoadMore" onclick="_TPV.loadMoreProducts(' + category + ',' + _TPV.showingProd + ')">' + txt + '</div>');
                    }
                    return;
                }
                else {
                    addProducts = true;
                    categories[category]['products'] = new Array();
                }
            }

            $.getJSON('./ajax_pos.php?action=getProducts&category=' + category + '&ticketstate=' + _TPV.ticket.type, function (data) {
                $.each(data, function (key, val) {
                    if (products[val.id] == undefined)
                        products[val.id] = val;
                    if (addProducts) {
                        var arrayItem = categories[category]['products'].length;
                        categories[category]['products'][arrayItem] = val.id;
                    }
                    $('#products').append('<div onclick="_TPV.ticket.addLine(' + val.id + ');_TPV.go_up();" align="center" id="produc_' + val.id + '"  class="botonProducto">'
                        + '<div align="center"><a ><img border="0"  src="' + val.thumb + '"></a></div>' + val.label + '</div>');
                    _TPV.showingProd++;
                });
                if (_TPV.showingProd % 10 == 0 && _TPV.showingProd > 0) {
                    var txt = ajaxDataSend('Translate', 'More');
                    $('#products').append('<div class="butProd" id="btnLoadMore" onclick="_TPV.loadMoreProducts(' + category + ',' + _TPV.showingProd + ')">' + txt + ' </div>');
                }
            });
        }
    },
    go_up: function () {

        $("div.ticket_content").animate({scrollTop: 0}, "slow");
        return false;
    },
    printing: function (type, id) {
        //$(".btnPrint").printPage();
        switch (type) {
            case 'ticket':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/ticket.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "T" + id);
                break;
            case 'facture':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/facture.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "F" + id);
                break;
            case 'giftticket':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/giftticket.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "G" + id);
                break;
            case 'giftfacture':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/giftfacture.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "J" + id);
                break;
            case 'closecash':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/closecash.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "C" + id);
                break
            case 'arqcash':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/arqcash.tpl.php?id=' + id);
                else
                    ajaxDataSend('addPrint', "C" + id);
                break
            case 'tranfer':
                if (_TPV.defaultConfig['module']['print_mode'] == 0)
                    $(".btnPrint").attr('href', 'tpl/tranfer.tpl.php?from=' + id.line_from +'&to='+ id.line_to);
                else
                    ajaxDataSend('addPrint', "C" + id);
                break
                
        }

        if (_TPV.defaultConfig['module']['print_mode'] == 0) {
            var windowSizeArray = ["width=0,height=0",
                "width=0,height=0,scrollbars=no"];

            $('.btnPrint').click(function (event) {
                $('.btnPrint').unbind('click');
                var url = $(this).attr("href");
                var windowName = "_blank";//$(this).attr("name");popup
                var windowSize = windowSizeArray[0];

                window.open(url, windowName, windowSize);

                event.preventDefault();

            });
            $(".btnPrint").click();
        }
    },
    validateMail: function (valor) {

        if (/^[0-9a-z_\-\.]+@[0-9a-z\-\.]+\.[a-z]{2,4}$/i.test(valor)) {
            return true;
        }
        else {
            var txt = ajaxDataSend('Translate', 'MailError');
            _TPV.showError(txt);
            return false;
        }
    },
    mailTicket: function (idTicket) {
        $('#mail_to').val("");
        $('#idSendMail').dialog({modal: true});
        $('#idSendMail').dialog({width: 400});
        $('#id_btn_ticketLine').unbind('click');
        $('#id_btn_ticketLine').click(function () {
            var email = new Object();
            email.idTicket = idTicket;
            email.mail_to = $('#mail_to').val();

            if (_TPV.validateMail($('#mail_to').val())) {
                var result = ajaxDataSend('SendMail', email);
            }
            $('#idSendMail').dialog("close");
        });
    },
    mailFacture: function (idFacture) {
        $('#mail_to').val("");
        $('#idSendMail').dialog({modal: true});
        $('#idSendMail').dialog({width: 400});
        $('#id_btn_ticketLine').unbind('click');
        $('#id_btn_ticketLine').click(function () {
            var email = new Object();
            email.idFacture = idFacture;
            email.mail_to = $('#mail_to').val();

            if (_TPV.validateMail($('#mail_to').val())) {
                var result = ajaxDataSend('SendMail', email);
            }
            $('#idSendMail').dialog("close");
        });
    },
    mailCash: function (idCloseCash) {
        $('#mail_to').val("");
        $('#idSendMail').dialog({modal: true});
        $('#idSendMail').dialog({width: 400});
        $('#id_btn_ticketLine').unbind('click');
        $('#id_btn_ticketLine').click(function () {
            var email = new Object();
            email.idCloseCash = idCloseCash;
            email.mail_to = $('#mail_to').val();

            if (_TPV.validateMail($('#mail_to').val())) {
                var result = ajaxDataSend('SendMail', email);
            }

            $('#idSendMail').dialog("close");
            $('#btnLogout').click();
        });
    },
    deletTicket: function (idTicket) {
        $('#delete').val("");
        $('#idTicketDelet').dialog({modal: true});
        $('#idTicketDelet').dialog({width: 400});
        $('#id_btn_ticketYes').click(function () {
            $('#id_btn_ticketYes').unbind('click');
            var result = ajaxDataSend('deleteTicket', idTicket);
            _TPV.searchByRef(-1);
            _TPV.searchByRefFac(-1);

            $('#idTicketDelet').dialog("close");
        });

        $('#id_btn_ticketNo').click(function () {
            $('#id_btn_ticketNo').unbind('click');

            $('#idTicketDelet').dialog("close");
        });
    },
    showInfo: function (error) {
        $('#infoText').html(error);
        $('#idPanelInfo').dialog({modal: true});
        $('#idPanelInfo').dialog({width: 500, height: 200});
        setTimeout(function () {
            $('#idPanelInfo').dialog("close")
        }, 3000);
    },
    showError: function (error) {
        $('#errorText').html(error);
        $('#idPanelError').dialog({modal: true});
        $('#idPanelError').dialog({width: 500, height: 200});
        setTimeout(function () {
            $('#idPanelError').dialog("close")
        }, 6000);
    },
    addInfoProduct: function (idProduct) {
        $('#short_description_content').hide();
        $('#info_product').show();
        this.activeIdProduct = idProduct;
        product = this.products[idProduct];
        $('#info_product').find('#our_label_display').html(product.label);
        $('#info_product').find('#short_description_content').html(product.description);
        if (product.description) {
            $('#btnHideInfo').show();
        }
        else {
            $('#btnHideInfo').hide();
        }
        var price = new Number(product.price_ttc);
        price = price.toFixed(2);
        var price_min = new Number(product.price_min_ttc);
        price_min = price_min.toFixed(2);
        $('#info_product').find('#our_price_display').html(price);
        if (price_min > 0) {
            $('#info_product').find('#our_price_min_display').html(price_min);
            $('#our_price_min').show();
        }
        else {
            $('#our_price_min').hide();
        }
        $('#info_product').find('#bigpic').attr({src: product.image});
        $('#info_product').find('#hiddenIdProduct').val(idProduct);
    },
    addInfoProductSt: function (idProduct) {
        $('#short_description_content_st').hide();
        $('#info_product_st').show();
        this.activeIdProduct = idProduct;
        if (typeof this.products[idProduct] == 'undefined') {
            var info = new Object();
            info['product'] = idProduct;
            info['customer'] = _TPV.ticket.customerId;
            info['discount_global'] = ticket.discount_global;

            var result = ajaxDataSend('getProduct', info);
            if (result.length > 0)
                this.products[idProduct] = result[0];
        }
        product = this.products[idProduct];
        $('#info_product_st').find('#our_label_display_st').html(product.label);
        $('#info_product_st').find('#short_description_content_st').html(product.description);
        if (product.description) {
            $('#btnHideInfoSt').show();
        }
        else {
            $('#btnHideInfoSt').hide();
        }
        var price = new Number(product.price_ttc);
        price = price.toFixed(2);
        var price_min = new Number(product.price_min_ttc);
        price_min = price_min.toFixed(2);
        $('#info_product_st').find('#our_price_display_st').html(price);
        if (price_min > 0) {
            $('#info_product_st').find('#our_price_min_display_st').html(price_min);
            $('#our_price_min_st').show();
        }
        else {
            $('#our_price_min_st').hide();
        }
        $('#info_product_st').find('#bigpic').attr({src: product.image});
        $('#info_product_st').find('#hiddenIdProduct').val(idProduct);
    },
    loadConfig: function () {

        var result = ajaxDataSend('getPlaces');

        if (result) {
            _TPV.places[null] = '';
            $.each(result, function (id, item) {
                _TPV.places[item['id']] = item['name'];
            });
        }
        var result = ajaxDataSend('getConfig', null);
        if (result) {
            this.defaultConfig = result;
            $('#id_user_name').html(result['user']['name']);
            $('#id_user_terminal').html(result['terminal']['name']);
            $('#infoCustomer').html(result['customer']['name']);
            $('#infoCustomer_').html(result['customer']['name']);
            $('#id_image').attr("src", result['user']['photo']);
            _TPV.customerId = result['customer']['id'];
            _TPV.employeeId = result['user']['id'];
            _TPV.warehouseId = result['terminal']['warehouse'];
            _TPV.faclimit = result['terminal']['faclimit'];
            _TPV.discount_global = result['customer']['remise'];
            _TPV.points = result['customer']['points'];
            //_TPV.coupon = result['customer']['coupon'];
            _TPV.uselocaltax1_rate = result['customer']['uselocaltax1_rate'];
            _TPV.uselocaltax2_rate = result['customer']['uselocaltax2_rate'];
            _TPV.cashId = result['terminal']['id'];

            if (result['terminal']['tactil'] == 1) {
                _TPV.tpvTactil(true);
            }
            else {
                _TPV.tpvTactil(false);
            }
            if (result['terminal']['barcode'] == 1) {
                $('#id_product_search').focus();
            }
        }
        var result = ajaxDataSend('getNotes', 0);
        if (result) {
            $('#totalNote_').html(result);
        }

        return;
    },
    tpvTactil: function (on) {

        if (!on) {
            //$('.quertyKeyboard').keyboard('option', 'openOn', '');
            $('[type=text]').each(function () {
                $(this).getkeyboard().destroy();
            });
            return;
        }
        else {
            $('[type=text]:not(.numKeyboard)').keyboard({
                layout: 'qwerty',
                usePreview: false,
                autoAccept: true,
                accepted: function (e, keyboard, el) {

                }
            });
            $('.numKeyboard').keyboard({
                //layout:'num',
                layout: 'custom',
                usePreview: false,
                autoAccept: true,
                customLayout: {
                    'default': [

                        '7 8 9',
                        '4 5 6',
                        '1 2 3',
                        '0 . {sign}',
                        '{bksp} {a} {c}'
                    ]
                },
                accepted: function (e, el) {

                }
            });
            return;
        }
    },
    moveProduct: function(idProduct) {
        var info = new Object();
        info['warehouse'] = this.warehouseId;
        info['id'] = "stock_tranfer_wh_to";
        var result = ajaxDataSend('getWarehosuesTo', info);
        actionsHtml = '<label>'+ _TPV.translations['Warehouse'] + '</label>';
        actionsHtml += result['select'];
        actionsHtml += '<label>'+ _TPV.translations['Units'] + '</label>';
        actionsHtml += '<input onclick="this.select()" type="text" size=4 name="stock_tranfer_qty" id="stock_tranfer_qty"></input>';    
        actionsHtml += '<input type="button" id="id_btn_stock_tranfer" value="'+ _TPV.translations['Send'] + '" class="btn3d"  onClick="_TPV.stockTranfer()"></input>';
        actionsHtml += '<input type="hidden" id="stock_tranfer_id_product" value="'+idProduct+'">';
        actionsHtml += '<a class="action close" onclick="$(\'#stockMov\').hide()"></a>';
        $('#stockMov').show();
        $('#stockMov').find('.colActions').html(actionsHtml);  
    },
    stockTranfer: function() {
        var info = new Object();
        info['wh_from'] = this.warehouseId;
        info['wh_to']= $('#stock_tranfer_wh_to').val();
        info['id_product'] = $('#stock_tranfer_id_product').val();
        info['qty'] = $('#stock_tranfer_qty').val();
        var result = ajaxDataSend('stockTranfer', info);
        
        if (typeof result != 'undefined' && typeof result == 'object') {
            $('#stockMov').find('.colActions').html('');
            $('#stockMov').hide();
            $.each(result, function(id, item){
                $('#stock' + info['id_product'] + '_' + id).find('.cant').html(item['real']);
            });
        }
    }
});

$(function() {
    _TPV.setButtonEvents();
    _TPV.tpvTactil(true);
    $.keyboard.keyaction.enter = function (base) {
        if (base.el.tagName === "INPUT") {
            //base.accept();      // accept the content
            var e = $.Event('keypress');
            e.which = 13;
            base.close(true);
            $(base.el).trigger(e);
            // same as base.accept();
            return false;
        } else {
            base.insertText('\r\n'); // textarea
        }
    };
});
$(document).ready(function() {
    _TPV.loadConfig();
    _TPV.ticket.newTicket();
    $(".numKeyboard").keypress(function (e) {
        if (window.event) { // IE
            var charCode = e.keyCode;
        } else if (e.which) { // Safari 4, Firefox 3.0.4
            var charCode = e.which
        }
        if (charCode != 8 && charCode != 0 && ((charCode < 48 && charCode != 46 && charCode != 44) || charCode > 57))
            return false;
        return true;
    });
});
var _TPV = new TPV();
$.getScript('js/calcule_product_price.js');
//_TPV.getDataCategories(0);
function removeKey(arrayName,key) {
    var tmpArray = new Array();
    for (var i = 0; i < arrayName.length; i++) {
        if (arrayName[i]['idProduct'] != key) {
            tmpArray.push(arrayName[i]);
        }
    }
    return tmpArray;
}
function ajaxSend(action) {
    var result;
    $.ajax({
        type: "POST",
        url: './ajax_pos.php',
        data: 'action=' + action,
        async: false,
        success: function (msg) {
            result = msg;
        }
    });
    return result;
}
function displayPrice(pr) {
    //return (Math.round(pr*100/5)*5/100).toFixed(2);
    var precision = 2;
    if (typeof _TPV.defaultConfig['decrange']['tot'] != 'undefined')
        precision = _TPV.defaultConfig['decrange']['tot'];
    return parseFloat(pr).toFixed(precision);
}
function showLeftContent(divcontent) {
    $('.leftBlock').each(function () {
        $(this).hide();
    });
    $(divcontent).show();
}
function hideLeftContent() {
    $('.leftBlock').each(function () {
        $(this).hide();
    });
    $('#products').show();
}
function ajaxDataSend(action,data) {
    var result;

    var DTO = {'data': data};

    var data = JSON.stringify(DTO);
    $.ajax({
        type: "POST",
        traditional: true,
        cache: false,
        url: './ajax_pos.php?action=' + action,
        contentType: "application/json;charset=utf-8",
        dataType: "json",
        async: false,
        processData: false,
        data: data,
        success: function (msg) {
            result = msg;
        
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Error:" + jqXHR.status + " , " + textStatus + ",  " +errorThrown);
             location.reload();
        }
    });
    if (result != null && typeof result != 'undefined') {
        if (typeof result['error'] != 'undefined' && typeof result['error']['desc'] != 'undefined' && result['error']['desc'] != '') // desc,value
        {
            if (result['error']['value'] == 0) {
                _TPV.showInfo(result['error']['desc']);
            }
            else if (result['error']['value'] == 99) {
                _TPV.showError(result['error']['desc']);
                window.location.href = "./disconect.php";
            }
            else {
                _TPV.showError(result['error']['desc']);
            }
            if (typeof result['error']['value'] != 'undefined' && parseInt(result['error']['value']) == 1)
                return false;
        }
        if (typeof result['error'] != 'undefined' && typeof result['error']['value'] != 'undefined' && result['error']['value'] == 0) // desc,value
        {

            if (typeof result['data'] != 'undefined')
                return result['data'];
        }
        //_TPV.showInfo('Error de ejecucion del codigo javascript');
    }
    return result;
}
