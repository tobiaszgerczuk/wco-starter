<?php

namespace WCO\Starter\Migration;

/**
 * Splits an SQL dump (plain or gzip, detected by zlib) into single statements without loading the
 * whole file: it reads line by line and tracks quotes and comments, so a `;` inside a string or
 * a multi-line INSERT never ends a statement.
 */
class SqlReader
{
    /** @return \Generator<int, string> */
    public static function statements(string $path): \Generator
    {
        $fh = @gzopen($path, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Nie można otworzyć pliku.');
        }

        $stmt = '';
        $state = ''; // '' = plain SQL, a quote character, or '/*' inside a block comment.

        try {
            while (($line = gzgets($fh)) !== false) {
                $len = strlen($line);
                $i = 0;
                $start = 0; // Start of the part of $line not yet appended to $stmt.

                while ($i < $len) {
                    if ($state === '') {
                        $i += strcspn($line, ";'\"`#-/", $i);
                        if ($i >= $len) {
                            break;
                        }

                        $c = $line[$i];
                        if ($c === ';') {
                            $stmt .= substr($line, $start, $i - $start);
                            $sql = trim($stmt);
                            $stmt = '';
                            $i++;
                            $start = $i;
                            if ($sql !== '') {
                                yield $sql;
                            }
                        } elseif ($c === "'" || $c === '"' || $c === '`') {
                            $state = $c;
                            $i++;
                        } elseif ($c === '#' || ($c === '-' && ($line[$i + 1] ?? '') === '-' && ctype_space($line[$i + 2] ?? "\n"))) {
                            // Line comment: drop the rest of the line but keep the line break.
                            $stmt .= substr($line, $start, $i - $start) . "\n";
                            $i = $start = $len;
                        } elseif ($c === '/' && ($line[$i + 1] ?? '') === '*') {
                            $state = '/*';
                            $i += 2;
                        } else {
                            $i++;
                        }
                    } elseif ($state === '/*') {
                        $end = strpos($line, '*/', $i);
                        if ($end === false) {
                            $i = $len;
                        } else {
                            $i = $end + 2;
                            $state = '';
                        }
                    } else {
                        // Inside a quoted string or identifier; a doubled quote closes and reopens it.
                        $i += strcspn($line, $state === '`' ? '`' : $state . '\\', $i);
                        if ($i >= $len) {
                            break;
                        }

                        if ($line[$i] === '\\') {
                            $i += 2;
                        } else {
                            $state = '';
                            $i++;
                        }
                    }
                }

                if ($start < $len) {
                    $stmt .= substr($line, $start);
                }
            }
        } finally {
            gzclose($fh);
        }

        if (trim($stmt) !== '') {
            throw new \RuntimeException('Plik kończy się w połowie polecenia SQL — wygląda na ucięty.');
        }
    }
}
