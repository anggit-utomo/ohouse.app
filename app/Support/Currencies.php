<?php

namespace App\Support;

final class Currencies
{
    /** code => [name, symbol, digits after the decimal point, locale used for formatting] */
    private const LIST = [
        'IDR' => ['Indonesian Rupiah', 'Rp', 0, 'id-ID'],
        'USD' => ['US Dollar', '$', 2, 'en-US'],
        'EUR' => ['Euro', '€', 2, 'de-DE'],
        'GBP' => ['British Pound', '£', 2, 'en-GB'],
        'SGD' => ['Singapore Dollar', 'S$', 2, 'en-SG'],
        'MYR' => ['Malaysian Ringgit', 'RM', 2, 'ms-MY'],
        'AUD' => ['Australian Dollar', 'A$', 2, 'en-AU'],
        'JPY' => ['Japanese Yen', '¥', 0, 'ja-JP'],
        'KRW' => ['South Korean Won', '₩', 0, 'ko-KR'],
        'INR' => ['Indian Rupee', '₹', 2, 'en-IN'],
        'PHP' => ['Philippine Peso', '₱', 2, 'en-PH'],
        'THB' => ['Thai Baht', '฿', 2, 'th-TH'],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    /** @return list<array{code: string, name: string, symbol: string}> */
    public static function all(): array
    {
        return collect(self::LIST)->map(
            fn ($c, $code) => ['code' => $code, 'name' => $c[0], 'symbol' => $c[1]]
        )->values()->all();
    }

    /** @return array{code: string, symbol: string, exponent: int, locale: string} */
    public static function for(?string $code): array
    {
        $code = isset(self::LIST[$code]) ? $code : 'IDR';

        [, $symbol, $exponent, $locale] = self::LIST[$code];

        return [
            'code' => $code,
            'symbol' => $symbol,
            'exponent' => $exponent,
            'locale' => $locale,
        ];
    }

    public static function exponent(string $code): int
    {
        return self::for($code)['exponent'];
    }

    /** For messages written by the server: "Rp 1.234.567" or "$ 12.34". */
    public static function format(int $minor, string $code): string
    {
        $c = self::for($code);
        $value = $minor / 10 ** $c['exponent'];
        $number = $c['code'] === 'IDR'
            ? number_format($value, $c['exponent'], ',', '.')
            : number_format($value, $c['exponent'], '.', ',');

        return $c['symbol'].' '.$number;
    }
}
