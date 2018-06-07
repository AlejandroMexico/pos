showLines: function (lines, edit) {
    $('#tablaTicket > tbody tr').remove();
    var total_dis = 0;            
    $.each( lines , function (idline, line) {
        if (!edit) {
            var totalLine = 0;
            var discount = 1;
            if (line['discount'] != 0)
                discount = 1 - line['discount'] / 100;
            //line['price_ttx']  = line['price_ttx']*(1+discount);       
            total_dis = total_dis + parseFloat(line['remise']) * ((parseFloat(line['tva_tx']) + parseFloat(line['localtax1_tx']) + parseFloat(line['localtax2_tx'])) / 100 + 1)
            var tr = '<tr id="ticketLine' + line['idProduct'] + '"><td class="idCol" >' + line['idProduct'] + '</td><td class="description">' + line['label'] + '</td><td class="discount">' + line['discount'] + '%</td><td class="price">' + displayPrice(line['total_ttc'] / line['cant']) + '</td><td class="cant">' + line['cant'] + '</td><td class="total">' + displayPrice(line['total_ttc']) + '</td>';
            tr = tr + '</tr>';

            $('#tablaTicket > tbody:last').prepend(tr);
        }
        else {
            line['discount'] = line['discount'] - _TPV.ticket.discount_percent;
            //_TPV.ticket.addManualProduct(line['idProduct'],line['cant'],line['discount'],line['total_ttc']);
            _TPV.ticket.addManualProduct(line['idProduct'], line['cant'], line['discount'], line['price'], line['note']);
        }
    });
    if (!edit) {
        _TPV.ticket.total = item['total_ttc'];
        $('#totalDiscount').html(displayPrice(total_dis));
        $('#totalTicket').html(displayPrice(_TPV.ticket.total));
        $('#totalTicketinv').html(displayPrice(_TPV.ticket.total));
        //_TPV.ticket.calculeDiscountTotal(total);
    }
    if (item['id_place']) {
        $('#totalPlace').html(_TPV.places[item['id_place']]);
    }
    else {
        $('#totalPlace').html('');
    }
    showTicketContent();
}