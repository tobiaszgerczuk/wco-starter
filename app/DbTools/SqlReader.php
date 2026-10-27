<?php

namespace WCO\Starter\DbTools;

/**
 * Streams SQL statements out of a (possibly large) dump file without loading it into memory.
 * Understands quoted strings (with backslash escapes and doubled quotes), backtick identifiers,
 * `--`, `#` and block comments, and `/*!...*​/` conditional comments. A statement ends at a `;`
 * outside quotes and comments. The byte offset after the last statement can be stored and resumed.
 */
final class SqlReader
{
    private const CHUNK = 262144;

    /** @var resource|null */
    private $fh = null;
    private string $buf = '';
    private int $pos = 0;
    private int $base = 0;   // absolute file offset of $buf[0]
    private bool $eof = false;

    public function __construct(private string $path)
    {
    }

    public function open(int $offset = 0): void
    {
        $this->close();
        $this->fh = fopen($this->path, 'rb');
        if ($this->fh === false) {
            throw new \RuntimeException('Nie można otworzyć pliku SQL.');
        }
        fseek($this->fh, $offset);
        $this->buf = '';
        $this->pos = 0;
        $this->base = $offset;
        $this->eof = false;
    }

    public function close(): void
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
        $this->fh = null;
    }

    /** Absolute offset of the first byte not yet consumed. */
    public function offset(): int
    {
        return $this->base + $this->pos;
    }

    private function fill(): bool
    {
        if ($this->eof || !is_resource($this->fh)) {
            return false;
        }
        $chunk = fread($this->fh, self::CHUNK);
        if ($chunk === '' || $chunk === false) {
            $this->eof = true;

            return false;
        }
        // drop what was already consumed so the buffer stays small
        if ($this->pos > self::CHUNK) {
            $this->buf = substr($this->buf, $this->pos);
            $this->base += $this->pos;
            $this->pos = 0;
        }
        $this->buf .= $chunk;

        return true;
    }

    /** @return string|null next statement (without the closing `;`), or null at the end of the file */
    public function next(): ?string
    {
        $start = null;
        $state = 0; // 0 plain, 1 '...', 2 "...", 3 `...`, 4 line comment, 5 block comment
        $i = $this->pos;

        while (true) {
            $len = strlen($this->buf);

            if ($i >= $len) {
                $shift = $this->pos;
                if (!$this->fill()) {
                    if ($start === null) {
                        $this->pos = $len;

                        return null;
                    }
                    $sql = trim(substr($this->buf, $start));
                    $this->pos = $len;

                    return $sql === '' ? null : $sql;
                }
                // fill() may have shifted the buffer
                $delta = $shift - $this->pos;
                $i -= $delta;
                if ($start !== null) {
                    $start -= $delta;
                }
                continue;
            }

            if ($start === null) {
                $i += strspn($this->buf, " \t\r\n", $i);
                if ($i >= $len) {
                    continue;
                }
                $c = $this->buf[$i];
                $n = $this->buf[$i + 1] ?? null;
                if ($n === null && !$this->eof) {
                    $before = $this->pos;
                    $this->fill();
                    $i -= $before - $this->pos;
                    continue;
                }
                if ($c === '#' || ($c === '-' && $n === '-')) {
                    $nl = strpos($this->buf, "\n", $i);
                    if ($nl === false) {
                        $i = $len;
                        continue;
                    }
                    $i = $nl + 1;
                    $this->pos = $i;
                    continue;
                }
                if ($c === '/' && $n === '*' && ($this->buf[$i + 2] ?? '') !== '!') {
                    $end = strpos($this->buf, '*/', $i + 2);
                    if ($end === false) {
                        $i = max($i, $len - 1);
                        continue;
                    }
                    $i = $end + 2;
                    $this->pos = $i;
                    continue;
                }
                $start = $i;
            }

            // keep a few bytes of lookahead so two-character tokens are never split by a refill
            if ($len - $i < 3 && !$this->eof) {
                $before = $this->pos;
                if ($this->fill()) {
                    $d = $before - $this->pos;
                    $i -= $d;
                    $start -= $d;
                    continue;
                }
            }

            switch ($state) {
                case 0:
                    $i += strcspn($this->buf, ";'\"`/#-", $i);
                    if ($i >= $len) {
                        break;
                    }
                    $c = $this->buf[$i];
                    if ($c === ';') {
                        $sql = substr($this->buf, $start, $i - $start);
                        $this->pos = $i + 1;

                        return $sql;
                    }
                    if ($c === "'") {
                        $state = 1;
                        $i++;
                    } elseif ($c === '"') {
                        $state = 2;
                        $i++;
                    } elseif ($c === '`') {
                        $state = 3;
                        $i++;
                    } elseif ($c === '/') {
                        if (($this->buf[$i + 1] ?? '') === '*') {
                            $state = 5;
                            $i += 2;
                        } else {
                            $i++;
                        }
                    } elseif ($c === '#') {
                        $state = 4;
                        $i++;
                    } else { // '-'
                        if (($this->buf[$i + 1] ?? '') === '-' && in_array($this->buf[$i + 2] ?? ' ', [' ', "\t", "\r", "\n"], true)) {
                            $state = 4;
                            $i += 2;
                        } else {
                            $i++;
                        }
                    }
                    break;

                case 1:
                case 2:
                    $quote = $state === 1 ? "'" : '"';
                    $i += strcspn($this->buf, $quote . '\\', $i);
                    if ($i >= $len) {
                        break;
                    }
                    if ($this->buf[$i] === '\\') {
                        $i += 2;
                        break;
                    }
                    if ($i + 1 >= $len && !$this->eof) {
                        $before = $this->pos;
                        $this->fill();
                        $d = $before - $this->pos;
                        $i -= $d;
                        $start -= $d;
                        $len = strlen($this->buf);
                    }
                    if (($this->buf[$i + 1] ?? '') === $quote) {
                        $i += 2;
                    } else {
                        $i++;
                        $state = 0;
                    }
                    break;

                case 3:
                    $i += strcspn($this->buf, '`', $i);
                    if ($i < $len) {
                        $i++;
                        $state = 0;
                    }
                    break;

                case 4:
                    $nl = strpos($this->buf, "\n", $i);
                    if ($nl === false) {
                        $i = $len;
                    } else {
                        $i = $nl + 1;
                        $state = 0;
                    }
                    break;

                case 5:
                    $end = strpos($this->buf, '*/', $i);
                    if ($end === false) {
                        $i = max($i, $len - 1);
                    } else {
                        $i = $end + 2;
                        $state = 0;
                    }
                    break;
            }
        }
    }
}
