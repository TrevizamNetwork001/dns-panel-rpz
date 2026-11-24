<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShowPublicLanding extends Authenticate
{
    protected function unauthenticated($request, array $guards): never
    {
        if ($request->expectsJson()) {
            parent::unauthenticated($request, $guards);
        }

        throw new HttpResponseException(response()->view('landing.index'));
    }
}
