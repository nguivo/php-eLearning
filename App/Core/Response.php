<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response
 *
 * Builds and sends the HTTP response. The Router calls setStatusCode(),
 * setHeader(), setBody(), and send() for 404/405 responses.
 * Controllers use redirect() and json() for their own responses.
 * */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }


    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }


    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /** Send a redirect response */
    public function redirect(string $url, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->send();
        exit;
    }


    /** Send a JSON response (for API routes) */
    public function json(mixed $data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json');
        $this->setBody(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->send();
        exit;
    }


    /** Flush status code, headers, and body to the client */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }


}











