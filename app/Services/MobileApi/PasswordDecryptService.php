<?php

namespace App\Services\MobileApi;

/**
 * Service to decrypt legacy employee passwords.
 * Extracted from AuthController to be reusable across Mobile API.
 */
class PasswordDecryptService
{
    protected array $strcode;

    public function __construct()
    {
        $this->strcode = ['', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E', 'f', 'F', 'g', 'G', 'h', 'H', 'i', 'I', 'j', 'J', 'k', 'K', 'l', 'L', 'm', 'M', 'n', 'N', 'o', 'O', 'p', 'P', 'q', 'Q', 'r', 'R', 's', 'S', 't', 'T', 'u', 'U', 'v', 'V', 'w', 'W', 'x', 'X', 'y', 'Y', 'z', 'Z', '#', '@', '$', '%', '^', '&', '*', '_', '!', '?', ' '];
    }

    public function decrypt(string $encryptedText): string
    {
        $chunks = str_split($encryptedText, 3);
        $output = '';
        foreach ($chunks as $chunk) {
            $output .= $this->derandomized($chunk);
        }

        return $output;
    }

    private function derandomized(string $chunk): string
    {
        $arr = $this->strsplt($chunk, strlen($chunk) - 1);
        $output = '';
        for ($x = 0; $x < strlen($chunk) - 1; $x++) {
            $s = $this->keyLocator(substr($arr[0], $x, 1));
            $t = $this->keyLocator($arr[1]);
            $newcode = $s - $t;
            if ($newcode < 0) {
                $newcode += count($this->strcode) - 1;
            }
            if ($newcode == 0 && $s != 0) {
                $newcode = count($this->strcode) - 1;
            }
            $output .= $this->strcode[$newcode];
        }

        return $output;
    }

    private function strsplt(string $text, int $size = 1): array
    {
        $chunks = [];
        $length = strlen($text);
        for ($i = 0; $i < $length; $i += $size) {
            $chunks[] = substr($text, $i, $size);
        }

        return $chunks;
    }

    private function keyLocator(string $char): int
    {
        $index = array_search($char, $this->strcode);

        return $index !== false ? $index : 0;
    }
}
