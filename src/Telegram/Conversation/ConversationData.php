<?php

namespace Faoxima\Telegram\Conversation;

final class ConversationData
{
    public static function savedata($type, $namefiled, $valuefiled)
    {
        global $from_id, $user;
        self::rx_write_conversation_data($from_id, $user, $type, $namefiled, $valuefiled);
    }

    public static function rx_write_conversation_data($from_id, &$user, $type, $namefiled, $valuefiled)
    {
        if ($type == "clear") {
            $datauser = [];
            $datauser[$namefiled] = $valuefiled;
            $data = json_encode($datauser, JSON_UNESCAPED_UNICODE);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $data, "id", $from_id);
            if (is_array($user)) {
                $user['Processing_value'] = $data;
            }
        } elseif ($type == "save") {
            $userdata = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select");
            $raw = $userdata['Processing_value'] ?? null;

            $dataperevieos = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
            if (!is_array($dataperevieos)) {
                $dataperevieos = [];
            }
            $dataperevieos[$namefiled] = $valuefiled;
            $data = json_encode($dataperevieos, JSON_UNESCAPED_UNICODE);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $data, "id", $from_id);
            if (is_array($user)) {
                $user['Processing_value'] = $data;
            }
        }
    }

    public static function languagechange($path_dir)
    {

        $rx_candidates = [];
        if (is_string($path_dir) && $path_dir !== '') {
            $rx_candidates[] = $path_dir;
        }
        if (defined('REFACTORED_LEGACY_ROOT')) {
            $rx_candidates[] = REFACTORED_LEGACY_ROOT . DIRECTORY_SEPARATOR . 'text.json';
        }
        $rx_candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'text.json';
        $rx_candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'text.json';

        $rx_source = null;
        foreach ($rx_candidates as $rx_candidate) {
            if (!is_string($rx_candidate) || $rx_candidate === '') continue;
            if (!@file_exists($rx_candidate)) continue;
            if (@is_readable($rx_candidate)) {
                $rx_source = $rx_candidate;
                break;
            }
        }
        if ($rx_source === null) {
            return [];
        }

        $rx_decoded = null;
        $rx_cache_key = 'faoxima:language:' . md5((string) @realpath($rx_source) . '|' . (int) @filemtime($rx_source) . '|' . (int) @filesize($rx_source));
        if (function_exists('apcu_fetch') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $rx_cached = apcu_fetch($rx_cache_key, $rx_cache_hit);
            if ($rx_cache_hit && is_array($rx_cached)) {
                $rx_decoded = $rx_cached;
            }
        }
        if (!is_array($rx_decoded)) {
            $rx_raw = @file_get_contents($rx_source);
            if ($rx_raw === false || $rx_raw === '') {
                return [];
            }
            $rx_decoded = json_decode($rx_raw, true);
            if (is_array($rx_decoded) && function_exists('apcu_store') && filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
                @apcu_store($rx_cache_key, $rx_decoded, 3600);
            }
        }
        if (!is_array($rx_decoded)) {
            return [];
        }

        $rx_setting = isset($GLOBALS['setting']) && is_array($GLOBALS['setting']) ? $GLOBALS['setting'] : null;
        if ($rx_setting === null && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $rx_setting = \Faoxima\Core\Database\Db::select("setting", "*");
            } catch (\Throwable $rx_setting_err) {
                $rx_setting = null;
            }
        }
        $rx_lang_key = 'fa';
        if (is_array($rx_setting)) {
            if (isset($rx_setting['languageen']) && intval($rx_setting['languageen']) === 1) {
                $rx_lang_key = 'en';
            } elseif (isset($rx_setting['languageru']) && intval($rx_setting['languageru']) === 1) {
                $rx_lang_key = 'ru';
            }
        }

        $rx_base = null;
        if (isset($rx_decoded[$rx_lang_key]) && is_array($rx_decoded[$rx_lang_key])) {
            $rx_base = $rx_decoded[$rx_lang_key];
        } elseif (isset($rx_decoded['fa']) && is_array($rx_decoded['fa'])) {
            $rx_base = $rx_decoded['fa'];
            $rx_lang_key = 'fa';
        }
        if ($rx_base === null) {
            return [];
        }

        if ($rx_lang_key === 'fa' && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $rx_overlay_rows = \Faoxima\Core\Database\Db::select('textbot', '*', null, null, 'fetchAll');
                if (is_array($rx_overlay_rows)) {
                    $GLOBALS['_rx_textbot_rows'] = $rx_overlay_rows;
                    foreach ($rx_overlay_rows as $rx_overlay_row) {
                        $rx_overlay_id = (string) ($rx_overlay_row['id_text'] ?? '');
                        if (strpos($rx_overlay_id, 'jsontext.') !== 0) {
                            continue;
                        }
                        $rx_overlay_path = substr($rx_overlay_id, strlen('jsontext.'));
                        if ($rx_overlay_path === '') {
                            continue;
                        }
                        $rx_overlay_segments = explode('.', $rx_overlay_path);
                        $rx_overlay_cursor = &$rx_base;
                        foreach ($rx_overlay_segments as $rx_overlay_i => $rx_overlay_seg) {
                            if ($rx_overlay_seg === '') {
                                continue 2;
                            }
                            if ($rx_overlay_i === count($rx_overlay_segments) - 1) {
                                $rx_overlay_cursor[$rx_overlay_seg] = (string) ($rx_overlay_row['text'] ?? '');
                            } else {
                                if (!isset($rx_overlay_cursor[$rx_overlay_seg]) || !is_array($rx_overlay_cursor[$rx_overlay_seg])) {
                                    $rx_overlay_cursor[$rx_overlay_seg] = [];
                                }
                                $rx_overlay_cursor = &$rx_overlay_cursor[$rx_overlay_seg];
                            }
                        }
                        unset($rx_overlay_cursor);
                    }
                }
            } catch (\Throwable $rx_overlay_err) {
            }
        }

        return $rx_base;
    }
}
