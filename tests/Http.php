<?php
declare(strict_types=1);

namespace Tests;

/**
 * A tiny browser for end-to-end tests: keeps cookies per client, does not
 * follow redirects (so tests can assert them), extracts CSRF tokens.
 */
final class Http
{
    public static int $port = 8099;
    private string $jar;
    public int $status = 0;
    public string $body = '';
    public array $headers = [];

    public function __construct(public string $host)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    /** POST a form. Adds the CSRF token from the last page unless $csrf === false. */
    public function post(string $path, array $data = [], bool|string $csrf = true): self
    {
        if ($csrf === true) {
            $data['_csrf'] = $this->csrf();
        } elseif (is_string($csrf)) {
            $data['_csrf'] = $csrf;
        }
        return $this->request('POST', $path, $data);
    }

    /** Fetch a fresh CSRF token for this client's session. */
    public function csrf(): string
    {
        if (!preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $this->body, $m)) {
            $this->get('/login-csrf-probe');
            preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $this->body, $m);
        }
        return $m[1] ?? '';
    }

    public function location(): string
    {
        return $this->headers['location'] ?? '';
    }

    public function cookie(string $name): ?string
    {
        foreach (file($this->jar) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) === 7 && $parts[5] === $name) {
                return $parts[6];
            }
        }
        return null;
    }

    public function request(string $method, string $path, array $data = [], array $extraHeaders = []): self
    {
        $ch = curl_init("http://{$this->host}:" . self::$port . $path);
        $this->headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_RESOLVE => ["{$this->host}:" . self::$port . ':127.0.0.1'],
            CURLOPT_HTTPHEADER => $extraHeaders,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HEADERFUNCTION => function ($ch, $h) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $this->headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($h);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $hasFile = (bool) array_filter($data, fn($v) => $v instanceof \CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
        }
        $body = curl_exec($ch);
        if ($body === false) {
            throw new \RuntimeException('HTTP error: ' . curl_error($ch));
        }
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // Keep the last HTML page so csrf() can read its token; don't overwrite it with a redirect body.
        if ($this->status < 300 || $this->status >= 400) {
            $this->body = (string) $body;
        }
        curl_close($ch);
        return $this;
    }
}
