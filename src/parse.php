<?php

declare(strict_types=1);

namespace Fpp;

use Phunkie\Validation\Validation;

if (! defined('T_OTHER')) {
    define('T_OTHER', 100000);
}

const parse = '\Fpp\parse';

function parse(string $filename): DefinitionCollection
{
    if (! is_file($filename)) {
        throw new \RuntimeException("'$filename' is not a file");
    }

    if (! is_readable($filename)) {
        throw new \RuntimeException("'$filename' is not readable");
    }

    $namespaceFound = false;
    $contents = file_get_contents($filename);
    $tokens = token_get_all("<?php\n\n$contents");

    $collection = new DefinitionCollection();

    $tokenCount = count($tokens);
    $position = 0;
    $line = 1;
    $namespace = '';

    $nextToken = function(array $tokens) use (&$position, &$tokenCount, &$line, $filename): array {
        if ($position === $tokenCount - 1) {
            throw ParseError::unexpectedEndOfFile($filename);
        }

        $token = $tokens[++$position];

        if (! is_array($token)) {
            $token = [
                T_OTHER,
                $token,
                $line
            ];
        } else {
            $token[2] = $token[2] - 2;
        }

        return $token;
    };

    $skipWhitespace = function(array $token, array $tokens) use (&$position, $nextToken): array {
        if ($token[0] === T_WHITESPACE) {
            $token = $nextToken($tokens);
        };

        return $token;
    };

    $requireWhitespace = function(array $token) use ($filename): void {
        if ($token[0] !== T_WHITESPACE) {
            throw ParseError::unexpectedTokenFound(' ', $token, $filename);
        }
    };

    $requireString = function(array $token) use ($filename): void {
        if ($token[0] !== T_STRING) {
            throw ParseError::expectedString($token, $filename);
        }
    };

    $requireUcFirstString = function(array $token) use ($filename): void {
        if ($token[0] !== T_STRING) {
            throw ParseError::expectedString($token, $filename);
        }

        if ($token[1][0] === strtolower($token[1][0])) {
            throw ParseError::lowerCaseDefinitionName($token, $filename);
        }
    };

    $checkEndOfFile = function(array $token) use (&$position, &$tokenCount, &$filename): bool {
        return $position === $tokenCount - 1;
    };

    $token = $nextToken($tokens);

    if ($checkEndOfFile($token)) {
        return $collection;
    }

    while ($position < $tokenCount - 1) {
        switch ($token[0]) {
            case T_OPEN_TAG:
                break;
            case T_NAMESPACE:
                if ($namespaceFound) {
                    throw ParseError::nestedNamespacesDetected($token[2], $filename);
                }

                $token = $nextToken($tokens);
                $requireWhitespace($token);
                $token = $nextToken($tokens);
                $requireString($token);
                $namespace = $token[1];
                $token = $nextToken($tokens);

                while ($token[0] === T_NS_SEPARATOR) {
                    $token = $nextToken($tokens);
                    $requireString($token);
                    $namespace .= '\\' . $token[1];
                    $token = $nextToken($tokens);
                }

                $token = $skipWhitespace($token, $tokens);

                if ($token[1] === '{') {
                    $namespaceFound = true;
                    break;
                }

                if ($token[1] !== ';') {
                    throw ParseError::unexpectedTokenFound(';', $token, $filename);
                }
                break;
            case T_STRING:
                if ($token[1] !== 'data') {
                    throw ParseError::unknownDefinition($token, $filename);
                }

                // parse name (incl. message name for prooph messages)
                $token = $nextToken($tokens);
                $requireWhitespace($token);
                $token = $nextToken($tokens);
                $requireUcFirstString($token);
                $name = $token[1];
                $token = $nextToken($tokens);
                $token = $skipWhitespace($token, $tokens);
                $messageName = null;

                if ($token[1] === ':') {
                    $token = $nextToken($tokens);
                    $token = $skipWhitespace($token, $tokens);
                    $requireString($token);
                    $messageName = $token[1];
                    $token = $nextToken($tokens);

                    while ($token[0] !== T_WHITESPACE
                        && $token[1] !== '='
                    ) {
                        $messageName .= $token[1];

                        $token = $nextToken($tokens);
                    }

                    $token = $skipWhitespace($token, $tokens);
                }

                if ($token[1] !== '=') {
                    throw ParseError::unexpectedTokenFound('=', $token, $this->filename);
                }

                // parse constructors
                $constructors = [];
                parseConstructor:
                $token = $nextToken($tokens);
                $token = $skipWhitespace($token, $tokens);
                $requireUcFirstString($token);
                $constructorName = $token[1];
                $token = $nextToken($tokens);
                $token = $skipWhitespace($token, $tokens);

                if ($token[1] === ';') {
                    $constructors[] = new Constructor($constructorName);
                    goto buildDefinition;
                }

                if ('|' === $token[1]) {
                    $constructors[] = new Constructor($constructorName);
                    goto parseConstructor;
                }
                buildDefinition:
                $collection->addDefinition(new Definition($namespace, $name, $constructors, [], [], $messageName));
                break;
            case T_WHITESPACE:
                break;
            case T_OTHER:
                if ($token[1] === '}') {
                    if ($namespaceFound) {
                        $namespaceFound = false;
                        $namespace = '';
                    } else {
                        throw ParseError::unexpectedTokenFound('T_STRING or T_WHITESPACE', $token, $this->filename);
                    }
                }
                break;
            default:
                throw ParseError::unexpectedTokenFound('T_STRING or T_WHITESPACE', $token, $this->filename);
        }

        if ($position + 1 < $tokenCount) {
            $token = $nextToken($tokens);
        }
    }

    return $collection;
}
