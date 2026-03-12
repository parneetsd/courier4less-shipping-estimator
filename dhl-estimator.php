add_action( 'wpforms_process_complete', 'courier4less_dhl_estimate_clean', 10, 4 );

function courier4less_dhl_estimate_clean( $fields, $entry, $form_data, $entry_id ) {

    if ( (int) $form_data['id'] !== 182 ) {
        return;
    }

    $data = [];
    foreach ( $fields as $field ) {
        $data[$field['name']] = $field['value'];
    }

    $origin_country = $data['Origin Country'] ?? '';
    $origin_postal  = $data['Origin Postal Code'] ?? '';
    $dest_country   = $data['Destination Country'] ?? '';
    $dest_postal    = $data['Destination Postal Code'] ?? '';
    $weight         = $data['Weight (kg)'] ?? '';
    $length         = $data['Length (cm)'] ?? '';
    $width          = $data['Width (cm)'] ?? '';
    $height         = $data['Height (cm)'] ?? '';
    $ship_date      = $data['Shipping Date'] ?? '';

    // TEST credentials
    $siteID   = 'v62_wiz3LTBW4o';
    $password = '124q6kSNnO';

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<p:DCTRequest xmlns:p="http://www.dhl.com">
<GetQuote>
<Request>
<ServiceHeader>
<SiteID>'.$siteID.'</SiteID>
<Password>'.$password.'</Password>
</ServiceHeader>
</Request>

<From>
<CountryCode>'.$origin_country.'</CountryCode>
<Postalcode>'.$origin_postal.'</Postalcode>
</From>

<BkgDetails>
<PaymentCountryCode>'.$origin_country.'</PaymentCountryCode>
<Date>'.$ship_date.'</Date>
<ReadyTime>PT10H21M</ReadyTime>
<DimensionUnit>CM</DimensionUnit>
<WeightUnit>KG</WeightUnit>
<Pieces>
<Piece>
<PieceID>1</PieceID>
<Height>'.$height.'</Height>
<Depth>'.$length.'</Depth>
<Width>'.$width.'</Width>
<Weight>'.$weight.'</Weight>
</Piece>
</Pieces>
<IsDutiable>N</IsDutiable>
<NetworkTypeCode>AL</NetworkTypeCode>
</BkgDetails>

<To>
<CountryCode>'.$dest_country.'</CountryCode>
<Postalcode>'.$dest_postal.'</Postalcode>
</To>

</GetQuote>
</p:DCTRequest>';

    $response = wp_remote_post(
        'https://xmlpitest-ea.dhl.com/XMLShippingServlet',
        [
            'headers' => [
                'Content-Type' => 'application/xml'
            ],
            'body' => $xml,
            'timeout' => 20
        ]
    );

    if ( is_wp_error( $response ) ) {
        wp_die( 'DHL API Error: ' . $response->get_error_message() );
    }

    $body = wp_remote_retrieve_body( $response );

    libxml_use_internal_errors(true);
    $xml_obj = simplexml_load_string($body);

    if ( ! $xml_obj ) {
        wp_die('<pre>' . htmlspecialchars($body) . '</pre>');
    }

    $xml_obj->registerXPathNamespace('res', 'http://www.dhl.com');
    $quotes = $xml_obj->xpath('//QtdShp');

    if ( empty($quotes) ) {
        wp_die('<h2>No DHL quotes found.</h2><pre>' . htmlspecialchars($body) . '</pre>');
    }

    echo '<div style="max-width:900px;margin:40px auto;font-family:Arial,sans-serif;">';
    echo '<h2>DHL Estimate Results</h2>';
    echo '<table style="width:100%;border-collapse:collapse;">';
    echo '<tr>
            <th style="border:1px solid #ccc;padding:10px;text-align:left;">Service</th>
            <th style="border:1px solid #ccc;padding:10px;text-align:left;">Estimated Price</th>
            <th style="border:1px solid #ccc;padding:10px;text-align:left;">Transit Days</th>
            <th style="border:1px solid #ccc;padding:10px;text-align:left;">Delivery Date</th>
          </tr>';

    foreach ( $quotes as $quote ) {
        $service = (string) ($quote->ProductShortName ?? $quote->GlobalServiceName ?? 'DHL Service');
        $currency = (string) ($quote->CurrencyCode ?? '');
        $weight_charge = (float) ($quote->WeightCharge ?? 0);
        $transit_days = (string) ($quote->TotalTransitDays ?? '');
        $delivery_date = (string) ($quote->DeliveryDate ?? '');

        $formatted_price = $currency . ' ' . number_format($weight_charge, 2);

        echo '<tr>';
        echo '<td style="border:1px solid #ccc;padding:10px;">' . esc_html($service) . '</td>';
        echo '<td style="border:1px solid #ccc;padding:10px;">' . esc_html($formatted_price) . '</td>';
        echo '<td style="border:1px solid #ccc;padding:10px;">' . esc_html($transit_days) . '</td>';
        echo '<td style="border:1px solid #ccc;padding:10px;">' . esc_html($delivery_date) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</div>';
    exit;
}