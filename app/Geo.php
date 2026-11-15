<?php
declare(strict_types=1);

/**
 * Reader for MaxMind DB (.mmdb) files such as GeoLite2 City and Country. Pure PHP, reads only the
 * parts of the file it needs, so a 50 MB database costs almost no memory. Every read is bounds checked
 * because the file is downloaded from the internet.
 */
final class MmdbReader
{
    private const MARKER = "\xAB\xCD\xEFMaxMind.com";
    private const MAX_DEPTH = 16;

    /** @var resource */
    private $fh;
    private int $size = 0;
    private array $meta = [];
    private int $nodes = 0;
    private int $recordBits = 0;
    private int $nodeBytes = 0;
    private int $dataStart = 0;
    private int $v4Start = 0;
    private bool $v6 = false;

    public static function open(string $path): ?self
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return null;
        }
        $r = new self();
        $r->fh = $fh;
        try {
            $r->init();
        } catch (Throwable) {
            fclose($fh);
            return null;
        }
        return $r;
    }

    public function __destruct()
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
    }

    public function meta(): array
    {
        return $this->meta;
    }

    private function read(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 0 || $offset + $length > $this->size) {
            throw new RuntimeException('read outside the file');
        }
        if ($length === 0) {
            return '';
        }
        fseek($this->fh, $offset);
        $s = (string)fread($this->fh, $length);
        if (strlen($s) !== $length) {
            throw new RuntimeException('short read');
        }
        return $s;
    }

    private static function be(string $b): int
    {
        $n = 0;
        for ($i = 0, $l = strlen($b); $i < $l; $i++) {
            $n = ($n << 8) | ord($b[$i]);
        }
        return $n;
    }

    private function init(): void
    {
        fseek($this->fh, 0, SEEK_END);
        $this->size = (int)ftell($this->fh);
        $tailLen = min($this->size, 131072);
        $tail = $this->read($this->size - $tailLen, $tailLen);
        $pos = strrpos($tail, self::MARKER);
        if ($pos === false) {
            throw new RuntimeException('not a MaxMind database');
        }
        $metaStart = $this->size - $tailLen + $pos + strlen(self::MARKER);
        $meta = $this->decode($metaStart, $metaStart, 0)[0];
        if (!is_array($meta)) {
            throw new RuntimeException('bad metadata');
        }
        $this->meta = $meta;
        $this->nodes = (int)($meta['node_count'] ?? 0);
        $this->recordBits = (int)($meta['record_size'] ?? 0);
        $this->v6 = (int)($meta['ip_version'] ?? 4) === 6;
        if ($this->nodes < 1 || !in_array($this->recordBits, [24, 28, 32], true)) {
            throw new RuntimeException('unsupported database');
        }
        $this->nodeBytes = intdiv($this->recordBits * 2, 8);
        $this->dataStart = $this->nodeBytes * $this->nodes + 16;
        if ($this->dataStart > $this->size) {
            throw new RuntimeException('truncated database');
        }
        if ($this->v6) {
            $n = 0;
            for ($i = 0; $i < 96 && $n < $this->nodes; $i++) {
                $n = $this->node($n, 0);
            }
            $this->v4Start = $n;
        }
    }

    private function node(int $n, int $bit): int
    {
        $b = $this->read($n * $this->nodeBytes, $this->nodeBytes);
        if ($this->recordBits === 24) {
            return self::be(substr($b, $bit * 3, 3));
        }
        if ($this->recordBits === 28) {
            return $bit === 0
                ? ((ord($b[3]) & 0xF0) << 20) | self::be(substr($b, 0, 3))
                : ((ord($b[3]) & 0x0F) << 24) | self::be(substr($b, 4, 3));
        }
        return self::be(substr($b, $bit * 4, 4));
    }

    /** @return array|null the record for an IP address, or null when it is not in the database */
    public function lookup(string $ip): ?array
    {
        $raw = @inet_pton($ip);
        if ($raw === false) {
            return null;
        }
        if (strlen($raw) === 4) {
            $n = $this->v6 ? $this->v4Start : 0;
        } else {
            if (!$this->v6) {
                return null;
            }
            $n = 0;
        }
        for ($i = 0, $bits = strlen($raw) * 8; $i < $bits && $n < $this->nodes; $i++) {
            $n = $this->node($n, (ord($raw[$i >> 3]) >> (7 - ($i & 7))) & 1);
        }
        if ($n <= $this->nodes) {
            return null;
        }
        $offset = $n - $this->nodes - 16 + $this->dataStart;
        $v = $this->decode($offset, $this->dataStart, 0)[0];
        return is_array($v) ? $v : null;
    }

    /** @return array{0:mixed,1:int} value and the offset after it */
    private function decode(int $off, int $base, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('too deep');
        }
        $ctrl = ord($this->read($off, 1));
        $off++;
        $type = $ctrl >> 5;
        if ($type === 1) {
            $size = ($ctrl >> 3) & 3;
            $vv = $ctrl & 7;
            if ($size === 0) {
                $p = ($vv << 8) | ord($this->read($off, 1));
                $off += 1;
            } elseif ($size === 1) {
                $p = (($vv << 16) | self::be($this->read($off, 2))) + 2048;
                $off += 2;
            } elseif ($size === 2) {
                $p = (($vv << 24) | self::be($this->read($off, 3))) + 526336;
                $off += 3;
            } else {
                $p = self::be($this->read($off, 4));
                $off += 4;
            }
            return [$this->decode($base + $p, $base, $depth + 1)[0], $off];
        }
        if ($type === 0) {
            $type = 7 + ord($this->read($off, 1));
            $off++;
        }
        $size = $ctrl & 31;
        if ($size === 29) {
            $size = 29 + ord($this->read($off, 1));
            $off += 1;
        } elseif ($size === 30) {
            $size = 285 + self::be($this->read($off, 2));
            $off += 2;
        } elseif ($size === 31) {
            $size = 65821 + self::be($this->read($off, 3));
            $off += 3;
        }
        switch ($type) {
            case 2:
                return [$this->read($off, $size), $off + $size];
            case 3:
                return [unpack('E', $this->read($off, 8))[1], $off + 8];
            case 4:
                return [null, $off + $size];
            case 5:
            case 6:
            case 8:
            case 9:
            case 10:
                return [$size <= 8 ? self::be($this->read($off, $size)) : null, $off + $size];
            case 7:
                if ($size > 2000) {
                    throw new RuntimeException('map too large');
                }
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    [$k, $off] = $this->decode($off, $base, $depth + 1);
                    [$v, $off] = $this->decode($off, $base, $depth + 1);
                    $map[(string)$k] = $v;
                }
                return [$map, $off];
            case 11:
                if ($size > 2000) {
                    throw new RuntimeException('array too large');
                }
                $arr = [];
                for ($i = 0; $i < $size; $i++) {
                    [$v, $off] = $this->decode($off, $base, $depth + 1);
                    $arr[] = $v;
                }
                return [$arr, $off];
            case 14:
                return [$size !== 0, $off];
            case 15:
                return [unpack('G', $this->read($off, 4))[1], $off + 4];
        }
        throw new RuntimeException('unknown data type');
    }
}

