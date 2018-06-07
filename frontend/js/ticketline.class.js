var TicketLine = jQuery.Class({
    init: function () {
        this.id = 0;
        this.idProduct = 0;
        this.ref = 0;
        this.label = '';
        this.description = '';
        this.discount = 0;
        this.discount_global =  0;
        this.qty = 1;
        //this.idTicket = 0;
        this.localtax1_tx = 0
        this.localtax1_type = 0
        this.localtax2_tx = 0
        this.localtax2_type = 0
        this.tva_tx = 0;
        this.pu_ht = 0;
        this.pu_tva = 0;
        this.pu_ttc = 0;//pu_ht+pu_tva
        this.total_ht = 0;
        this.price_min = 0;
        this.price_min_ttc = 0;
        this.price_base_type = '';
        this.type = 0;
        this.total_ttc = 0;//total_ht+total_tva
        this.tot_price_ttc = 0;
        this.diff_price = 0;
        this.is_promo = 0;
        this.stock = null;
    },    
    /*getHtml: function () {
        var hide = "$('#info_product').toggle()";
        // html =  '<tr id="ticketLine' + this.idProduct + '" onclick="_TPV.ticket.showTicketOptions(' + this.idProduct + ')">';
        html =  '<tr id="ticketLine' + this.idProduct + '">';
        html += this.getHtmlLine();
        html += '<td class="colActions">';
        html += '<a class="action edit" onclick="_TPV.ticket.editTicketLine(' + this.idProduct + ');"></a>';
        html += '<a class="action delete" onclick="_TPV.ticket.deleteLine(' + this.idProduct + ');"></a>';
        html += '<a class="action info" onclick="' + hide + '"></a><a class="action close" onclick="_TPV.ticket.hideTicketOptions(' + this.idProduct + ')"></a></td></tr>';
        return html; 
    },
    getHtmlLine() {
        html = '<td class="idCol">' + this.idProduct + '</td>';
        html += '<td class="description">' + this.ref + " - " + this.label + ' <a class="removeLine" onclick="_TPV.ticket.deleteLine(' + this.idProduct + ');"></td>';
        html += '<td class="pu_ht" onclick="_TPV.ticket.editLinePrice(' + this.idProduct + ')">' + displayPrice(this.pu_ht) + '</td>';
        html += '<td class="discount" onclick="_TPV.ticket.editLineDiscount(' + this.idProduct + ')">' + this.discount + '%</td>';
        if (this.diff_price == 0) {
            html += '<td class="price">' + displayPrice(this.pu_ttc) + '</td>';
        } else {
            var txt = _TPV.translations['DiffPrice'];        
            html += '<td class="price"><img style="float: left; margin: 3% 0px 0px 26%;" src="img/alert.png" title="' + txt + '">  ' + displayPrice(this.pu_ttc) + '</td>';
        }
        //html = html + '<td class="cant">' + line.qty + ' </td>';
        html += '<td class="cant"  onclick="_TPV.ticket.editLineQty(' + this.idProduct + ')">' + this.qty + '</td>';
        html += '<td class="total">' + displayPrice(this.total_ttc) + '</td>';
        return html;
    },*/
    getHtmlCode() {
        html =  '<tr id="ticketLine' + this.idProduct + '">';
        html +=  '<td class="idCol">' + this.idProduct + '</td>';
        html += '<td class="description">' + this.ref + " - " + this.label + '</td>';
        html += '<td class="pu_ht">' + displayPrice(this.pu_ht) + '</td>';
        html += '<td class="discount">' + this.discount + '%</td>';
        if (this.diff_price == 0) {
            html += '<td class="price">' + displayPrice(this.pu_ttc) + '</td>';
        } else {
            var txt = _TPV.translations['DiffPrice'];        
            html += '<td class="price"><img style="float: left; margin: 3% 0px 0px 26%;" src="img/alert.png" title="' + txt + '">  ' + displayPrice(this.pu_ttc) + '</td>';
        }
        html += '<td class="cant">' + this.qty + '</td>';
        html += '<td class="total">' + displayPrice(this.total_ttc) + '</td>';
        html += '<td class="colActions"></td></tr>';
        return html;
    },
    setLineByIdProducts: function (idProduct, ticket) {
        /*var info = new Object();
        info['product'] = idProduct;
        info['customer'] = ticket.customerId;
        info['discount_global'] = ticket.discount_global;
        */

        var result = ajaxDataSend('getProduct', ticket.setInfoToGetProduct(idProduct));
        if (result.length > 0) {
            if (_TPV.defaultConfig['module']['pos_stock'] == 1 || result[0]["stock"] > 0) {
                _TPV.products[idProduct] = result[0];
                jQuery.extend(true, this , result[0]);
                return true;                       
            }
            else {
                //Muestro un error diciendo que no hay stock ni se le espera...
                _TPV.showError(_TPV.translations['NoStockEnough']);
            }
        }
        return false;
    },
    setQuantity: function (cant) {
        number = parseFloat(cant);
        this.qty = number;
    },
    setDiscount: function (discount) {
        quantitydiscount = parseFloat(discount);
        if (quantitydiscount > 100 || quantitydiscount < 0)
            quantitydiscount = 0;
        this.discount = quantitydiscount;
    },
    setPrice: function (new_price) {
        price = parseFloat(new_price);
        price_old = Math.round10(this.pu_ht,-2);
        if (price == price_old)
            return;
        this.pu_ht = price;
    },
    setNote: function (note) {
        this.note = note;
    },
    getTotal: function (check_price) {
        var result = calcule_product_price(this, _TPV.ticket);
        //var result = ajaxDataSend('calculePrice', this);
        if (check_price == true) { 
            if (result['pu_min'] <  this.price_min) {
                _TPV.showError(_TPV.translations['PriceMinError']);
                return false;
            }
        }
        jQuery.extend(true, this, result); 
        return true;
    },
    showHTMLTotal: function () {

        $('#ticketLine' + this.idProduct).find('.cant').html(this.qty);
        $('#ticketLine' + this.idProduct).find('.discount').html(this.discount + '%');
         $('#ticketLine' + this.idProduct).find('.pu_ht').html(displayPrice(this.pu_ht));
        if (this.diff_price == 0)
            $('#ticketLine' + this.idProduct).find('.price').html(displayPrice(this.pu_ttc));
        else {
            $('#ticketLine' + this.idProduct).find('.price').html('<img style="float: left; margin: 3% 0px 0px 26%;" src="img/alert.png" title="' + _TPV.translations['DiffPrice'] + '"> ' + displayPrice(this.pu_ttc) + '');
        }
        $('#ticketLine' + this.idProduct).find('.total').html(displayPrice(this.total_ttc))   
    },
    showEditOptions: function (ticket) {
        var id = this.idProduct
        if (_TPV.ticketState != 1) {
            if (_TPV.ticketState != 2) {
                $('#ticketLine' + this.idProduct).find('.discount').each(function(index,item){$(item).click(function(){ticket.editLineDiscount(id)})});
                $('#ticketLine' + this.idProduct).find('.pu_ht').each(function(index,item){$(item).click(function(){ticket.editLinePrice(id)})});             
            }
            $('#ticketLine' + this.idProduct).find('.cant').each(function(index,item){$(item).click(function(){ticket.editLineQty(id)})});
            var desc = $('#ticketLine' + this.idProduct).find('.description').text();
            html = desc + ' <a class="removeLine"></a>';
            
            $('#ticketLine' + this.idProduct).find('.description').html(html);
            $('#ticketLine' + this.idProduct).find('.removeLine').each(function(index,item){$(item).click(function(){ticket.deleteLine(id)})});
        }
    },

    getStock: function () {
        var info = new Object();
        info['productId'] = this.idProduct;
        info['warehouseId'] = _TPV.warehouseId;
        info['type'] = this.type;
        var result = ajaxDataSend('getProductStock', info);
        if (result != null && typeof result != 'undefined' && typeof result['stock'] != 'undefined') {
            this.stock = result['stock'];
        }
    }


});