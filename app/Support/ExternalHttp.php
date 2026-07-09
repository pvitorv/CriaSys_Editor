<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ExternalHttp
{
    public static function client(int $timeout = 20): PendingRequest
    {
        $request = Http::timeout($timeout)
            ->withHeaders(['User-Agent' => 'CriaSys-Editor/1.0']);

        if (! config('criasys.http_verify_ssl', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
