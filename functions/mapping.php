<?php

function normalizeHeader(
    $text
): string
{
    $text =
    strtoupper(
        trim(
            (string)$text
        )
    );

    $text =
    str_replace(
        [
            '_',
            '-',
            '.',
            '/',
            '\\'
        ],
        ' ',
        $text
    );

    $text =
    preg_replace(
        '/\s+/',
        ' ',
        $text
    );

    return $text;
}

function autoMapHeader(
    $headerB,
    array $headersA
): string
{
    $headerB =
    trim(
        (string)$headerB
    );

    if(
        $headerB === ''
    )
    {
        return '';
    }

    $needle =
    normalizeHeader(
        $headerB
    );

    $aliases = [

        'NIK' => [
            'NO KTP',
            'NOMOR KTP',
            'NO IDENTITAS',
            'ID PESERTA',
            'NIK KTP',
            'NO NIK'
        ],

        'NAMA' => [
            'NAMA LENGKAP',
            'FULL NAME',
            'FULLNAME',
            'NAMA PESERTA'
        ],

        'KPJ' => [
            'NO KPJ',
            'NOMOR KPJ',
            'KPJ TK'
        ],

        'ALAMAT' => [
            'ADDRESS',
            'DOMISILI'
        ],

        'TELEPON' => [
            'HP',
            'NO HP',
            'NOMOR HP',
            'HANDPHONE'
        ],

        'EMAIL' => [
            'E MAIL',
            'MAIL'
        ],

        'TANGGAL LAHIR' => [
            'TTL',
            'TGL LAHIR',
            'DATE OF BIRTH'
        ]

    ];

    foreach(
        $headersA
        as
        $headerA
    )
    {
        $headerA =
        trim(
            (string)$headerA
        );

        if(
            $headerA === ''
        )
        {
            continue;
        }

        $normalizedA =
        normalizeHeader(
            $headerA
        );

        if(
            $normalizedA === $needle
        )
        {
            return $headerA;
        }
    }

    foreach(
        $aliases
        as
        $masterHeader
        =>
        $aliasList
    )
    {
        $masterNormalized =
        normalizeHeader(
            $masterHeader
        );

        $allAliases =
        array_merge(
            [$masterHeader],
            $aliasList
        );

        foreach(
            $allAliases
            as
            $alias
        )
        {
            if(
                normalizeHeader(
                    $alias
                )
                ===
                $needle
            )
            {
                foreach(
                    $headersA
                    as
                    $headerA
                )
                {
                    if(
                        normalizeHeader(
                            $headerA
                        )
                        ===
                        $masterNormalized
                    )
                    {
                        return $headerA;
                    }
                }
            }
        }
    }

    foreach(
        $headersA
        as
        $headerA
    )
    {
        $headerA =
        trim(
            (string)$headerA
        );

        if(
            $headerA === ''
        )
        {
            continue;
        }

        $normalizedA =
        normalizeHeader(
            $headerA
        );

        if(
            strpos(
                $normalizedA,
                $needle
            )
            !== false
            ||
            strpos(
                $needle,
                $normalizedA
            )
            !== false
        )
        {
            return $headerA;
        }
    }

    $bestMatch = '';
    $bestScore = 0;

    foreach(
        $headersA
        as
        $headerA
    )
    {
        $headerA =
        trim(
            (string)$headerA
        );

        if(
            $headerA === ''
        )
        {
            continue;
        }

        $normalizedA =
        normalizeHeader(
            $headerA
        );

        similar_text(
            $needle,
            $normalizedA,
            $score
        );

        if(
            $score > $bestScore
        )
        {
            $bestScore = $score;
            $bestMatch = $headerA;
        }
    }

    if(
        $bestScore >= 75
    )
    {
        return $bestMatch;
    }

    return '';
}