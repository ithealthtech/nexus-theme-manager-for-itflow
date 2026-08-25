<?php

declare(strict_types=1);

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/load_global_settings.php';
require_once '../includes/session_init.php';
require_once '../includes/inc_set_timezone.php';
require_once '../includes/nexus_theme.php';
require_once '../includes/nexus_invoice_pdf.php';

if (!isset($_GET['invoice_id'], $_GET['url_key']) || !is_string($_GET['url_key'])) {
    http_response_code(400);
    exit('Invoice reference is required.');
}

$invoice_id = intval($_GET['invoice_id']);
$url_key_raw = trim($_GET['url_key']);
if ($invoice_id < 1 || $url_key_raw === '') {
    http_response_code(400);
    exit('Invoice reference is invalid.');
}

if (!nexusThemeRuntimeEnabled()) {
    header('Location: guest_post.php?' . http_build_query([
        'export_invoice_pdf' => $invoice_id,
        'url_key' => $url_key_raw,
    ], '', '&', PHP_QUERY_RFC3986));
    exit;
}

$url_key = escapeSql($url_key_raw);
$sql = mysqli_query(
    $mysqli,
    "SELECT client_id, client_name, contact_email, contact_extension, contact_phone,
        contact_phone_country_code, invoice_amount, invoice_currency_code, invoice_date,
        invoice_discount_amount, invoice_due, invoice_id, invoice_note, invoice_number,
        invoice_prefix, invoice_status, location_address, location_city, location_country,
        location_state, location_zip FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
    LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
    WHERE invoice_id = $invoice_id AND invoice_url_key = '$url_key'
    LIMIT 1"
);

if (!$sql || mysqli_num_rows($sql) !== 1) {
    http_response_code(404);
    exit('Invoice not found.');
}

$invoice = mysqli_fetch_assoc($sql);
$invoice_prefix = (string)$invoice['invoice_prefix'];
$invoice_number = intval($invoice['invoice_number']);
$invoice_status = (string)$invoice['invoice_status'];
$invoice_date = (string)$invoice['invoice_date'];
$invoice_due = (string)$invoice['invoice_due'];
$invoice_amount = floatval($invoice['invoice_amount']);
$invoice_discount = floatval($invoice['invoice_discount_amount']);
$invoice_currency_code = (string)$invoice['invoice_currency_code'];
$invoice_note = (string)$invoice['invoice_note'];
$client_id = intval($invoice['client_id']);
$client_name = (string)$invoice['client_name'];
$contact_phone = formatPhoneNumber((string)$invoice['contact_phone'], (string)$invoice['contact_phone_country_code']);
if ((string)$invoice['contact_extension'] !== '') {
    $contact_phone .= ' ext. ' . (string)$invoice['contact_extension'];
}

$companySql = mysqli_query(
    $mysqli,
    "SELECT company_address, company_city, company_country, company_email, company_locale,
        company_logo, company_name, company_phone, company_phone_country_code, company_state,
        company_tax_id, company_website, company_zip
    FROM companies WHERE company_id = 1 LIMIT 1"
);
if (!$companySql || mysqli_num_rows($companySql) !== 1) {
    http_response_code(500);
    exit('Company billing profile is unavailable.');
}
$company = mysqli_fetch_assoc($companySql);
$company_name = (string)$company['company_name'];
$company_phone = formatPhoneNumber((string)$company['company_phone'], (string)$company['company_phone_country_code']);
$company_tax_id_display = '';
if ($config_invoice_show_tax_id && (string)$company['company_tax_id'] !== '') {
    $company_tax_id_display = 'Tax ID: ' . (string)$company['company_tax_id'];
}

$currency_format = numfmt_create((string)$company['company_locale'], NumberFormatter::CURRENCY);
$amountPaidSql = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
$amountPaidRow = $amountPaidSql ? mysqli_fetch_assoc($amountPaidSql) : null;
$amount_paid = floatval($amountPaidRow['amount_paid'] ?? 0);
$balance = $invoice_amount - $amount_paid;
$overdue = false;
if (!in_array($invoice_status, ['Paid', 'Draft', 'Cancelled', 'Non-Billable'], true)) {
    $overdue = strtotime($invoice_due) + 86400 < time();
}

