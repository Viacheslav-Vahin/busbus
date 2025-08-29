<?php
return [
    'manager_emails' => array_filter(array_map('trim',
        explode(',', env('WP_MANAGER_EMAILS', ''))
    )),
];
