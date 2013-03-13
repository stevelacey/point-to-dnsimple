<?php

require __DIR__.'/vendor/autoload.php';

use Buzz\Browser;
use Buzz\Client\Curl as Client;
use Buzz\Listener\BasicAuthListener as BasicAuth;

$config = json_decode(file_get_contents(__DIR__.'/config.json'));

$point = $config->point;
$point->api = 'http://pointhq.com';

$dnsimple = $config->dnsimple;
$dnsimple->api = 'https://dnsimple.com';

// PointHQ

$browser = new Browser(new Client);
$browser->addListener(new BasicAuth($point->username, $point->token));

$headers = array(
    'Accept' => 'application/json',
    'Content-Type' => 'application/json'
);

$zones = json_decode($browser->get($point->api.'/zones', $headers)->getContent());

foreach ($zones as $zone) {
    $point->zones[] = (object) array(
        'name' => $zone->zone->name,
        'records' => json_decode($browser->get($point->api.sprintf('/zones/%d/records', $zone->zone->id), $headers)->getContent())
    );
}

// DNSimple

$browser = new Browser(new Client);

$headers += array(
    'X-DNSimple-Token' => sprintf('%s:%s', $dnsimple->username, $dnsimple->token)
);

foreach ($point->zones as $zone) {
    $browser->post($dnsimple->api.'/domains', $headers, json_encode((object) array(
        'domain' => (object) array(
            'name' => $zone->name
        )
    )));

    foreach ($zone->records as $record) {
        $record = $browser->post($dnsimple->api.sprintf('/domains/%s/records', $zone->name), $headers, json_encode((object) array(
            'record' => (object) array(
                'name' => trim(str_replace($zone->name, '', $record->zone_record->name), '.'),
                'record_type' => $record->zone_record->record_type,
                'content' => $record->zone_record->data,
                'ttl' => $record->zone_record->ttl,
                'prio' => $record->zone_record->aux
            )
        )));
    }
}