var Ticket = jQuery.Class({

    init: function () {
        this.id = 0;
        this.ref = "";
       // this.payment_type = 0;
        this.type = 0;
        this.discount_global = 0;
        this.discount_qty = 0;
        this.lines = new Array();
        this.oldproducts = new Array();
        this.total_ttc = 0;
        this.tot_price_ttc = 0;
        this.customerpay = 0;
        this.customerpay1 = 0;
        this.customerpay2 = 0;
        this.customerpay3 = 0;
        this.difpayment = 0;
        this.customerId = 0;
        this.customerName = "";
        this.employeeId = 0;
        this.idsource = 0;
        this.state = 1; // 0=Draft, 1=To Invoice , 2=Invoiced, 3=No invoiceble
        this.id_place = 0;
        this.note = "";
        this.mode = 0;
        this.points = 0;
        this.coupons = new Object();
        this.total_coupons = 0;
        this.ret_points = 0;
        this.serie = 0;
        this.uselocaltax1_rate = 0;
        this.uselocaltax2_rate = 0;
        this.qty=0;
        this.window = '';
    },  
    getExistReturnProduct: function (idProduct) {
        if (typeof this.oldproducts != 'undefined' && this.oldproducts.length > 0) {
            for (var i = 0; i < this.oldproducts.length; i++) {
                if (this.oldproducts[i]['idProduct'] == idProduct) {
                    var returnLine =  jQuery.extend(true, {}, this.oldproducts[i]);
                    return returnLine;
                }
            }
        }
        return false;
    },
    newTicket: function () {
        this.init();
        this.customerId = _TPV.customerId;
        this.employeeId = _TPV.employeeId;
        this.cashId = _TPV.cashId;
        this.discount_global = _TPV.discount_global;
        this.uselocaltax1_rate = _TPV.uselocaltax1_rate;
        this.uselocaltax2_rate = _TPV.uselocaltax2_rate;
        _TPV.ticketState = 0;
        //_TPV.getDataCategories(0);
        _TPV.setButtonState(false);
        $('#tablaTicket tbody tr').remove();
        $('#totalDiscount').html(displayPrice(0));
        $('#totalTicket').html(displayPrice(0));
        $('#totalTicketinv').html(displayPrice(0));
        $('#totalPlace').html('');
        $('#btnTicketRef').html('');
        var result = ajaxDataSend('getNotes', 0);
        if (result) {
            $('#totalNote_').html(result);
        }
        else {
            $('#totalNote_').html(0);
        }
        if (typeof _TPV.defaultConfig['customer']['name'] != 'undefined') {
            this.customerName = _TPV.defaultConfig['customer']['name'];
            $('#infoCustomer').html(_TPV.defaultConfig['customer']['name']);
            $('#infoCustomer_').html(_TPV.defaultConfig['customer']['name']);
        }
        if (typeof this.discount_global != 'undefined') {
            $('#ticket_discount_perc').html(this.discount_global);
            $('#globalDiscount').html(this.discount_global + "%");
        }
        _TPV.points = _TPV.defaultConfig['customer']['points'];
        //_TPV.coupon = _TPV.defaultConfig['customer']['coupon'];
        _TPV.activeIdProduct = 0;
        $('#info_product').hide();
        $('#payment_points').hide();
        //this.resetPayment();
        hideLeftContent();
        if (_TPV.defaultConfig['terminal']['barcode'] == 1) {
            $('#id_product_search').focus();
        }
    },
    newTicketPlace: function (id_place) {
        this.newTicket();
        $('#totalPlace').html(_TPV.places[id_place]);
        this.id_place = id_place;
        showTicketContent();
    },
    setLine: function (line) {
        this.lines.push(line);
        if (this.lines.length == 1)
            _TPV.setButtonState(true);
        this.addLineToTotal(line);
    },
    setPlace: function (idPlace) {
        this.id_place = idPlace;
    },
    getLine: function (idProduct) {
        for (var i in this.lines) {
            if (this.lines[i]['idProduct'] == idProduct)
                return this.lines[i];
        }
        return null;
    },
    showTotalTicket: function () {
        discount = this.tot_price_ttc - this.total_ttc;
        var pricediscount = new Number(discount);
        pricediscount = pricediscount.toFixed(2);
        $('#totalDiscount').html(displayPrice(pricediscount));
        var total = new Number(this.total_ttc);
        total = total.toFixed(2);
        $('#totalTicket').html(displayPrice(total));
        $('#totalTicketinv').html(displayPrice(total));
        var limfac = new Number(_TPV.faclimit);
        if (total >= limfac) {
            $('#alertfaclim').show();
        }
        else {
            $('#alertfaclim').hide();
        }
    },
    restLineFromTotal: function (line) {
        if (typeof line == "object" ) {
            this.total_ttc      =   Math.round10(parseFloat(this.total_ttc - line.total_ttc),-2);
            this.tot_price_ttc  =   Math.round10(parseFloat(this.tot_price_ttc - line.tot_price_ttc),-2);
            this.qty            =   Math.round10(parseFloat(this.qty - line.qty),-2);
        }
    },
    addLineToTotal: function (line) {
        if (typeof line == "object" ) {
            this.total_ttc      =   Math.round10(parseFloat(this.total_ttc + line.total_ttc),-2);
            this.tot_price_ttc  =   Math.round10(parseFloat(this.tot_price_ttc + line.tot_price_ttc),-2);
            this.qty            =   Math.round10(parseFloat(this.qty + line.qty),-2);
        }
    },
    updateTotal: function(oldLine,newLine) {

        this.restLineFromTotal(oldLine);
        this.addLineToTotal(newLine);    
    },
    updateLine: function (line,newLine, checkPrice = true){        
        var oldLine = jQuery.extend(true, {}, line);
        jQuery.extend(true, line, newLine);
        if (line.getTotal(checkPrice)) {
            line.showHTMLTotal();
            this.updateTotal(oldLine,line);
            this.showTotalTicket(); 
        }else{
            jQuery.extend(true, line, oldLine);    
        }
    },
    addThisLine: function (product, checkPrice) {
       var line = new TicketLine();
       jQuery.extend(true, line, product);
       if(line.getTotal(checkPrice)) {
            this.setLine(line);
            $('#tablaTicket > tbody:last').prepend(line.getHtmlCode());
            line.showEditOptions(this);
            this.showTotalTicket(); 
       }                               
    },
    addManualProduct: function (product) {
         var line = this.getLine(product['idProduct']);
         if (line != null) {
           this.updateLine(line, product); 
         } else{
            this.addThisLine(product, true);                          
         }     
    },
    addReturnProduct: function (idProduct) {
        returnLine = this.getExistReturnProduct(idProduct);
        if(!returnLine)
            return false;
        var line = this.getLine(idProduct)
        if (line != null) {
            var qty = line.qty+1;
            if (returnLine.qty < qty) {
                return false
            }
            returnLine.setQuantity(qty);
            this.updateLine(line, returnLine, false);
        }
        else {
            returnLine.setQuantity(1);
            this.addThisLine(returnLine, false)   
        }        
    },
    addLine: function (idProduct) {
        if (_TPV.ticketState == 1)
            return;
        showTicketContent();

        if (_TPV.ticket.idsource != 0) {
            this.addReturnProduct(idProduct);
            return;
        }
        var line = this.getLine(idProduct);
        if ( line != undefined) {
            var newLine = Object();
            newLine.qty = line.qty + 1;
            this.updateLine(line,newLine);
        }
        else {
            var line = new TicketLine();
            if (line.setLineByIdProducts(idProduct, this) == true ){
                this.setLine(line);
                $('#tablaTicket > tbody:last').prepend(line.getHtmlCode());
                line.showEditOptions(this);
                this.showTotalTicket();     
            }
        }
        _TPV.addInfoProduct(idProduct);
        if (_TPV.defaultConfig['terminal']['barcode'] == 1) {
            $('#id_product_search').focus();
        }
    },
    setPaymentReadonly: function (readonly){
        $('#pay_client_0').attr('readonly', readonly);
        $('#pay_client_1').attr('readonly', readonly);
        $('#pay_client_2').attr('readonly', readonly);
        $('#pay_all_0').attr("disabled", readonly);
        $('#pay_all_1').attr("disabled", readonly);
        $('#pay_all_2').attr("disabled", readonly);
        
        pay_all_0
    },
    resetMoneyPayment: function (){
        $('#pay_client_0').val("");
        $('#pay_client_1').val("");
        $('#pay_client_2').val("");
        this.customerpay1 = 0;
        this.customerpay2 = 0;
        this.customerpay3 = 0;
        this.customerpay = 0;
    },
    resetPayment: function(){
        this.resetMoneyPayment();
        this.setPaymentReadonly(false);
        $('#pay_coupons').val("");
        $('#idPayCoupons').hide();
        this.total_coupons = 0;
        this.coupons = new Object();
        this.convertDis = false; 
    },
    checkIsPayedWithCoupons: function(){
        $('#pay_coupons').val(ticket.total_coupons);
        if (ticket.total_coupons > 0) {
            $('#idPayCoupons').show();
        }else{
            $('#idPayCoupons').hide();
        }        
        if (this.total_coupons >= this.total_ttc) {
            this.resetMoneyPayment();
            this.setPaymentReadonly(true);
        } else{

            this.setPaymentReadonly(false);
        }
        this.calculeDifPayment();
    },
    /*addCoupons: function () {
        var total_coupons = 0;
        $.each(this.coupons, function (ref, ammount) {
           total_coupons += ammount;
        });
        this.total_coupons = total_coupons;
        this.calculeDifPayment();
        _TPV.showInfo(_TPV.translations['CouponAdded']);
    },
    editTicketLine: function (idProduct) {
        var line = _TPV.ticket.getLine(idProduct);
        $('#line_quantity').val(line.qty);
        $('#line_discount').val(line.discount);
        $('#line_price').val(Math.round(line.pu_ht * 100) / 100);
        $('#line_note').val(line.note);
        showLeftContent('#idTicketLine');
        $('#id_btn_editTicketline').unbind('click');
        $('#id_btn_editTicketline').click(function () {
            var line = _TPV.ticket.getLine(idProduct);
            var newLine = new TicketLine();
            var newLine = jQuery.extend(true, {}, line);
            newLine.setQuantity($('#line_quantity').val());
            newLine.setDiscount($('#line_discount').val());
            newLine.setPrice($('#line_price').val());
            newLine.setNote($('#line_note').val());
            if (newLine.getTotal(true) == true) {
                _TPV.ticket.updateTotal(line,newLine);
                jQuery.extend(true, line, newLine);
                line.showHTMLTotal();
                _TPV.ticket.showTotalTicket();
            } else{
                hideLeftContent();    
            }

            
        });
    },*/
    deleteLine: function (idProduct) {
        $('#ticketLine' + idProduct).remove();
        line = jQuery.extend(true, {}, this.getLine(idProduct));    
        this.lines = removeKey(this.lines, idProduct);
        if (this.lines.length == 0) {
           this.newTicket();     
        } else{
            this.restLineFromTotal(line);
            this.showTotalTicket();
        }
    },
    cancelTicket: function () {
        var success = ajaxSend('cancelTicket');
        $('#tablaTicket tbody tr').remove();
    },
    saveTicket: function () {
        // Set State to draft
        this.mode = 0;
        this.state = 0;
        var result = ajaxDataSend('saveTicket', this);
        if (result) {
            $('#tablaTicket tbody tr').remove();
            _TPV.ticket.newTicket();
        }
    },
    showWindowPayment: function(){
        if (this.window == '#payType') { 
            this.showTotalBlock();
        } else{
            this.showTotalBlockRet();    
        }
        $(this.window).dialog({modal: true, width: 500,  create: function() {
            $(this).closest('div.ui-dialog')
            .find('a.ui-dialog-titlebar-close')
            .click(function(e) {
               _TPV.ticket.resetPayment();
               e.preventDefault();
           });
        }});
    },
    calculeDifPayment: function() {
        if (this.type==1) {
            this.difpayment = Math.min(this.total_ttc, this.ret_points) - this.customerpay;
            var displayDif = '.payment_return_ret';
        } else {
            if (this.points > 0) {
                this.difpayment = this.total_with_points - this.total_coupons - this.customerpay;
            }
            else {
                this.difpayment = this.total_ttc - this.total_coupons - this.customerpay;
            }
            this.difpayment = Math.round10(this.difpayment,-2);  
            var displayDif = '.payment_return';
        }
        if (this.difpayment > 0)
            $(displayDif).addClass('negat');
        else
            $(displayDif).removeClass('negat');
        $('.payment_options ' + displayDif).html(displayPrice(this.difpayment));
    }, 
    okTicket: function () {
        //$('#id_btn_add_ticket').hide();
        $('#payment_points').hide();
        $('#info_product').hide();
        this.resetPayment();
        this.calculeDifPayment();
        if (this.type == 1) {
            //this.difpayment = Math.min(this.total_ttc, this.ret_points);
            this.window = '#payTypeRet';
            this.showWindowPayment();
            if (this.mode == 0) {
                $('#convert_coupon').hide();
                $('#payment_total_ret').show();
                $('#payment_total_points_ret').show();
                this.mode = 0;
            }
            else if (this.total_ttc < _TPV.faclimit) {
                $('#convert_coupon').show();
                this.mode = 1;
            }
            else {
                $('#convert_coupon').show();
                this.mode = 2;
            }
	    }
        else {
            $('#points_client_id').val('');
            // this.difpayment = this.total_ttc - this.total_coupons;
            this.window = '#payType'; 
            //la opcion para elegir ticket, facsim o factura
            if (_TPV.defaultConfig['module']['ticket'] == 1 && _TPV.defaultConfig['module']['facture'] == 1) {
                showLeftContent('#idFactureMode');
            }
            else if (_TPV.defaultConfig['module']['ticket'] == 1) {
                this.mode = 0;
                
                this.showWindowPayment();
            }
            else if (this.total_ttc < _TPV.faclimit) {
                this.mode = 1;
                this.showWindowPayment();
            }
            else {
                this.mode = 2;
                if (_TPV.defaultConfig['module']['series'] == 1) {
                    showLeftContent('#idSerieMode');
                }
                else {
                    this.showWindowPayment();
                }
            }
            $('#id_btn_ticketPay').click(function () {
                $('#id_btn_ticketPay').unbind('click');
                _TPV.ticket.mode = 0;
                this.showWindowPayment();
                $('#payment_coupon').hide();
                $('#payment_total_points').show();
                //$('#id_btn_add_ticket').show();
            });
            $('#id_btn_facsimPay').click(function () {
                $('#id_btn_facsimPay').unbind('click');
                _TPV.ticket.mode = 1;
                this.showWindowPayment();
            });
            $('#id_btn_facturePay').click(function () {
                $('#id_btn_facturePay').unbind('click');
                _TPV.ticket.mode = 2;
                if (_TPV.defaultConfig['module']['series'] == 1) {
                    showLeftContent('#idSerieMode');
                }
                else {
                    this.showWindowPayment();
                }
            });
        }
        $('#id_btn_payment_confirm').click(function(){
            _TPV.ticket.confirmTotal()
        });

        $('#id_btn_add_ticket').unbind('click');
        $('#id_btn_add_ticket_ret').unbind('click');
        $('#id_btn_add_ticket_desc').unbind('click');
        $('#id_btn_add_ticket').click(function () {
            $('#idShowPayment').dialog("close");            
            _TPV.ticket.sendTicket();
        });
        $('#id_btn_add_ticket_ret').click(function () {
            _TPV.ticket.sendTicket();
        });
        $('#id_btn_add_ticket_desc').click(function () {
            _TPV.ticket.convertDis = true;
            _TPV.ticket.sendTicket();
        });                
    },
    confirmTotal: function(){

       $('#idShowPayment').dialog({modal: true, width: 440});
        $('#txt_payment_0').html(this.customerpay1);
        $('#txt_payment_1').html(this.customerpay2);
        $('#txt_payment_2').html(this.customerpay3);
        $('#txt_payment_coupons').html(this.total_coupons);
    },
    ajaxSendTicket: function(){
        this.state = 1;
        var result = ajaxDataSend('saveTicket', this);
        if (!result)
            return;
        $(this.window).dialog("close");
        if (_TPV.defaultConfig['module']['print'] > 0) {
            if (this.mode == 0) {
                _TPV.printing('ticket', result);
                _TPV.printing('ticket', result);
            }
            else {
                _TPV.printing('facture', result);
                _TPV.printing('facture', result);
            }
        }
        this.newTicket();
    },
    checkChangeAmmount: function(){
        if (this.difpayment < 0) {
            if (this.type == 1) {
                 return _TPV.showError(_TPV.translations['ReturnAmmountMustBeExact']);
            } else{

                var difpayment = this.difpayment * -1;
                     
                var pay = this.customerpay1 + this.total_coupons - this.total_ttc;
                if (pay < difpayment) {
                    return _TPV.showError(_TPV.translations['ChangeAmmountCantByMoreThanCashPayment']);
                }    
            }
        }
        this.ajaxSendTicket(); 
    },
    sendTicket: function () {
        if (this.convertDis) {
            return this.ajaxSendTicket();
        }
        if (this.difpayment > 0){
        
            this.paymentMenor();
        }
        else {
            this.checkChangeAmmount(); 
        }
    },
    showAddCustomer: function (customer) {
        $('#idClient').dialog({modal: true});
        $('#idClient').dialog({width: 440});
        $('#id_btn_add_customer').unbind('click');
        $('#id_btn_add_customer').click(function () {
            var customer = new Customer();
            customer.nom = $('#id_customer_name').val();
            customer.prenom = $('#id_customer_lastname').val();
            customer.address = $('#id_customer_address').val();
            customer.town = $('#id_customer_town').val();
            customer.zip = $('#id_customer_zip').val();
            customer.idprof1 = $('#id_customer_cif').val();
            customer.tel = $('#id_customer_phone').val();
            customer.email = $('#id_customer_email').val();
            customer.discount = $('#id_customer_disc').val();
            var result = ajaxDataSend('addCustomer', customer);
            $('#idClient').dialog('close');
            //if(result.length>0)
            //_TPV.ticket.id= result[0];
        });
    },
    //addTicketCustomer: function (idcustomer, name, remise, coupon, points) {
    addTicketCustomer: function (idcustomer, name, remise, points) {
        this.newTicket();
        this.customerId = idcustomer;
        this.customerName = name;
        this.discount_global = remise;
        //_TPV.coupon = coupon;
        _TPV.points = points;
        this.showCustomerInfo();
        showTicketContent();
    },
    showAddProduct: function (customer) {
        var product = new Product();
        $('#id_product_name').val('');
        $('#id_product_ref').val('');
        $('#id_product_price').val('');
        $('#idPanelProduct').dialog({modal: true});
        $('#idPanelProduct').dialog({height: 450, width: 440});
        $('.tax_types').removeClass('btnon');
        $('.tax_types').unbind('click');
        $('.tax_types').click(function () {
            $('.tax_types').removeClass('btnon');
            $(this).addClass('btnon');
            product.tax = $(this).find('a:first').attr('id').substring(7);
        })
        $('#id_btn_add_product').unbind('click');
        $('#id_btn_add_product').click(function () {

            product.label = $('#id_product_name').val();
            product.ref = $('#id_product_ref').val();
            product.price_ttc = $('#id_product_price').val();
            var result = ajaxDataSend('addNewProduct', product);
            $('#idPanelProduct').dialog('close');
            if (result)
                _TPV.getDataCategories(0);

        });
    },
    addDiscount: function () {
        var disc =  _TPV.ticket.discount_global;
        $('#ticket_discount_perc').val(disc);
        //$('#ticket_discount_qty').val('');
        showLeftContent('#idDiscount');
        $('#id_btn_add_discount').unbind('click');
        $('#id_btn_add_discount').click(function () {
            _TPV.ticket.discount_global = $('#ticket_discount_perc').val();
            if (_TPV.ticket.calculeTotal() == false) {        
                hideLeftContent();
                $('#globalDiscount').html(_TPV.ticket.discount_global + "%");
            }else {
                $('#ticket_discount_perc').val(disc);
                _TPV.ticket.discount_global = disc;
            }

        });
    },
    calculeTotal: function () {
        var newlines = Array();
        var error = false;
        for (var i = 0; i < this.lines.length; i++) {
            var result = calcule_product_price(this.lines[i], this)
            if (result['pu_min'] <  this.lines[i].price_min) {
                _TPV.showError(_TPV.translations['PriceMinError']);
                error = true;
                break;
            } else{
                newlines[i] = result;
            }
         }
         if ( !error ) {
            for (var i = 0; i < this.lines.length; i++) {
                this.updateTotal(this.lines[i], newlines[i]);
                jQuery.extend(true, this.lines[i], newlines[i]);
                this.lines[i].showHTMLTotal();
            }
            this.showTotalTicket();            
        }
        return error;       
    },
    addTicketNote: function () {
        $('#ticket_note').val(_TPV.ticket.note);
        $('#ticketNote').dialog({modal: true});
        $('#ticketNote').dialog({width: 450});
        $('#id_btn_ticket_note').unbind('click');
        $('#id_btn_ticket_note').click(function () {
            _TPV.ticket.note = $('#ticket_note').val();

            $('#ticketNote').dialog("close");

        });
    },
    paymentMenor: function () {

        if (this.type == 1) {
            _TPV.showError(_TPV.translations['ReturnAmmountMustBeExact']);
            return false;
        }
        if (!_TPV.defaultConfig['module']['credit_sell']) {
            _TPV.showError(_TPV.translations['CreditSellNotAllowed'])
            return false;
        } 
        $('#paymentMenor').dialog({modal: true, height: 250, width: 350});
        $('#id_btn_payment_menor_yes').unbind('click');
        $('#id_btn_payment_menor_no').unbind('click');
        $('#id_btn_payment_menor_yes').click(function () {
            _TPV.ticket.ajaxSendTicket();
            $('#paymentMenor').dialog("close");
        });
        $('#id_btn_payment_menor_no').click(function () {
            $('#paymentMenor').dialog("close");
        });
    },
    showCoupons: function () {
        //var result = ajaxDataSend('searchCoupon', _TPV.ticket.customerId);
        $('#idRefCoupon').dialog({modal: true, height: 450, width: 600});
        $('#id_ref_coupon_search').val("");
        $("#couponTable_ tbody tr.data").remove();
        $.each(this.coupons, function (ref, coupon) {
            $.each(coupon, function (index, cp) {
                $('#couponTable_ tbody').append('<tr class="data"><td class="itemId" >' + ref + '</td><td class="itemAmount">' + displayPrice(cp.amount) + '</td><td class="action add"><a class="action delcoupon" onclick="_TPV.ticket.removeCoupon(\'' + ref + '\', '+ cp.id +');"></a></td></tr>');
            });
        });            
    },
    inCoupons: function(coupon){
        var  found = false;
        $.each(this.coupons, function (ref, ammount) {
            if (coupon == ref) {
                found = true;
                return;
            }        
        });
        return found;
    },
    searchCoupon: function () {
        //filter.search = $('#id_ref_coupon_search').val();
        ticket = this;
        var ref = $('#id_ref_coupon_search').val();
        if (this.inCoupons(ref)) {
            $('#id_ref_coupon_search').val("");
            return _TPV.showError(_TPV.translations['CouponAlreadyLoaded']);
        }
        var result = ajaxDataSend('GetCouponByRef', ref);     
        if (typeof result != 'undefined' && typeof result['total_coupons'] != 'undefined' && result['total_coupons'] > 0) {
            $('#idApplyCoupon').dialog({modal: true, height: 300, width: 350});
            $('#id_coupon_amount').text(result['total_coupons']);
            $('#id_btn_couponYes').unbind('click');
            $('#id_btn_couponNo').unbind('click');
            $('#id_btn_couponNo').click(function () {
                $('#idApplyCoupon').dialog("close");
            });
            $('#id_btn_couponYes').click(function () {
                ticket.coupons[ref] = result['coupons'];
                ticket.total_coupons += Math.round10 (result['total_coupons'],-2);
                //$('#couponTable_ tbody').append('<tr class="data"><td class="itemId" >' + ref + '</td><td class="itemAmount">' + displayPrice(result) + '</td><td class="action add"><a class="action addcoupon" onclick="_TPV.ticket.removeCoupon(' + ref + ');"></a></td></tr>');          
                $('#idApplyCoupon').dialog("close");
                ticket.checkIsPayedWithCoupons();
                ticket.showCoupons();
            });
        } else {
            _TPV.showError(_TPV.translations['InvalidCoupon']);   
        }
    },
    removeCoupon: function(ref, id){
        ticket = this;
        $.each(this.coupons, function (ref_, coupons) {
            if (ref_ == ref) {
                $.each(coupons, function (index, cp) {
                    if (cp.id == id) {
                        ticket.total_coupons = Math.round10(ticket.total_coupons - cp.amount,-2);
                        delete coupons[index];
                    }
                });
            ticket.coupons[ref_] = coupons.filter(function(x) { return x !== null });
            }
        });
        if (this.coupons[ref].length == 0) {
            delete ticket.coupons[ref];
        }
        
        this.checkIsPayedWithCoupons();
        this.showCoupons();
    },
    showTotalBlock: function () {
        $('#payment_coupon').show();
        if (_TPV.points != null && this.mode != 0) {
            $('#payment_points').show();
            $('#payment_total_points').hide();
        }
        else {
            $('#payment_total_points').show();
        }
        //$('#id_btn_add_ticket').show();
        //Initialize Values
        $('.points_total').html(_TPV.points);
        $('.points_money').html(_TPV.defaultConfig['module']['points'] * _TPV.points + ' ');
        //$('.payment_total').html(displayPrice(_TPV.ticket.total));
    },
    showTotalBlockRet: function () {
        $('#payment_total_ret').show();
        //$('#id_btn_add_ticket_ret').show();
        $('#convert_coupon').hide();
    },
    showZoomProducts: function () {
        $('#idProducts').append($('#products').html());
        $('#idProducts').dialog({modal: true});
        $('#idProducts').dialog({width: 640});
    },
    showManualProducts: function () {
        $('#idManualProducts').dialog({modal: true});
        $('#idManualProducts').dialog({width: 640});
    },
    showTicketOptions: function (idProduct) {
        $('.leftBlock').hide();
        $('#products').show();
        $('#ticketOptions').html($('#ticketLine' + idProduct).find('.colActions').html()).show();
        _TPV.addInfoProduct(idProduct);
        $('#tablaTicket tr').removeClass('lineSelected');
        $('#ticketLine' + idProduct).addClass('lineSelected');
    },
    hideTicketOptions: function (idProduct) {
        $('#ticketOptions').html($('#ticketLine' + idProduct).find('.colActions').html()).hide();
    },
    showHistoryOptions: function (idTicket) {
        $('#historyOptions .colActions').html($('#historyTicket' + idTicket).find('.colActions').html()).show();
        $('#historyOptions').show();
        $('#historyTable tr').removeClass('lineSelected');
        $('#historyTicket' + idTicket).addClass('lineSelected');
    },
    hideHistoryOptions: function (idTicket) {
        $('#historyOptions .colActions').html($('#historyTicket' + idTicket).find('.colActions').html()).hide();
        $('#historyOptions').hide();
    },
    showHistoryFacOptions: function (idTicket) {
        $('#historyFacOptions .colActions').html($('#historyFacTicket' + idTicket).find('.colActions').html()).show()
        $('#historyFacOptions').show();
        $('#historyFacTable tr').removeClass('lineSelected');
        $('#historyFacTicket' + idTicket).addClass('lineSelected');
    },
    hideHistoryFacOptions: function (idTicket) {
        $('#historyFacOptions .colActions').html($('#historyFacTicket' + idTicket).find('.colActions').html()).hide();
        $('#historyFacOptions').hide();
    },
    showStockOptions: function (idProduct, idWarehouse) {
        $('#stockOptions .colActions').html($('#stock' + idProduct + '_' + idWarehouse).find('.colActions').html()).show();
        $('#stockOptions').show();
        $('#storeTable tr').removeClass('lineSelected');
        $('#stock' + idProduct + '_' + idWarehouse).addClass('lineSelected');
    },
    hideStockOptions: function (idProduct, idWarehouse) {
        $('#stockOptions .colActions').html($('#stock' + idProduct + '_' + idWarehouse).find('.colActions').html()).hide();
        $('#stockOptions').hide();
    },
    editLineQty: function (idProduct){
        var ticket = this;
        var line = ticket.getLine(idProduct);

        edtQty = '<input id="edt_qty_'+ idProduct +'" value = '+ line.qty +' type="text" size="6" class="numKeyboard">';
        $('#ticketLine' + idProduct).find('.cant').html(edtQty);
        $('#edt_qty_'+ idProduct).focus().select();
        $('#edt_qty_'+ idProduct).blur(function(){
            $('#ticketLine' + idProduct).find('.cant').html(line.qty);
        });
        $('#edt_qty_'+ idProduct).change(function() {
            var newLine = Object();
            newLine.qty = parseFloat(this.value);
            if (isNaN(newLine.qty)) {
                $('#ticketLine' + idProduct).find('.cant').html(line.qty);
                return;     
            }

            if (newLine.qty ==0 ) {
                ticket.deleteLine(line.idProduct);
                return;     
            }
            /*Chequea si existe stock - para venta - o cantidad en factura - para devolucion*/
            if ( _TPV.ticketState != 2  && _TPV.defaultConfig['module']['pos_stock'] == 0 && line["stock"] === null) {
                line. getStock();
            }
            
            if (line["stock"] != "all" && newLine.qty > line["stock"]) {
                _TPV.showError(_TPV.translations['NoStockEnough']);
                $('#ticketLine' + idProduct).find('.cant').html(line.qty);
            } else {
                ticket.updateLine(line,newLine);
            }
        });
    },
    editLineDiscount: function (idProduct){
        var line = this.getLine(idProduct);
        edtDisc = '<input id="edt_disc_'+ idProduct +'" value = '+ line.discount +' type="text" size="6" class="numKeyboard">';
        $('#ticketLine' + idProduct).find('.discount').html(edtDisc);
        $('#edt_disc_'+ idProduct).focus().select();
        $('#edt_disc_'+ idProduct).blur(function(){
            $('#ticketLine' + idProduct).find('.discount').html(line.discount + '%');
        });

        $('#edt_disc_'+ idProduct).change(function() {
            var newLine = Object();
            newLine.discount = parseFloat(this.value);
            _TPV.ticket.updateLine(line,newLine);
        });
    },
    editLinePrice: function (idProduct){
        var line = this.getLine(idProduct);
        edtPrice = '<input id="edt_price_'+ idProduct +'" value = '+ line.pu_ht +' type="text" size="6" class="numKeyboard">';
        $('#ticketLine' + idProduct).find('.pu_ht').html(edtPrice);
        $('#edt_price_'+ idProduct).focus().select();
        $('#edt_price_'+ idProduct).blur(function(){
            $('#ticketLine' + idProduct).find('.pu_ht').html(displayPrice(line.pu_ht));
        });
        $('#edt_price_'+ idProduct).change(function() {
            var newLine = Object();
            newLine.pu_ht = parseFloat(this.value);
            _TPV.ticket.updateLine(line,newLine);
        });
    },
    loadLines: function (lines) {
        var ticket = this;
        $('#tablaTicket > tbody tr').remove();
        $.each( lines , function (idline, line) {
            var newLine = new TicketLine();
            jQuery.extend(true, newLine, line);
            var add = true;
            if (_TPV.ticketState == 0) {
               var add = newLine.getTotal(true);     
            } 
            if (add) {
                ticket.setLine(newLine);
                $('#tablaTicket > tbody:last').prepend(newLine.getHtmlCode());
                newLine.showEditOptions(ticket);
            }
        });
        ticket.showTotalTicket();                 
    },
    setReturnStock: function () {
        var ticket = this;
        var result = ajaxDataSend('CheckProductsReturned', ticket.idsource);
        if (result != null) {
            $.each( this.lines , function (id, line) {
                line['stock'] = ticket.oldproducts[id]['qty'];
                if  (typeof result[line.idProduct] != 'undefined') {
                    line['stock'] -= parseFloat(result[line.idProduct]['qty']);
                }
                if (line['stock'] == 0 ) {
                    delete ticket.lines[id];
                } else {
                    if (line['stock'] < line['qty'] ) {
                        line['qty'] = line['stock']; 
                    }
                }
            });
            this.lines = this.lines.filter(function(x) { return x !== null });
            this.total_ttc += result['total_ttc'];
        }            
    },  
    showLines: function () {
        var ticket = this;
        $('#tablaTicket > tbody tr').remove();
        $.each( this.lines , function (idline, line) {
            $('#tablaTicket > tbody:last').prepend(line.getHtmlCode());
            line.showEditOptions(ticket); 
        });
        ticket.showTotalTicket();                 
    },
    showCustomerInfo: function(){
        var ref = "";
        if (_TPV.ticketState == 2) 
            ref = _TPV.translations['ReturnTicket'] + " " + this.ref; 
         else
            ref = this.ref;

        $('#btnTicketRef').html(ref);
        $('#infoCustomer').html(this.customerName);
        $('#infoCustomer_').html(this.customerName);
        $('#globalDiscount').html(this.discount_global + "%");
    },
    setInfoToGetProduct: function(idProduct){
        var info = new Object();
        info['product'] = idProduct;
        info['customer'] = this.customerId;
        info['discount_global'] = this.discount_global;
        info['warehouse'] = _TPV.warehouseId;
        return info;
    }
});