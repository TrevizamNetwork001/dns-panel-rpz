<?php

namespace App\Services;

use PDO;

/** Disk-backed suffix traversal: bounded PHP memory even for million-rule feeds. */
class RpzPolicyCompiler
{
    private ?PDO $db;
    private string $path;
    private \PDOStatement $insert;

    public function __construct()
    {
        $path = tempnam(sys_get_temp_dir(), 'rpz-policy-');
        if ($path === false) { throw new \RuntimeException('Falha ao criar compilador temporário RPZ.'); }
        $this->path = $path;
        $this->db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF; PRAGMA temp_store=FILE;
            CREATE TABLE nodes (domain TEXT PRIMARY KEY, sort_key TEXT NOT NULL, wildcard INTEGER NOT NULL, kind INTEGER NOT NULL, source INTEGER NOT NULL) WITHOUT ROWID;
            CREATE TABLE guards (domain TEXT PRIMARY KEY, exact INTEGER NOT NULL, wildcard INTEGER NOT NULL) WITHOUT ROWID;');
        $this->db->beginTransaction();
        $this->insert = $this->db->prepare('INSERT OR IGNORE INTO nodes VALUES (?, ?, ?, ?, ?)');
    }

    public function add(string $domain, bool $wildcard): void
    {
        $this->insert->execute([$domain, $this->sortKey($domain), (int) $wildcard, 0, 1]);
    }

    /** Exact/wildcard block guards needed to keep inherited blocks continuous. */
    public function guards(array $exceptions, string $host): \Generator
    {
        foreach ($exceptions as $exception) {
            $this->insert->execute([$exception, $this->sortKey($exception), 1, 1, 0]);
        }
        $canary = 'blocktest.'.$host;
        $this->insert->execute([$canary, $this->sortKey($canary), 0, 2, 0]);
        $this->db->prepare('UPDATE nodes SET kind=2 WHERE domain=?')->execute([$canary]);
        $this->db->commit();
        $this->db->exec('CREATE INDEX nodes_suffix_order ON nodes(sort_key COLLATE BINARY)');
        $this->db->beginTransaction();
        $guard = $this->db->prepare('INSERT OR IGNORE INTO guards VALUES (?, ?, 1)');
        $stack = [];
        foreach ($this->db->query('SELECT domain, wildcard, kind, source FROM nodes ORDER BY sort_key COLLATE BINARY') as $row) {
            $domain = $row['domain'];
            while ($stack && ! str_ends_with($domain, '.'.$stack[array_key_last($stack)])) {
                array_pop($stack);
            }
            $inherited = $stack !== [];
            if ($inherited) {
                $ancestor = $stack[array_key_last($stack)];
                $parents = [];
                $parent = substr($domain, strpos($domain, '.') + 1);
                while ($parent !== $ancestor) {
                    $parents[] = $parent;
                    $parent = substr($parent, strpos($parent, '.') + 1);
                }
                foreach (array_reverse($parents) as $parent) {
                    $guard->execute([$parent, 1]);
                    $stack[] = $parent;
                }
            }
            if ((int) $row['kind'] === 1) { continue; }
            if ($inherited && ! $row['wildcard']) {
                // WPAD/canary stay exact-only unless a source parent already
                // blocks their whole subtree. Do not exempt them accidentally.
                $guard->execute([$domain, 0]);
            }
            if ($row['wildcard'] || $inherited) { $stack[] = $domain; }
        }
        $this->db->commit();
        foreach ($this->db->query('SELECT domain, exact, wildcard FROM guards ORDER BY domain COLLATE BINARY') as $row) {
            if ($row['exact']) { yield $row['domain']; }
            if ($row['wildcard'] && strlen('*.'.$row['domain'].'.'.rtrim($host, '.')) <= 253) { yield '*.'.$row['domain']; }
        }
    }

    private function sortKey(string $domain): string
    {
        // ! sorts before every valid label character, including hyphen.
        return implode('!', array_reverse(explode('.', $domain)));
    }

    public function __destruct()
    {
        unset($this->insert);
        $this->db = null;
        if (isset($this->path)) { @unlink($this->path); }
    }
}
