<?php
/**
 * Seed data for default extra options.
 *
 * @package WooSpiegelloftConfigurator
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return 
array (
  0 => 
  array (
    'group' => 'suspension_plates',
    'legacy_id' => 2,
    'title' => 'Standard loose',
    'value' => 'standard-loose',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mounting-standard_lose.png',
  ),
  1 => 
  array (
    'group' => 'suspension_plates',
    'legacy_id' => 3,
    'title' => 'Standard pre-assembled',
    'value' => 'standard-preassembled',
    'price' => 4.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mounting-standard-vormontiert.png',
  ),
  2 => 
  array (
    'group' => 'suspension_plates',
    'legacy_id' => 4,
    'title' => 'Profiplus set loose',
    'value' => 'profiplus',
    'price' => 15.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mounting-profiplus-set_lose.png',
  ),
  3 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 2,
    'title' => 'LED neutralweiß 4000K',
    'value' => 'neutral',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-neutral.png',
  ),
  4 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 3,
    'title' => 'LED warmweiß 3000K',
    'value' => 'warm',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-warm.png',
  ),
  5 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 4,
    'title' => 'LED kaltweiß 6000K',
    'value' => 'cold',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-cool.png',
  ),
  6 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 5,
    'title' => 'Dimmbar 2700K bis 6300K inkl. Gestensteuerung',
    'value' => 'dimmable_gesture',
    'price' => 167.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-gesture-control.png',
    'nested' => 
    array (
      'placement' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  7 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 6,
    'title' => 'Dimmbar 2700–6300K mit Fernbedienung',
    'value' => 'dimmable_remote',
    'price' => 147.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-remote-control.png',
  ),
  8 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 7,
    'title' => 'LED warm+kaltweiß mit 2x Touch',
    'value' => 'warm_cold_touch',
    'price' => 87.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-touch-sensor.png',
    'nested' => 
    array (
      'placement' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  9 => 
  array (
    'group' => 'light_color',
    'legacy_id' => 8,
    'title' => 'LED RGB mit Fernbedienung',
    'value' => 'rgb',
    'price' => 67.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/light-color-rgb.png',
  ),
  10 => 
  array (
    'group' => 'led_strip',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/led-band-no-selected.png',
  ),
  11 => 
  array (
    'group' => 'led_strip',
    'legacy_id' => 2,
    'title' => 'Yes',
    'value' => 'yes',
    'price' => 47.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/led-band-doppelt.png',
  ),
  12 => 
  array (
    'group' => 'switch_sensor',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
    'nested' => 
    array (
      'position' => 
      array (
      ),
    ),
  ),
  13 => 
  array (
    'group' => 'switch_sensor',
    'legacy_id' => 2,
    'title' => 'Kippschalter',
    'value' => 'toggle-switch',
    'price' => 6.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/switch-sensor-kippschalter.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  14 => 
  array (
    'group' => 'switch_sensor',
    'legacy_id' => 3,
    'title' => 'Touch Sensor',
    'value' => 'touch-sensor',
    'price' => 39.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/switch-sensor-touch-sensor.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  15 => 
  array (
    'group' => 'switch_sensor',
    'legacy_id' => 4,
    'title' => 'WiFi Schalter App Sprachst. Alexa',
    'value' => 'wifi-switch',
    'price' => 49.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/switch-sensor-wifi-with-alexa.png',
    'nested' => 
    array (
      'position' => 
      array (
      ),
    ),
  ),
  16 => 
  array (
    'group' => 'side_panels',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  17 => 
  array (
    'group' => 'side_panels',
    'legacy_id' => 2,
    'title' => 'Seitenblenden',
    'value' => 'side-panels',
    'price' => 14.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/side-panel-seitenblenden.png',
  ),
  18 => 
  array (
    'group' => 'side_panels',
    'legacy_id' => 3,
    'title' => 'Vollverblendung',
    'value' => 'full-veneer',
    'price' => 39.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/side-panel-vollverbiendung.png',
  ),
  19 => 
  array (
    'group' => 'material',
    'legacy_id' => 1,
    'title' => 'Kunststoff (lichtdurchlässig)',
    'value' => 'plastic',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/aperture-material-kunststoff.png',
  ),
  20 => 
  array (
    'group' => 'glass_shelf',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  21 => 
  array (
    'group' => 'glass_shelf',
    'legacy_id' => 2,
    'title' => 'Klarglas',
    'value' => 'clear-glass',
    'price' => 29.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/clear-glass.png',
  ),
  22 => 
  array (
    'group' => 'glass_shelf',
    'legacy_id' => 3,
    'title' => 'satiniertes Glas',
    'value' => 'satin-glass',
    'price' => 59.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/satin-glass.png',
  ),
  23 => 
  array (
    'group' => 'clock',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
    'nested' => 
    array (
      'position' => 
      array (
      ),
    ),
  ),
  24 => 
  array (
    'group' => 'clock',
    'legacy_id' => 2,
    'title' => 'Digitaluhr',
    'value' => 'digital',
    'price' => 39.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/digital-clock.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Oben Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/oben-links.png',
        ),
        1 => 
        array (
          'title' => 'Oben Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/oben-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Oben Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/oben-rechts.png',
        ),
        3 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        4 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        5 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  25 => 
  array (
    'group' => 'mirror_heating',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
    'nested' => 
    array (
      'position' => 
      array (
      ),
    ),
  ),
  26 => 
  array (
    'group' => 'mirror_heating',
    'legacy_id' => 2,
    'title' => 'ohne Schalter',
    'value' => 'without-switch',
    'price' => 45.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mirror-heating-without-switch.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  27 => 
  array (
    'group' => 'mirror_heating',
    'legacy_id' => 3,
    'title' => 'mit Kippschalter',
    'value' => 'with-toggle-switch',
    'price' => 48.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mirror-heating-with-toggle-switch.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  28 => 
  array (
    'group' => 'mirror_heating',
    'legacy_id' => 4,
    'title' => 'mit Touchsensor',
    'value' => 'with-touch-sensor',
    'price' => 65.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/mirror-heating-with-touch-sensor.png',
    'nested' => 
    array (
      'position' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
    ),
  ),
  29 => 
  array (
    'group' => 'sockets',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  30 => 
  array (
    'group' => 'sockets',
    'legacy_id' => 2,
    'title' => '1x Steckdose',
    'value' => '1x-socket',
    'price' => 45.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-socket-schwarz-glanzend.png',
    'nested' => 
    array (
      'socket_position' => 
      array (
        0 => 
        array (
          'title' => 'Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
      'socket_colors' => 
      array (
        0 => 
        array (
          'title' => 'schwarz glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-socket-schwarz-glanzend.png',
        ),
        1 => 
        array (
          'title' => 'anthrazit matt',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-socket-anthrazit-matt.png',
        ),
        2 => 
        array (
          'title' => 'polarweiß glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-socket-polarweiss.png',
        ),
        3 => 
        array (
          'title' => 'grau glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-socket-grau-glanzend.png',
        ),
      ),
    ),
  ),
  31 => 
  array (
    'group' => 'sockets',
    'legacy_id' => 3,
    'title' => '2x Steckdosen',
    'value' => '2x-socket',
    'price' => 89.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-socket-grau-glanzend.png',
    'nested' => 
    array (
      'socket_position' => 
      array (
        0 => 
        array (
          'title' => '1x rechts + 1x links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/1x-right-1x-left.jpg',
        ),
        1 => 
        array (
          'title' => '2x Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-center.jpg',
        ),
        2 => 
        array (
          'title' => '2x rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-right.jpg',
        ),
        3 => 
        array (
          'title' => '2x links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-left.png',
        ),
      ),
      'socket_colors' => 
      array (
        0 => 
        array (
          'title' => 'schwarz glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-socket-schwarz-glanzend.png',
        ),
        1 => 
        array (
          'title' => 'anthrazit matt',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-socket-anthrazit-matt.png',
        ),
        2 => 
        array (
          'title' => 'polarweiß glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-socket-polarweiss.png',
        ),
        3 => 
        array (
          'title' => 'grau glänzend',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/2x-socket-grau-glanzend.png',
        ),
      ),
    ),
  ),
  32 => 
  array (
    'group' => 'makeup_mirror',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  33 => 
  array (
    'group' => 'makeup_mirror',
    'legacy_id' => 2,
    'title' => '5-fach Vergrößerung, unbeleuchtet',
    'value' => 'unbeleuchtet',
    'price' => 51.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-unbeleuchtet.png',
    'nested' => 
    array (
      'mirror_type' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Ø152 mm auf Glas',
          'value' => '152mm-glass',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-unbeleuchtet-aufs-glas.png',
          'price' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Ø152 mm integriert',
          'value' => '152mm-integrated',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-unbeleuchtet-integrierte.png',
          'price' => 44.99,
        ),
      ),
      'mirror_position' => 
      array (
        0 => 
        array (
          'title' => 'Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
      'side_distance' => true,
      'bottom_distance' => true,
    ),
  ),
  34 => 
  array (
    'group' => 'makeup_mirror',
    'legacy_id' => 3,
    'title' => '5-fach Vergrößerung, beleuchtet',
    'value' => 'beleuchtet',
    'price' => 67.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-beleuchtet.png',
    'nested' => 
    array (
      'mirror_type' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Ø152 mm auf Glas',
          'value' => '152mm-glass',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-beleuchtet-aufs-glas.png',
          'price' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Ø152 mm integriert',
          'value' => '152mm-integrated',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-beleuchtet-intergriert.png',
          'price' => 44.99,
        ),
      ),
      'mirror_position' => 
      array (
        0 => 
        array (
          'title' => 'Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
      'mirror_light_color' => 
      array (
        0 => 
        array (
          'title' => 'LED neutralweiß 4000K',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-kalt.png',
        ),
        1 => 
        array (
          'title' => 'LED warmweiß 3000K',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-warm.png',
        ),
        2 => 
        array (
          'title' => 'LED kaltweiß 6000K',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-neutral.png',
        ),
      ),
      'separate_switch' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => '---',
          'value' => '---',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
          'price' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Wippschalter',
          'value' => 'toggle-switch',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-toggle-switch.png',
          'price' => 6.99,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Touch Sensor',
          'value' => 'touch-sensor',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/makeup-mirror-touch-sensor.png',
          'price' => 39.99,
        ),
      ),
      'position_switch' => 
      array (
        0 => 
        array (
          'title' => 'Unten Links',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-links.png',
        ),
        1 => 
        array (
          'title' => 'Unten Mitte',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-mitte.png',
        ),
        2 => 
        array (
          'title' => 'Unten Rechts',
          'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/unten-rechts.png',
        ),
      ),
      'side_distance' => true,
      'bottom_distance' => true,
    ),
  ),
  35 => 
  array (
    'group' => 'sealing',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  36 => 
  array (
    'group' => 'sealing',
    'legacy_id' => 2,
    'title' => 'Yes',
    'value' => 'yes',
    'price' => 74.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/sealing-yes.png',
  ),
  37 => 
  array (
    'group' => 'bluetooth',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  38 => 
  array (
    'group' => 'bluetooth',
    'legacy_id' => 2,
    'title' => 'Standard Lautsprecher',
    'value' => 'standard',
    'price' => 79.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/bluetooth-speaker-standard.png',
  ),
  39 => 
  array (
    'group' => 'bluetooth',
    'legacy_id' => 3,
    'title' => 'WHD High-End System',
    'value' => 'high-end',
    'price' => 199.0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/bluetooth-speaker-high-end.png',
  ),
  40 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 1,
    'title' => '---',
    'value' => '---',
    'price' => 0,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png',
  ),
  41 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 2,
    'title' => 'Eurostecker weiß',
    'value' => 'euro-plug-white',
    'price' => 9.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/plug-for-socket-eurostecker-weiss.png',
  ),
  42 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 3,
    'title' => 'Eurostecker schwarz',
    'value' => 'euro-plug-black',
    'price' => 10.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/plug-for-socket-eurostecker-schwarz.png',
  ),
  43 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 4,
    'title' => 'Eurostecker grau',
    'value' => 'euro-plug-grey',
    'price' => 10.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/plug-for-socket-eurostecker-grau.png',
  ),
  44 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 5,
    'title' => 'Eurostecker inkl. Kippschalter weiß',
    'value' => 'euro-plug-including-toggle-switch-white',
    'price' => 14.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/plug-for-socket--eurostecker-kippschalter-weiss.png',
  ),
  45 => 
  array (
    'group' => 'plug_socket',
    'legacy_id' => 6,
    'title' => 'Eurostecker inkl. Kippschalter schwarz',
    'value' => 'euro-plug-including-toggle-switch-black',
    'price' => 14.99,
    'image' => 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/plug-for-socket-eurostecker-schwarz-kippschalter.png',
  ),
  46 => 
  array (
    'group' => 'edge',
    'legacy_id' => 0,
    'title' => 'Kanten',
    'value' => 'edge',
    'price' => 0,
    'image' => '',
    'nested' => 
    array (
      'name' => 'Kanten',
      'desc' => 'geschliffen & poliert',
    ),
  ),
);
