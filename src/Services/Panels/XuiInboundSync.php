<?php

namespace Faoxima\Services\Panels;

use PDO;

final class XuiInboundSync
{
    public static function &xsync_ctx()
    {
        static $ctx = array('deadline' => null);
        return $ctx;
    }

    public static function xsync_pdo()
    {
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $pdo = \Faoxima\Core\Database\Db::getDatabaseConnection();
            if ($pdo instanceof PDO) {
                return $pdo;
            }
        }
        return ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null;
    }

    public static function xsync_reset_run()
    {
        $ctx = &self::xsync_ctx();
        $ctx = array('deadline' => null);
    }

    public static function xsync_sanitize($text, $max = 240)
    {
        $text = (string) $text;
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', $text);
        $text = preg_replace('#https?://[^\s"\'<>]+#i', '[url]', (string) $text);
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', (string) $text);
        $text = preg_replace('/(token|password|secret|cookie|authorization|apikey|api_key)(["\']?\s*[=:]\s*["\']?)[^\s"\',;&]+/i', '$1$2[redacted]', (string) $text);
        $text = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '[uuid]', (string) $text);
        $text = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)+/', '[email]', (string) $text);
        $text = preg_replace('/[A-Za-z0-9+\/_=\-]{24,}/', '[long-token]', (string) $text);
        $text = trim((string) $text);
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }

    public static function xsync_ids($ids)
    {
        $out = array();
        foreach (is_array($ids) ? $ids : array() as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $out[(int) $id] = (int) $id;
            }
        }
        $out = array_values($out);
        sort($out);
        return $out;
    }

    public static function xsync_decode_ids($json)
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? self::xsync_ids($decoded) : array();
    }

    public static function xsync_ensure_tables($pdo)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS xui_sync_panel (
        panel_name VARCHAR(191) NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        reference_email VARCHAR(300) NULL,
        baseline_ids TEXT NULL,
        ref_ids TEXT NULL,
        ref_version INT UNSIGNED NOT NULL DEFAULT 0,
        ref_validated_at BIGINT NOT NULL DEFAULT 0,
        pending_empty_at BIGINT NULL,
        last_check_at BIGINT NOT NULL DEFAULT 0,
        last_status VARCHAR(80) NULL,
        last_error VARCHAR(500) NULL,
        migration_rev INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at BIGINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $hasRev = $pdo->query("SHOW COLUMNS FROM xui_sync_panel LIKE 'migration_rev'")->fetch(PDO::FETCH_ASSOC);
        if (!$hasRev) {
            $pdo->exec('ALTER TABLE xui_sync_panel ADD COLUMN migration_rev INT UNSIGNED NOT NULL DEFAULT 0');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS xui_sync_service (
        svc_key CHAR(40) NOT NULL PRIMARY KEY,
        panel_name VARCHAR(191) NOT NULL,
        email VARCHAR(300) NOT NULL,
        id_invoice VARCHAR(200) NULL,
        state VARCHAR(20) NOT NULL DEFAULT 'unclassified',
        managed_ids TEXT NULL,
        synced_version INT UNSIGNED NOT NULL DEFAULT 0,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at BIGINT NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL,
        ownership_source VARCHAR(40) NULL,
        review_reason VARCHAR(255) NULL,
        updated_at BIGINT NOT NULL DEFAULT 0,
        KEY idx_xsync_queue (panel_name, state, next_attempt_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
    }

    public static function xsync_svc_key($panelName, $email)
    {
        return sha1((string) $panelName . '|' . (string) $email);
    }

    public static function xsync_panel_state($pdo, $panelName)
    {
        $stmt = $pdo->prepare('SELECT * FROM xui_sync_panel WHERE panel_name = ?');
        $stmt->execute(array($panelName));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function xsync_is_enabled($panelName)
    {
        $pdo = self::xsync_pdo();
        if (!$pdo) {
            return false;
        }
        try {
            self::xsync_ensure_tables($pdo);
            $row = self::xsync_panel_state($pdo, $panelName);
            return is_array($row) && (int) $row['enabled'] === 1;
        } catch (\Throwable $e) {
            error_log('[xui-inbound-sync] is_enabled failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function xsync_enable_panel($panelName, $referenceEmail, $referenceIds, $previousDefaultRaw)
    {
        $pdo = self::xsync_pdo();
        if (!$pdo) {
            return false;
        }
        self::xsync_ensure_tables($pdo);
        $ids = self::xsync_ids($referenceIds);
        $existing = self::xsync_panel_state($pdo, $panelName);
        $now = time();
        if (!is_array($existing)) {
            $baseline = self::xsync_ids(\Faoxima\Services\Panels\Drivers\XuiSingle::xui_parse_inbound_ids($previousDefaultRaw));
            $stmt = $pdo->prepare('INSERT INTO xui_sync_panel (panel_name, enabled, reference_email, baseline_ids, ref_ids, ref_version, ref_validated_at, last_status, updated_at) VALUES (?, 1, ?, ?, ?, 1, ?, ?, ?)');
            $stmt->execute(array($panelName, $referenceEmail, json_encode($baseline), json_encode($ids), $now, 'enabled', $now));
            return true;
        }
        $version = (int) $existing['ref_version'];
        $currentIds = self::xsync_decode_ids($existing['ref_ids']);
        $emailChanged = (string) $existing['reference_email'] !== (string) $referenceEmail;
        $idsChanged = ($currentIds !== $ids) || (int) $existing['enabled'] !== 1 || $emailChanged;
        if ($idsChanged) {
            $version++;
        }
        $stmt = $pdo->prepare('UPDATE xui_sync_panel SET enabled = 1, reference_email = ?, ref_ids = ?, ref_version = ?, ref_validated_at = ?, pending_empty_at = NULL, last_status = ?, last_error = NULL, updated_at = ? WHERE panel_name = ?');
        $stmt->execute(array($referenceEmail, json_encode($ids), $version, $now, 're-enabled', $now, $panelName));
        return true;
    }

    public static function xsync_record_creation($panelName, $email, $ids, $invoiceId = null)
    {
        $pdo = self::xsync_pdo();
        if (!$pdo) {
            return false;
        }
        try {
            self::xsync_ensure_tables($pdo);
            $state = self::xsync_panel_state($pdo, $panelName);
            if (!is_array($state) || (int) $state['enabled'] !== 1) {
                return false;
            }
            $ids = self::xsync_ids($ids);
            $current = self::xsync_decode_ids($state['ref_ids']);
            $version = ($ids === $current) ? (int) $state['ref_version'] : 0;
            $stmt = $pdo->prepare("INSERT INTO xui_sync_service (svc_key, panel_name, email, id_invoice, state, managed_ids, synced_version, ownership_source, updated_at) VALUES (?, ?, ?, ?, 'pending', ?, ?, 'creation_record', ?) ON DUPLICATE KEY UPDATE managed_ids = VALUES(managed_ids), synced_version = VALUES(synced_version), state = 'pending', ownership_source = 'creation_record', review_reason = NULL, updated_at = VALUES(updated_at)");
            $stmt->execute(array(self::xsync_svc_key($panelName, $email), $panelName, $email, $invoiceId, json_encode($ids), $version, time()));
            return true;
        } catch (\Throwable $e) {
            error_log('[xui-inbound-sync] record_creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function xsync_error_kind_from_response($resp)
    {
        $status = is_array($resp) ? (int) ($resp['status'] ?? 0) : 0;
        if ($status === 401 || $status === 403) {
            return 'auth';
        }
        if ($status === 0) {
            return 'transport';
        }
        if ($status >= 500) {
            return 'http_5xx';
        }
        if ($status < 200 || $status >= 300) {
            return 'http_' . $status;
        }
        return null;
    }

    public static function xsync_fetch_client($panel, $email)
    {
        $started = microtime(true);
        $resp = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_api_token_request($panel, 'GET', '/panel/api/clients/get/' . rawurlencode($email), null, XSYNC_API_TIMEOUT, 'xsync_fetch_client', $email);
        $result = array('ok' => false, 'kind' => null, 'ids' => null, 'status' => is_array($resp) ? (int) ($resp['status'] ?? 0) : 0, 'ms' => (int) round((microtime(true) - $started) * 1000), 'msg' => null);
        $kind = self::xsync_error_kind_from_response($resp);
        if ($kind !== null) {
            $result['kind'] = $kind;
            $result['msg'] = !empty($resp['error']) ? self::xsync_sanitize($resp['error'], 160) : null;
            return $result;
        }
        $body = is_string($resp['body'] ?? null) ? $resp['body'] : null;
        $decoded = $body !== null ? json_decode($body, true) : null;
        if (!is_array($decoded)) {
            $result['kind'] = 'malformed';
            return $result;
        }
        if (($decoded['success'] ?? null) !== true) {
            $result['kind'] = 'rejected';
            $result['msg'] = isset($decoded['msg']) ? self::xsync_sanitize($decoded['msg'], 160) : null;
            return $result;
        }
        $obj = $decoded['obj'] ?? null;
        if (!is_array($obj)) {
            $result['kind'] = 'malformed';
            return $result;
        }
        $client = is_array($obj['client'] ?? null) ? $obj['client'] : $obj;
        $returnedEmail = $client['email'] ?? null;
        if (!is_string($returnedEmail) || strcasecmp($returnedEmail, (string) $email) !== 0) {
            $result['kind'] = 'identity_mismatch';
            return $result;
        }
        if (!array_key_exists('inboundIds', $obj) || !is_array($obj['inboundIds'])) {
            $result['kind'] = 'incomplete';
            return $result;
        }
        foreach ($obj['inboundIds'] as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                $result['kind'] = 'malformed';
                return $result;
            }
            if ((int) $id <= 0) {
                $result['kind'] = 'malformed';
                return $result;
            }
        }
        $result['ok'] = true;
        $result['ids'] = self::xsync_ids($obj['inboundIds']);
        return $result;
    }

    public static function xsync_time_left()
    {
        $ctx = &self::xsync_ctx();
        if ($ctx['deadline'] === null) {
            return true;
        }
        if (microtime(true) >= $ctx['deadline']) {
            return false;
        }
        if (method_exists(\Faoxima\Cron\Runtime\CronRuntime::class, 'rx_cron_time_up') && \Faoxima\Cron\Runtime\CronRuntime::rx_cron_time_up()) {
            return false;
        }
        return true;
    }

    public static function xsync_pause($seconds)
    {
        if (XSYNC_SLEEP_ENABLED && $seconds > 0) {
            usleep((int) ($seconds * 1000000));
        }
    }

    public static function xsync_fetch_client_retry($panel, $email)
    {
        $result = null;
        for ($attempt = 1; $attempt <= XSYNC_STEP_RETRIES; $attempt++) {
            $result = self::xsync_fetch_client($panel, $email);
            if ($result['ok']) {
                return $result;
            }
            $retryable = in_array($result['kind'], array('transport', 'http_5xx'), true);
            if (!$retryable || $attempt === XSYNC_STEP_RETRIES || !self::xsync_time_left()) {
                break;
            }
            self::xsync_pause($attempt);
        }
        return $result;
    }

    public static function xsync_mutate($panel, $email, $action, $ids)
    {
        $started = microtime(true);
        $resp = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_api_token_request($panel, 'POST', '/panel/api/clients/' . rawurlencode($email) . '/' . $action, array('inboundIds' => array_values($ids)), XSYNC_API_TIMEOUT, 'xsync_' . $action, $email);
        $body = is_array($resp) && is_string($resp['body'] ?? null) ? json_decode($resp['body'], true) : null;
        return array(
            'status' => is_array($resp) ? (int) ($resp['status'] ?? 0) : 0,
            'success_flag' => is_array($body) ? ($body['success'] ?? null) : null,
            'msg' => (is_array($body) && isset($body['msg'])) ? self::xsync_sanitize($body['msg'], 240) : ((is_array($resp) && !empty($resp['error'])) ? self::xsync_sanitize($resp['error'], 240) : null),
            'ms' => (int) round((microtime(true) - $started) * 1000),
        );
    }

    public static function xsync_update_service($pdo, $svcKey, $fields)
    {
        $fields['updated_at'] = time();
        $sets = array();
        $values = array();
        foreach ($fields as $column => $value) {
            $sets[] = '`' . $column . '` = ?';
            $values[] = $value;
        }
        $values[] = $svcKey;
        $stmt = $pdo->prepare('UPDATE xui_sync_service SET ' . implode(', ', $sets) . ' WHERE svc_key = ?');
        $stmt->execute($values);
    }

    public static function xsync_backoff($attempts)
    {
        return min(XSYNC_BACKOFF_CAP, 60 * (1 << min(6, max(0, (int) $attempts))));
    }

    public static function xsync_fail_service($pdo, $svc, $reason, $extra = array())
    {
        $attempts = (int) $svc['attempts'] + 1;
        $next = time() + self::xsync_backoff($attempts);
        self::xsync_update_service($pdo, $svc['svc_key'], array(
            'state' => 'error',
            'attempts' => $attempts,
            'next_attempt_at' => $next,
            'last_error' => self::xsync_sanitize($reason, 400),
        ));
        return 'failed';
    }

    public static function xsync_current_desired($pdo, $panelName)
    {
        $state = self::xsync_panel_state($pdo, $panelName);
        if (!is_array($state) || (int) $state['enabled'] !== 1 || $state['ref_ids'] === null) {
            return null;
        }
        return array('version' => (int) $state['ref_version'], 'ids' => self::xsync_decode_ids($state['ref_ids']), 'reference_email' => (string) $state['reference_email'], 'baseline' => self::xsync_decode_ids($state['baseline_ids']));
    }

    public static function xsync_product_explicit($pdo, $panelName, $productName)
    {
        if ($productName === null || $productName === '') {
            return array('explicit' => false, 'rows' => 0);
        }
        $stmt = $pdo->prepare('SELECT Location, inbounds FROM product WHERE name_product = ?');
        $stmt->execute(array($productName));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $matched = 0;
        $explicit = false;
        foreach (is_array($rows) ? $rows : array() as $row) {
            $locations = array_map('trim', explode(',', (string) ($row['Location'] ?? '')));
            if (!in_array($panelName, $locations, true) && !in_array('/all', $locations, true)) {
                continue;
            }
            $matched++;
            $raw = $row['inbounds'] ?? null;
            if ($raw !== null && trim((string) $raw) !== '') {
                $explicit = true;
            }
        }
        return array('explicit' => $explicit, 'rows' => $matched);
    }

    public static function xsync_classify_service($pdo, $panel, $svc, $desired)
    {
        $email = $svc['email'];
        $panelName = $svc['panel_name'];
        if (strcasecmp($email, $desired['reference_email']) === 0) {
            self::xsync_update_service($pdo, $svc['svc_key'], array('state' => 'excluded', 'review_reason' => 'reference_client'));
            return 'excluded';
        }
        $product = self::xsync_product_explicit($pdo, $panelName, $svc['product_name'] ?? null);
        if ($product['explicit']) {
            self::xsync_update_service($pdo, $svc['svc_key'], array('state' => 'excluded', 'review_reason' => 'product_explicit_inbounds'));
            return 'excluded';
        }
        $read = self::xsync_fetch_client_retry($panel, $email);
        if (!$read['ok']) {
            return self::xsync_fail_service($pdo, $svc, 'classification_read_' . $read['kind'], array('http_status' => $read['status']));
        }
        $actual = $read['ids'];
        $baseline = $desired['baseline'];
        $managed = self::xsync_ids(array_intersect($actual, $baseline));
        $source = ($actual === $baseline && !empty($actual)) ? 'migration_exact_baseline' : 'migration_baseline_overlap';
        self::xsync_update_service($pdo, $svc['svc_key'], array('state' => 'pending', 'managed_ids' => json_encode($managed), 'ownership_source' => $source, 'review_reason' => null, 'attempts' => 0, 'next_attempt_at' => 0, 'last_error' => null));
        return 'managed';
    }

    public static function xsync_reconcile_service($pdo, $panel, $svc, $desired)
    {
        $email = $svc['email'];
        $panelName = $svc['panel_name'];
        $version = $desired['version'];
        $target = $desired['ids'];
        $managed = self::xsync_decode_ids($svc['managed_ids']);

        $read = self::xsync_fetch_client_retry($panel, $email);
        if (!$read['ok']) {
            return self::xsync_fail_service($pdo, $svc, 'read_' . $read['kind'], array('http_status' => $read['status']));
        }
        $actual = $read['ids'];
        $toAdd = array_values(array_diff($target, $actual));
        $toRemove = array_values(array_intersect(array_diff($managed, $target), $actual));

        $attachedByUs = array();
        $addPending = $toAdd;
        for ($try = 1; !empty($addPending) && $try <= XSYNC_STEP_RETRIES; $try++) {
            $latest = self::xsync_current_desired($pdo, $panelName);
            if ($latest === null || $latest['version'] !== $version) {
                return 'superseded';
            }
            self::xsync_mutate($panel, $email, 'attach', $addPending);
            $verify = self::xsync_fetch_client_retry($panel, $email);
            if (!$verify['ok']) {
                return self::xsync_fail_service($pdo, $svc, 'verify_after_attach_' . $verify['kind'], array('requested' => $addPending));
            }
            $actual = $verify['ids'];
            $done = array_values(array_intersect($addPending, $actual));
            $attachedByUs = array_values(array_unique(array_merge($attachedByUs, $done)));
            $addPending = array_values(array_diff($addPending, $actual));
            if (!empty($addPending) && $try < XSYNC_STEP_RETRIES && self::xsync_time_left()) {
                self::xsync_pause($try);
            } elseif (!empty($addPending)) {
                break;
            }
        }
        $managedNow = self::xsync_ids(array_intersect(array_merge($managed, $attachedByUs), $actual));
        if (!empty($addPending)) {
            self::xsync_update_service($pdo, $svc['svc_key'], array('managed_ids' => json_encode($managedNow)));
            return self::xsync_fail_service($pdo, $svc, 'attach_incomplete', array('failed_adds' => $addPending));
        }
        if (!empty($attachedByUs)) {
            self::xsync_update_service($pdo, $svc['svc_key'], array('managed_ids' => json_encode($managedNow)));
        }

        $latest = self::xsync_current_desired($pdo, $panelName);
        if ($latest === null || $latest['version'] !== $version) {
            return 'superseded';
        }
        $removePending = array_values(array_intersect(array_diff($managedNow, $target), $actual));
        for ($try = 1; !empty($removePending) && $try <= XSYNC_STEP_RETRIES; $try++) {
            self::xsync_mutate($panel, $email, 'detach', $removePending);
            $verify = self::xsync_fetch_client_retry($panel, $email);
            if (!$verify['ok']) {
                return self::xsync_fail_service($pdo, $svc, 'verify_after_detach_' . $verify['kind'], array('requested' => $removePending));
            }
            $actual = $verify['ids'];
            $removePending = array_values(array_intersect($removePending, $actual));
            if (!empty($removePending) && $try < XSYNC_STEP_RETRIES && self::xsync_time_left()) {
                self::xsync_pause($try);
            } elseif (!empty($removePending)) {
                break;
            }
        }
        $managedFinal = self::xsync_ids(array_intersect($managedNow, $actual));
        if (!empty($removePending)) {
            self::xsync_update_service($pdo, $svc['svc_key'], array('managed_ids' => json_encode($managedFinal)));
            return self::xsync_fail_service($pdo, $svc, 'detach_incomplete', array('failed_removals' => $removePending));
        }
        $missing = array_values(array_diff($target, $actual));
        $leftover = array_values(array_intersect(array_diff($managedFinal, $target), $actual));
        if (!empty($missing) || !empty($leftover)) {
            return self::xsync_fail_service($pdo, $svc, 'final_verification_mismatch', array('missing' => $missing, 'leftover' => $leftover));
        }
        self::xsync_update_service($pdo, $svc['svc_key'], array(
            'state' => 'synced',
            'managed_ids' => json_encode($managedFinal),
            'synced_version' => $version,
            'attempts' => 0,
            'next_attempt_at' => 0,
            'last_error' => null,
        ));
        return 'synced';
    }

    public static function xsync_check_reference($pdo, $panel, $state)
    {
        $panelName = $state['panel_name'];
        $email = (string) $state['reference_email'];
        $now = time();
        $read = self::xsync_fetch_client_retry($panel, $email);
        $touch = $pdo->prepare('UPDATE xui_sync_panel SET last_check_at = ?, last_status = ?, last_error = ?, updated_at = ? WHERE panel_name = ?');
        if (!$read['ok']) {
            $touch->execute(array($now, 'reference_unavailable', 'reference_' . $read['kind'], $now, $panelName));
            return array('changed' => false, 'valid' => false);
        }
        $ids = $read['ids'];
        $snapshot = self::xsync_decode_ids($state['ref_ids']);
        $version = (int) $state['ref_version'];
        if ($ids === $snapshot) {
            $stmt = $pdo->prepare('UPDATE xui_sync_panel SET last_check_at = ?, last_status = ?, last_error = NULL, pending_empty_at = NULL, ref_validated_at = ?, updated_at = ? WHERE panel_name = ?');
            $stmt->execute(array($now, 'reference_unchanged', $now, $now, $panelName));
            return array('changed' => false, 'valid' => true);
        }
        if (empty($ids)) {
            $pendingSince = $state['pending_empty_at'] !== null ? (int) $state['pending_empty_at'] : 0;
            if ($pendingSince === 0) {
                $stmt = $pdo->prepare('UPDATE xui_sync_panel SET pending_empty_at = ?, last_check_at = ?, last_status = ?, last_error = NULL, updated_at = ? WHERE panel_name = ?');
                $stmt->execute(array($now, $now, 'empty_reference_awaiting_confirmation', $now, $panelName));
                return array('changed' => false, 'valid' => true);
            }
            if (($now - $pendingSince) < XSYNC_EMPTY_CONFIRM_DELAY) {
                $touch->execute(array($now, 'empty_reference_awaiting_confirmation', null, $now, $panelName));
                return array('changed' => false, 'valid' => true);
            }
        }
        $version++;
        $stmt = $pdo->prepare('UPDATE xui_sync_panel SET ref_ids = ?, ref_version = ?, ref_validated_at = ?, pending_empty_at = NULL, last_check_at = ?, last_status = ?, last_error = NULL, updated_at = ? WHERE panel_name = ?');
        $stmt->execute(array(json_encode($ids), $version, $now, $now, 'reference_changed', $now, $panelName));
        $defaultUpdated = false;
        if (!empty($ids)) {
            $csv = implode(',', $ids);
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                \Faoxima\Core\Database\Db::update('marzban_panel', 'inboundid', $csv, 'name_panel', $panelName);
            } else {
                $upd = $pdo->prepare('UPDATE marzban_panel SET inboundid = ? WHERE name_panel = ?');
                $upd->execute(array($csv, $panelName));
            }
            $defaultUpdated = true;
        }
        return array('changed' => true, 'valid' => true);
    }

    public static function xsync_migrate_services($pdo, $panelName, $state)
    {
        $requeuedReview = 0;
        if ((int) ($state['migration_rev'] ?? 0) < 2) {
            $stmt = $pdo->prepare("UPDATE xui_sync_service SET state = 'unclassified', review_reason = NULL, attempts = 0, next_attempt_at = 0, updated_at = ? WHERE panel_name = ? AND state = 'review'");
            $stmt->execute(array(time(), $panelName));
            $requeuedReview = $stmt->rowCount();
            $mark = $pdo->prepare('UPDATE xui_sync_panel SET migration_rev = 2 WHERE panel_name = ?');
            $mark->execute(array($panelName));
        }
        $stmt = $pdo->prepare("UPDATE xui_sync_service SET state = 'unclassified', review_reason = NULL, updated_at = ? WHERE panel_name = ? AND state = 'excluded' AND review_reason = 'reference_client' AND LOWER(email) <> LOWER(?)");
        $stmt->execute(array(time(), $panelName, (string) $state['reference_email']));
        return $requeuedReview;
    }

    public static function xsync_migrate_disabled_panels($pdo)
    {
        $stmt = $pdo->query("SELECT * FROM xui_sync_panel WHERE enabled = 0 AND reference_email IS NOT NULL AND reference_email <> '' AND last_check_at < " . (time() - 600));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach (is_array($rows) ? $rows : array() as $row) {
            $panelName = $row['panel_name'];
            $panel = self::xsync_load_panel($panelName);
            $now = time();
            $touch = $pdo->prepare('UPDATE xui_sync_panel SET last_check_at = ?, last_status = ?, updated_at = ? WHERE panel_name = ?');
            if (!is_array($panel) || ($panel['type'] ?? '') !== 'x-ui_single' || !\Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($panel)) {
                $touch->execute(array($now, 'disabled_unsupported_panel', $now, $panelName));
                continue;
            }
            $read = self::xsync_fetch_client_retry($panel, (string) $row['reference_email']);
            if (!$read['ok'] || empty($read['ids'])) {
                $touch->execute(array($now, 'disabled_reference_unvalidated', $now, $panelName));
                continue;
            }
            self::xsync_enable_panel($panelName, (string) $row['reference_email'], $read['ids'], '');
            $csv = implode(',', $read['ids']);
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                \Faoxima\Core\Database\Db::update('marzban_panel', 'inboundid', $csv, 'name_panel', $panelName);
            } else {
                $upd = $pdo->prepare('UPDATE marzban_panel SET inboundid = ? WHERE name_panel = ?');
                $upd->execute(array($csv, $panelName));
            }
        }
    }

    public static function xsync_discover($pdo, $panelName, $referenceEmail)
    {
        $statuses = array('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold', 'disabled', 'disablebyadmin');
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT i.id_invoice, i.username FROM invoice i WHERE i.Service_location = ? AND i.Status IN ($marks) AND i.username IS NOT NULL AND i.username <> '' AND NOT EXISTS (SELECT 1 FROM xui_sync_service s WHERE s.svc_key = SHA1(CONCAT(i.Service_location, '|', i.username))) ORDER BY i.id_invoice LIMIT " . (int) XSYNC_DISCOVER_BATCH;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(array($panelName), $statuses));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare("INSERT IGNORE INTO xui_sync_service (svc_key, panel_name, email, id_invoice, state, managed_ids, updated_at) VALUES (?, ?, ?, ?, 'unclassified', '[]', ?)");
        $count = 0;
        foreach (is_array($rows) ? $rows : array() as $row) {
            $insert->execute(array(self::xsync_svc_key($panelName, $row['username']), $panelName, $row['username'], $row['id_invoice'], time()));
            $count += $insert->rowCount();
        }
        return $count;
    }

    public static function xsync_lock_name($pdo, $panelName)
    {
        return 'xsync_' . substr(sha1((string) ($GLOBALS['dbname'] ?? '') . '|' . $panelName), 0, 40);
    }

    public static function xsync_lock($pdo, $panelName)
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute(array(self::xsync_lock_name($pdo, $panelName)));
        return (int) $stmt->fetchColumn() === 1;
    }

    public static function xsync_unlock($pdo, $panelName)
    {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute(array(self::xsync_lock_name($pdo, $panelName)));
        $stmt->fetchColumn();
    }

    public static function xsync_load_panel($panelName)
    {
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $panelName, 'select');
            return is_array($panel) ? $panel : null;
        }
        $pdo = self::xsync_pdo();
        $stmt = $pdo->prepare('SELECT * FROM marzban_panel WHERE name_panel = ?');
        $stmt->execute(array($panelName));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function xsync_run_panel($panelName)
    {
        $pdo = self::xsync_pdo();
        if (!self::xsync_lock($pdo, $panelName)) {
            return array('status' => 'locked');
        }
        $summary = array('status' => 'ok', 'synced' => 0, 'failed' => 0, 'review' => 0, 'managed' => 0, 'excluded' => 0, 'superseded' => 0);
        try {
            $state = self::xsync_panel_state($pdo, $panelName);
            $panel = self::xsync_load_panel($panelName);
            if (!is_array($state) || (int) $state['enabled'] !== 1 || !is_array($panel)) {
                return array('status' => 'disabled');
            }
            if (($panel['type'] ?? '') !== 'x-ui_single' || !\Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($panel)) {
                $stmt = $pdo->prepare('UPDATE xui_sync_panel SET last_status = ?, last_check_at = ?, updated_at = ? WHERE panel_name = ?');
                $stmt->execute(array('unsupported_panel_mode', time(), time(), $panelName));
                return array('status' => 'unsupported');
            }
            self::xsync_check_reference($pdo, $panel, $state);
            self::xsync_migrate_services($pdo, $panelName, $state);
            self::xsync_discover($pdo, $panelName, $state['reference_email']);
            $limit = (int) XSYNC_BATCH_SIZE;
            $stmt = $pdo->prepare("SELECT s.*, (SELECT i.name_product FROM invoice i WHERE i.id_invoice = s.id_invoice LIMIT 1) AS product_name FROM xui_sync_service s WHERE s.panel_name = ? AND s.next_attempt_at <= ? AND (s.state IN ('unclassified','pending','error') OR (s.state = 'synced' AND s.synced_version < ?)) ORDER BY s.next_attempt_at ASC, s.updated_at ASC LIMIT " . $limit);
            $latestState = self::xsync_panel_state($pdo, $panelName);
            $stmt->execute(array($panelName, time(), (int) $latestState['ref_version']));
            $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach (is_array($batch) ? $batch : array() as $svc) {
                if (!self::xsync_time_left()) {
                    break;
                }
                $desired = self::xsync_current_desired($pdo, $panelName);
                if ($desired === null) {
                    break;
                }
                try {
                    if ($svc['state'] === 'unclassified') {
                        $outcome = self::xsync_classify_service($pdo, $panel, $svc, $desired);
                        if ($outcome === 'managed') {
                            $summary['managed']++;
                            $fresh = $pdo->prepare('SELECT * FROM xui_sync_service WHERE svc_key = ?');
                            $fresh->execute(array($svc['svc_key']));
                            $svc = $fresh->fetch(PDO::FETCH_ASSOC);
                            $outcome = self::xsync_reconcile_service($pdo, $panel, $svc, $desired);
                        }
                    } else {
                        if (strcasecmp($svc['email'], $desired['reference_email']) === 0) {
                            self::xsync_update_service($pdo, $svc['svc_key'], array('state' => 'excluded', 'review_reason' => 'reference_client'));
                            $outcome = 'excluded';
                        } else {
                            $outcome = self::xsync_reconcile_service($pdo, $panel, $svc, $desired);
                        }
                    }
                } catch (\Throwable $e) {
                    $outcome = self::xsync_fail_service($pdo, $svc, 'exception: ' . $e->getMessage());
                }
                if (isset($summary[$outcome])) {
                    $summary[$outcome]++;
                }
            }
        } catch (\Throwable $e) {
            $summary['status'] = 'exception';
        } finally {
            self::xsync_unlock($pdo, $panelName);
        }
        $counts = $pdo->prepare("SELECT state, COUNT(*) AS c FROM xui_sync_service WHERE panel_name = ? GROUP BY state");
        $counts->execute(array($panelName));
        $byState = array();
        foreach ($counts->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byState[$row['state']] = (int) $row['c'];
        }
        $summary['services_by_state'] = $byState;
        return $summary;
    }

    public static function xsync_run_all()
    {
        $pdo = self::xsync_pdo();
        if (!$pdo) {
            return array();
        }
        self::xsync_reset_run();
        self::xsync_ensure_tables($pdo);
        $ctx = &self::xsync_ctx();
        $ctx['deadline'] = microtime(true) + XSYNC_TIME_BUDGET;
        try {
            self::xsync_migrate_disabled_panels($pdo);
        } catch (\Throwable $e) {
        }
        $stmt = $pdo->query('SELECT panel_name FROM xui_sync_panel WHERE enabled = 1 ORDER BY panel_name');
        $results = array();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $panelName) {
            if (!self::xsync_time_left()) {
                break;
            }
            $results[$panelName] = self::xsync_run_panel($panelName);
        }
        return $results;
    }
}
