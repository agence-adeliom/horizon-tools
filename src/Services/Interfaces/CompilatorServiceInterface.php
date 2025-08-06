<?php

namespace Adeliom\HorizonTools\Services\Interfaces;

interface CompilatorServiceInterface
{
    static function getPath(string $handle): string;

    static function getUrl(string $handle): false|string;

    static function getUrlByRegex(string $regex): false|string;
}