/**
 * Where visitors are: looks up the country and city of a visit in a GeoLite2 database stored in storage/geo.
 * The IP address is used for the lookup only and is never stored. The database can be downloaded and
 * kept up to date from the admin area.
 */
final class Geo
{
    private const SOURCE = 'P3TERX/GeoLite.mmdb';
    private const CHECK_EVERY = 604800;
    private static ?MmdbReader $reader = null;
    private static bool $tried = false;

    public static function dir(): string
    {
        return RP_STORAGE . '/geo';
    }

    public static function edition(): string
    {
        return Settings::get('geo_edition', 'city') === 'country' ? 'Country' : 'City';
    }

    public static function file(): string
    {
        return self::dir() . '/GeoLite2-' . self::edition() . '.mmdb';
    }

    public static function installed(): bool
    {
        return is_file(self::file());
    }

    /** Location lookups are on, and a database is there. */
    public static function enabled(): bool
    {
        return Settings::get('geo_on', '0') === '1' && self::installed();
    }

    /** @return array{0:string,1:string,2:string} country code, country name, city; empty strings when unknown */
    public static function lookup(string $ip): array
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['', '', ''];
        }
        if (!self::$tried) {
            self::$tried = true;
            self::$reader = self::installed() ? MmdbReader::open(self::file()) : null;
        }
        try {
            return self::fromRecord(self::$reader?->lookup($ip));
        } catch (Throwable) {
            return ['', '', ''];
        }
    }

    /** @return array{0:string,1:string,2:string} */
    public static function fromRecord(?array $rec): array
    {
        if (!$rec) {
            return ['', '', ''];
        }
        $cc = strtoupper((string)($rec['country']['iso_code'] ?? ''));
        if (!preg_match('/^[A-Z]{2}$/', $cc)) {
            return ['', '', ''];
        }
        $country = mb_substr((string)($rec['country']['names']['en'] ?? ''), 0, 60);
        $city = mb_substr((string)($rec['city']['names']['en'] ?? ''), 0, 80);
        return [$cc, $country, $city];
    }

    /** @return array{installed:bool,size:int,date:string,version:string,checked:int,edition:string} */
    public static function status(): array
    {
        $f = self::file();
        return [
            'installed' => is_file($f), 'size' => is_file($f) ? (int)filesize($f) : 0,
            'date' => is_file($f) ? date('Y-m-d', (int)filemtime($f)) : '',
            'version' => (string)Settings::get('geo_version', ''), 'checked' => (int)Settings::get('geo_checked', '0'),
            'edition' => self::edition(),
        ];
    }

    /** True when automatic updating is on and the weekly check is due. */
    public static function due(): bool
    {
        return Settings::get('geo_on', '0') === '1' && Settings::get('geo_auto', '1') === '1' && self::installed()
            && time() - (int)Settings::get('geo_checked', '0') >= self::CHECK_EVERY;
    }

    /**
     * Download the newest database. Nothing happens when the installed one is already the newest, unless $force.
     * @return array{ok:bool,message:string}
     */
    public static function update(bool $force): array
    {
        @mkdir(self::dir(), 0755, true);
        if (!is_dir(self::dir()) || !is_writable(self::dir())) {
            return ['ok' => false, 'message' => 'The folder storage/geo can not be written.'];
        }
        $lock = @fopen(self::dir() . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'message' => 'An update is already running.'];
        }
        try {
            @set_time_limit(300);
            Settings::set('geo_checked', (string)time());
            $res = Updater::http('https://api.github.com/repos/' . self::SOURCE . '/releases/latest');
            if (!$res['ok']) {
                return ['ok' => false, 'message' => 'Could not reach GitHub: ' . $res['error']];
            }
            $rel = json_decode($res['body'], true);
            $tag = is_array($rel) ? (string)($rel['tag_name'] ?? '') : '';
            $name = 'GeoLite2-' . self::edition() . '.mmdb';
            $url = '';
            foreach ((array)($rel['assets'] ?? []) as $a) {
                if (($a['name'] ?? '') === $name) {
                    $url = (string)($a['browser_download_url'] ?? '');
                }
            }
            if ($tag === '' || $url === '' || !str_starts_with($url, 'https://github.com/' . self::SOURCE . '/')) {
                return ['ok' => false, 'message' => 'The newest release has no ' . $name . ' file.'];
            }
            if (!$force && self::installed() && Settings::get('geo_version', '') === $tag) {
                return ['ok' => true, 'message' => 'The location data is already up to date (' . $tag . ').'];
            }
            $part = self::file() . '.part';
            $dl = Updater::http($url, $part, 280);
            if (!$dl['ok'] || !is_file($part) || filesize($part) < 1000000) {
                @unlink($part);
                return ['ok' => false, 'message' => 'The download failed: ' . ($dl['error'] ?: 'file too small') . '.'];
            }
            $r = MmdbReader::open($part);
            $type = $r ? (string)($r->meta()['database_type'] ?? '') : '';
            $test = $r ? $r->lookup('8.8.8.8') : null;
            $r = null;
            if ($type === '' || !str_contains($type, self::edition()) || !is_array($test)) {
                @unlink($part);
                return ['ok' => false, 'message' => 'The downloaded file is not a usable ' . self::edition() . ' database, so it was not installed.'];
            }
            if (!@rename($part, self::file())) {
                @unlink($part);
                return ['ok' => false, 'message' => 'The database could not be saved in storage/geo.'];
            }
            Settings::set('geo_version', $tag);
            self::$tried = false;
            self::$reader = null;
            return ['ok' => true, 'message' => 'Location data installed (' . $tag . ', ' . round((int)filesize(self::file()) / 1048576, 1) . ' MB).'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function remove(): void
    {
        foreach (glob(self::dir() . '/*.mmdb*') ?: [] as $f) {
            @unlink($f);
        }
        Settings::setMany(['geo_version' => '', 'geo_checked' => '0']);
    }
}
