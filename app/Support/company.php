<?php
// app/Support/company.php (або в helpers.php, який підключений у composer autoload)
use App\Models\CompanyProfile;

if (! function_exists('company')) {
    function company(): array {
        $db = CompanyProfile::query()->first();
        return [
            'name'   => $db->name   ?? env('COMPANY_NAME',   'ТОВ «Максбус»'),
            'edrpou' => $db->edrpou ?? env('COMPANY_EDRPOU'),
            'iban'   => $db->iban   ?? env('COMPANY_IBAN'),
            'bank'   => $db->bank   ?? env('COMPANY_BANK'),
            'addr'   => $db->addr   ?? env('COMPANY_ADDR'),
            'vat'    => $db->vat    ?? env('COMPANY_VAT', 'неплатник ПДВ'),
        ];
    }
}
