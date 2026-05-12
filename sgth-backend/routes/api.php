<?php

use Illuminate\Support\Facades\Route;

// Las rutas se irán habilitando en cada Sprint a medida que se creen los controladores.
Route::get('/up', function () {
    return response('OK', 200);
});
