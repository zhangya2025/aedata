<?php
return [
  'enable' => true,
  'log' => false,
  'lock_file_mods' => true,
  'lock_caps' => true,
  'freeze_updates' => true,
  'disable_update_cron' => true,
  'block_update_hosts' => true,
  'update_deny_hosts' => [
    'api.wordpress.org',
    'downloads.wordpress.org',
    'plugins.svn.wordpress.org',
    'github.com',
    'api.github.com',
    'raw.githubusercontent.com',
  ],
  'allow_delete' => false,
  'allow_delete_until' => 0,
];