$items = [];
$sub_total = 0.00 - $invoice_discount;
$total_tax = 0.00;
$itemsSql = mysqli_query(
    $mysqli,
    "SELECT item_description, item_name, item_price, item_quantity, item_tax, item_total
    FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC"
);
while ($itemsSql && ($item = mysqli_fetch_assoc($itemsSql))) {
    $quantity = floatval($item['item_quantity']);
    $price = floatval($item['item_price']);
    $tax = floatval($item['item_tax']);
    $total = floatval($item['item_total']);
    $sub_total += $price * $quantity;
    $total_tax += $tax;
    $items[] = [
        'name' => (string)$item['item_name'],
        'description' => (string)$item['item_description'],
        'quantity' => rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.'),
        'unit_price' => numfmt_format_currency($currency_format, $price, $invoice_currency_code),
        'tax' => numfmt_format_currency($currency_format, $tax, $invoice_currency_code),
        'amount' => numfmt_format_currency($currency_format, $total, $invoice_currency_code),
    ];
}

$settings = nexusThemeSettingsForSurface(nexusThemeSettings(), 'print');
$brand_name = nexusThemeBrandName($company_name, $settings);
$logo_url = nexusThemeLogoUrl($settings, '', 'light');
$logo_file = '';
if (preg_match('#^/uploads/nexus-theme/logo-light\.(?:png|jpe?g|gif)$#', $logo_url) === 1) {
    $candidate = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
    if (is_file($candidate)) {
        $logo_file = $candidate;
    }
}
if ($logo_file === '' && (string)$company['company_logo'] !== '') {
    $candidate = $_SERVER['DOCUMENT_ROOT'] . '/uploads/settings/' . basename((string)$company['company_logo']);
    if (is_file($candidate)) {
        $logo_file = $candidate;
    }
}

$totals = [
    ['label' => 'Subtotal', 'value' => numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code)],
];
if ($invoice_discount > 0) {
    $totals[] = ['label' => 'Discount', 'value' => '-' . numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code)];
}
if ($total_tax > 0) {
    $totals[] = ['label' => 'Tax', 'value' => numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code)];
}
$totals[] = ['label' => 'Total', 'value' => numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code)];
if ($amount_paid > 0) {
    $totals[] = ['label' => 'Paid', 'value' => numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code), 'tone' => 'success'];
}
$totals[] = ['label' => 'Balance', 'value' => numfmt_format_currency($currency_format, $balance, $invoice_currency_code), 'important' => true];

$pdfData = [
    'brand_name' => $brand_name,
    'tagline' => (string)$settings['branding']['tagline'],
    'logo_file' => $logo_file,
    'logo_height' => (int)round(17 * (int)$settings['branding']['logo_size'] / 100),
    'company_name' => $company_name,
    'company_lines' => [
        formatAddress((string)$company['company_address'], (string)$company['company_city'], (string)$company['company_state'], (string)$company['company_zip'], (string)$company['company_country'], "\n"),
        (string)$company['company_email'] . ($company_phone !== '' ? ' | ' . $company_phone : ''),
        (string)$company['company_website'],
        $company_tax_id_display,
    ],
    'client_name' => $client_name,
    'client_lines' => [
        formatAddress((string)$invoice['location_address'], (string)$invoice['location_city'], (string)$invoice['location_state'], (string)$invoice['location_zip'], (string)$invoice['location_country'], "\n"),
        (string)$invoice['contact_email'] . ($contact_phone !== '' ? ' | ' . $contact_phone : ''),
    ],
    'invoice_label' => $invoice_prefix . $invoice_number,
    'status' => $overdue ? 'Overdue' : $invoice_status,
    'overdue' => $overdue,
    'date' => $invoice_date,
    'due' => $invoice_due,
    'balance' => numfmt_format_currency($currency_format, $balance, $invoice_currency_code),
    'items' => $items,
    'note' => $invoice_note,
    'totals' => $totals,
    'footer' => (string)$config_invoice_footer,
];

require_once '../libs/TCPDF/tcpdf.php';
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Nexus Theme Manager for ITFlow');
$pdf->SetAuthor($brand_name);
$pdf->SetTitle('Invoice ' . $invoice_prefix . $invoice_number);
$pdf->SetSubject('Invoice for ' . $client_name);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9.5);
$pdf->writeHTML(nexusThemeInvoicePdfHtml($pdfData, $settings), true, false, true, false, '');

$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$invoice_date}_{$company_name}_{$client_name}_Invoice_{$invoice_prefix}{$invoice_number}");
$pdf->Output($filename . '.pdf', 'I');
exit;
