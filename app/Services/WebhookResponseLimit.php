<?php

namespace App\Services;

final class WebhookResponseLimit
{
    public bool $exceeded = false;

    private int $bodyBytes = 0;

    private int $headerBytes = 0;

    public function body(string $chunk): int
    {
        $this->bodyBytes += strlen($chunk);

        return $this->accept($this->bodyBytes, 65536, strlen($chunk));
    }

    public function headers(string $line): int
    {
        $this->headerBytes += strlen($line);

        return $this->accept($this->headerBytes, 16384, strlen($line));
    }

    private function accept(int $bytes, int $maximum, int $length): int
    {
        if ($bytes > $maximum) {
            $this->exceeded = true;

            return 0;
        }

        return $length;
    }
}
