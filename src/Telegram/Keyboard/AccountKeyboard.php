<?php

namespace Faoxima\Telegram\Keyboard;

use Throwable;

final class AccountKeyboard
{
    public static function label(string $id, array $registry, $datatextbot, $textbotlang): string
    {
        if ($id === 'backuser' && !empty($textbotlang['users']['backbtn'])) {
            return (string) $textbotlang['users']['backbtn'];
        }
        $key = $registry[$id]['textbot'] ?? null;
        if ($key !== null && isset($datatextbot[$key]) && trim((string) $datatextbot[$key]) !== '') {
            return (string) $datatextbot[$key];
        }
        return (string) ($registry[$id]['label'] ?? $id);
    }

    public static function rows($userId, array $registry, array $layout, array $styles, callable $conditionMet, callable $label, callable $style): array
    {
        $rows = [];
        foreach ($layout as $layoutRow) {
            $row = [];
            foreach ($layoutRow as $id) {
                if (!isset($registry[$id])) {
                    continue;
                }
                try {
                    if (!$conditionMet($registry[$id]['condition'], $userId)) {
                        continue;
                    }
                } catch (Throwable $e) {
                    continue;
                }
                $row[] = $style(['text' => $label($id), 'callback_data' => $id], $id, $styles);
            }
            if ($row) {
                $rows[] = $row;
            }
        }
        $hasBack = false;
        foreach ($rows as $row) {
            foreach ($row as $btn) {
                if (($btn['callback_data'] ?? '') === 'backuser') {
                    $hasBack = true;
                }
            }
        }
        if (!$hasBack) {
            $rows[] = [$style(['text' => $label('backuser'), 'callback_data' => 'backuser'], 'backuser', $styles)];
        }
        return $rows;
    }

    public static function json(array $rows): string
    {
        return json_encode([
            'inline_keyboard' => $rows,
            'resize_keyboard' => true,
        ], JSON_UNESCAPED_UNICODE);
    }
}
