<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class TextRequestHelpers
{
    public static function liveOverlay(PDO $pdo, string $jsonFile): array
    {
        $decoded = is_file($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : null;
        $base = is_array($decoded['fa'] ?? null) ? $decoded['fa'] : [];

        try {
            $stmt = $pdo->query('SELECT id_text, text FROM textbot');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = (string) ($row['id_text'] ?? '');
                    if (strpos($id, 'jsontext.') !== 0) continue;
                    $path = substr($id, strlen('jsontext.'));
                    if ($path === '') continue;
                    $segments = explode('.', $path);
                    $cursor = &$base;
                    foreach ($segments as $i => $seg) {
                        if ($seg === '') continue 2;
                        if ($i === count($segments) - 1) {
                            $cursor[$seg] = (string) ($row['text'] ?? '');
                        } else {
                            if (!isset($cursor[$seg]) || !is_array($cursor[$seg])) $cursor[$seg] = [];
                            $cursor = &$cursor[$seg];
                        }
                    }
                    unset($cursor);
                }
            }
        } catch (\Throwable $e) {
        }
        return $base;
    }
}
