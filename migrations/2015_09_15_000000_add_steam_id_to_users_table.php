<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'steam_id' => ['string', 'length' => 255, 'nullable' => true]
]);
