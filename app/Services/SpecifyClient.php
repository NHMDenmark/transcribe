<?php

namespace App\Services;

/** Read-only access to the Specify API (GET), logging in with the app's credentials when needed. */
class SpecifyClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.specify.url'), '/');
    }

    /** Decoded JSON for an API path such as "/api/specify/locality/7/", or null if unavailable. */
    public function get(string $path): ?array
    {
        if ($this->baseUrl() === '') {
            return null;
        }

        try {
            if (API_is_loggedin() === false) {
                API_login();
            }

            $cookie_file = specify_cookie_file();
            $ch = curl_init($this->baseUrl() . $path);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            $raw = curl_exec($ch);
            curl_close($ch);

            $data = is_string($raw) ? json_decode($raw, true) : null;

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
