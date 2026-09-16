<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP saliente (código, cabeceras, cuerpo).
 */
final class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    public function json(array $data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = (string) json_encode($data);
        return $this;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self())
            ->status($status)
            ->withHeader('Location', $url);
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);
        if (!headers_sent()) {
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}