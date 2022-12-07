<?php

Route::get('/domain-verify', [App\Http\Controllers\CaddyProxyController::class, 'verifyDomain']);
