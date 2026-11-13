<?php

return [
    // Product facts and indicative retail prices checked on 2026-11-12.
    // Quantities, internal opening lot numbers and expiry dates are sample opening stock.
    'checked_on' => '2026-11-12',
    'products' => [
        'PANADOL-500' => [
            'name' => 'Panadol 500 mg', 'unit' => 'viên',
            'strength' => 'Paracetamol 500 mg · Viên nén', 'price' => 1000,
            'opening_quantity' => 300,
            'source_url' => 'https://www.panadol.com/content/cf-consumer-healthcare/wellness-panadol/vi_vn/home/san-pham/san-pham-nguoi-lon/giam-dau-va-ha-sot/panadol-.html',
            'price_source_url' => 'https://nhathuoclongchau.com.vn/thuoc/panadol-giam-dau-ha-sot-2716.html',
        ],
        'CLANOZ-10' => [
            'name' => 'Clanoz 10 mg', 'unit' => 'viên',
            'strength' => 'Loratadin 10 mg · Viên nén', 'price' => 600,
            'opening_quantity' => 200,
            'source_url' => 'https://dhgpharma.com.vn/vi/san-pham/ho-hap/clanoz',
            'price_source_url' => 'https://nhathuoclongchau.com.vn/thuoc/clanoz-10-mg-dhg-pharma-10-x10.html',
        ],
        'BOCALEX-C1000' => [
            'name' => 'Bocalex C 1000', 'unit' => 'viên',
            'strength' => 'Vitamin C 1000 mg · Viên nén sủi bọt', 'price' => 2100,
            'opening_quantity' => 100,
            'source_url' => 'https://dhgpharma.com.vn/vi/san-pham/dinh-duong/bocalex-c-1000',
            'price_source_url' => 'https://www.pharmacity.vn/bocalex-c-1000-hop-1-tuyp-10-vien.html',
        ],
    ],
];
