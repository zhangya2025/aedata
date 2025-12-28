<?php
return [
  'enable'         => true,
  'log'            => false,
  'freeze_updates' => true,
  'silence_notices'=> true,
  'block_remote'   => true,
  'fonts_mode'     => 'system',
  'deny_hosts'     => [
    'assets.elementor.com',
    'my.elementor.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'plugins.svn.wordpress.org',
    'github.com',
    'api.github.com',
    'raw.githubusercontent.com',
    'go.elementor.com',
  ],
  'allow_hosts'    => [],
];
