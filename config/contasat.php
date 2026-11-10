<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Header summary cards
    |--------------------------------------------------------------------------
    |
    | The stat-card strips at the top of the dashboard and the invoice screens
    | (status totals, Ingresos/Gastos, Subtotal/IVA/Total). Hidden by default;
    | flip to true (or set CONTASAT_SHOW_HEADER_CARDS=true) to bring them back
    | without touching the views.
    |
    */

    'show_header_cards' => (bool) env('CONTASAT_SHOW_HEADER_CARDS', false),

];
