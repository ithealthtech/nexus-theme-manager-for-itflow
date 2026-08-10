<?php

declare(strict_types=1);

function nexusThemePdfEscape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nexusThemePdfLines(array $lines): string
{
    $clean = [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line !== '') {
            $clean[] = nl2br(nexusThemePdfEscape($line));
        }
    }
    return implode('<br>', $clean);
}

function nexusThemePdfColor(mixed $value, string $fallback): string
{
    $value = strtolower(trim((string)$value));
    return preg_match('/^#[a-f0-9]{6}$/', $value) === 1 ? $value : $fallback;
}

function nexusThemeInvoicePdfHtml(array $invoice, array $settings): string
{
    $colors = is_array($settings['colors'] ?? null) ? $settings['colors'] : [];
    $primary = nexusThemePdfColor($colors['primary'] ?? null, '#5aaeea');
    $header = nexusThemePdfColor($colors['sidebar'] ?? null, '#121124');
    $headerText = nexusThemeContrastColor($header);
    $primaryText = nexusThemeContrastColor($primary);
    $surface = nexusThemeMixColors('#ffffff', $primary, 94);
    $border = nexusThemeMixColors('#ffffff', $header, 84);
    $muted = nexusThemeMixColors('#ffffff', $header, 38);
    $brand = nexusThemePdfEscape($invoice['brand_name'] ?? 'Support Portal');
    $tagline = nexusThemePdfEscape($invoice['tagline'] ?? '');
    $invoiceLabel = nexusThemePdfEscape($invoice['invoice_label'] ?? '');
    $status = nexusThemePdfEscape($invoice['status'] ?? '');
    $statusColor = strtolower((string)($invoice['status'] ?? '')) === 'paid' ? '#16865b' : $primary;
    if (!empty($invoice['overdue'])) {
        $statusColor = '#c8324d';
    }

    $logo = '';
    $logoFile = (string)($invoice['logo_file'] ?? '');
    if ($logoFile !== '' && is_file($logoFile)) {
        $logoHeight = max(12, min(24, (int)($invoice['logo_height'] ?? 17)));
        $logo = '<img src="' . nexusThemePdfEscape($logoFile) . '" height="' . $logoHeight . '">';
    }
    if ($logo === '') {
        $logo = '<span style="color:' . $headerText . ';font-size:17pt;font-weight:bold;">' . $brand . '</span>';
    }

    $html = '<table width="100%" cellspacing="0" cellpadding="10" style="background-color:' . $header . ';color:' . $headerText . ';">'
        . '<tr><td width="56%">' . $logo . '</td><td width="44%" align="right">'
        . '<span style="color:' . $primary . ';font-size:8pt;font-weight:bold;">SECURE BILLING PORTAL</span><br>'
        . '<span style="color:' . $headerText . ';font-size:20pt;font-weight:bold;">Invoice details</span>'
        . '</td></tr>';
    if ($tagline !== '') {
        $html .= '<tr><td colspan="2" style="color:' . $headerText . ';font-size:8.5pt;">' . $tagline . '</td></tr>';
    }
    $html .= '</table><table width="100%" cellspacing="0" cellpadding="0"><tr><td height="3" style="background-color:' . $primary . ';"></td></tr></table><br><br>';

    $html .= '<table width="100%" cellspacing="0" cellpadding="4"><tr>'
        . '<td width="62%"><span style="color:' . $primary . ';font-size:8pt;font-weight:bold;">BILLING DOCUMENT</span><br>'
        . '<span style="color:' . $header . ';font-size:17pt;font-weight:bold;">' . nexusThemePdfEscape($invoice['company_name'] ?? $brand) . '</span><br>'
        . '<span style="color:' . $muted . ';font-size:9pt;line-height:1.5;">' . nexusThemePdfLines((array)($invoice['company_lines'] ?? [])) . '</span></td>'
        . '<td width="38%" align="right"><span style="color:' . $header . ';font-size:27pt;font-weight:bold;">INVOICE</span><br>'
        . '<span style="color:' . $muted . ';font-size:10pt;">' . $invoiceLabel . '</span><br><br>'
        . '<span style="background-color:' . $statusColor . ';color:#ffffff;font-size:8pt;font-weight:bold;">&nbsp;&nbsp;' . $status . '&nbsp;&nbsp;</span></td>'
        . '</tr></table><br>';

    $html .= '<table width="100%" cellspacing="0" cellpadding="7"><tr style="background-color:' . $surface . ';">'
        . '<td width="58%"><span style="color:' . $primary . ';font-size:8pt;font-weight:bold;">BILL TO</span><br>'
        . '<span style="color:' . $header . ';font-size:12pt;font-weight:bold;">' . nexusThemePdfEscape($invoice['client_name'] ?? '') . '</span><br>'
        . '<span style="color:' . $muted . ';font-size:9pt;line-height:1.45;">' . nexusThemePdfLines((array)($invoice['client_lines'] ?? [])) . '</span></td>'
        . '<td width="18%"><span style="color:' . $muted . ';font-size:8pt;font-weight:bold;">ISSUED</span><br><span style="color:' . $header . ';font-size:10pt;">' . nexusThemePdfEscape($invoice['date'] ?? '') . '</span><br><br>'
        . '<span style="color:' . $muted . ';font-size:8pt;font-weight:bold;">DUE</span><br><span style="color:' . $header . ';font-size:10pt;">' . nexusThemePdfEscape($invoice['due'] ?? '') . '</span></td>'
        . '<td width="24%" align="right"><span style="color:' . $muted . ';font-size:8pt;font-weight:bold;">BALANCE DUE</span><br>'
        . '<span style="color:' . $header . ';font-size:16pt;font-weight:bold;">' . nexusThemePdfEscape($invoice['balance'] ?? '') . '</span></td>'
        . '</tr></table><br><br>';

    $html .= '<table width="100%" cellspacing="0" cellpadding="6" border="0">'
        . '<tr style="background-color:' . $primary . ';color:' . $primaryText . ';">'
        . '<th width="24%" align="left"><strong>ITEM</strong></th>'
        . '<th width="30%" align="left"><strong>DESCRIPTION</strong></th>'
        . '<th width="8%" align="center"><strong>QTY</strong></th>'
        . '<th width="14%" align="right"><strong>PRICE</strong></th>'
        . '<th width="10%" align="right"><strong>TAX</strong></th>'
        . '<th width="14%" align="right"><strong>AMOUNT</strong></th></tr>';
    $rowIndex = 0;
    foreach ((array)($invoice['items'] ?? []) as $item) {
        $rowBackground = $rowIndex % 2 === 0 ? '#ffffff' : $surface;
        $html .= '<tr style="background-color:' . $rowBackground . ';color:' . $header . ';">'
            . '<td width="24%"><strong>' . nexusThemePdfEscape($item['name'] ?? '') . '</strong></td>'
            . '<td width="30%">' . nl2br(nexusThemePdfEscape($item['description'] ?? '')) . '</td>'
            . '<td width="8%" align="center">' . nexusThemePdfEscape($item['quantity'] ?? '') . '</td>'
            . '<td width="14%" align="right">' . nexusThemePdfEscape($item['unit_price'] ?? '') . '</td>'
            . '<td width="10%" align="right">' . nexusThemePdfEscape($item['tax'] ?? '') . '</td>'
            . '<td width="14%" align="right"><strong>' . nexusThemePdfEscape($item['amount'] ?? '') . '</strong></td></tr>';
        $rowIndex++;
    }
    $html .= '</table><br><br>';

    $note = trim((string)($invoice['note'] ?? ''));
    $html .= '<table width="100%" cellspacing="0" cellpadding="5"><tr><td width="56%">';
    if ($note !== '') {
        $html .= '<span style="color:' . $primary . ';font-size:8pt;font-weight:bold;">INVOICE NOTE</span><br>'
            . '<span style="color:' . $muted . ';font-size:9pt;line-height:1.45;">' . nl2br(nexusThemePdfEscape($note)) . '</span>';
    }
    $html .= '</td><td width="44%"><table width="100%" cellspacing="0" cellpadding="4">';
    foreach ((array)($invoice['totals'] ?? []) as $total) {
        $important = !empty($total['important']);
        $tone = ($total['tone'] ?? '') === 'success' ? '#16865b' : $header;
        $fontSize = $important ? '13pt' : '9.5pt';
        $weight = $important ? 'font-weight:bold;' : '';
        $html .= '<tr><td style="color:' . $tone . ';font-size:' . $fontSize . ';' . $weight . '">' . nexusThemePdfEscape($total['label'] ?? '') . '</td>'
            . '<td align="right" style="color:' . $tone . ';font-size:' . $fontSize . ';' . $weight . '">' . nexusThemePdfEscape($total['value'] ?? '') . '</td></tr>';
    }
    $html .= '</table></td></tr></table><br><br>';

    $footer = trim((string)($invoice['footer'] ?? ''));
    if ($footer !== '') {
        $html .= '<table width="100%" cellspacing="0" cellpadding="6"><tr><td style="border-top:1px solid ' . $border . ';color:' . $muted . ';font-size:8.5pt;text-align:center;">'
            . nl2br(nexusThemePdfEscape($footer)) . '</td></tr></table>';
    }

    return $html;
}
