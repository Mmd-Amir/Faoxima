<?php

namespace Faoxima\Services\Panels;

use PDO;
use Throwable;

/**
 * Renewal: the extend operation, the stored renewal plans, reservation registration and the verification loop for uncertain outcomes.
 * Part of ManagePanel; the methods keep their original names, signatures and bodies.
 */
trait PanelRenewal
{
    function extend($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel, $rxTarget = null, $rxOptions = array())
    {
        global $pdo;
        $rxOptions = is_array($rxOptions) ? $rxOptions : array();
        $panel = $this->loadPanel($name_panel, "code_panel");
        $code_product = \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($code_product);
        $product = $code_product === 'custom_volume' ? array() : \Faoxima\Core\Database\Db::select("product", "*", "code_product", $code_product, "select");
        if (!is_array($product)) {
            $product = array();
        }
        $rxLogBase = array(
            'username' => (string) $username,
            'method' => (string) $Method_extend,
            'volume_gb' => $new_limit,
            'days' => $time_day,
            'ref' => (string) ($rxOptions['ref'] ?? ''),
            'operation_id' => (string) ($rxOptions['op_id'] ?? ''),
        );
        if ($panel == false) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend', 'panel_not_found', $rxLogBase + array('panel_name' => (string) $name_panel, 'mutation' => 'none'));
            return array(
                'status' => false,
                'code' => 'panel_not_found',
                'no_mutation' => true,
                'msg' => 'data not found'
            );
        }
        $rxLogBase['panel'] = $panel;
        $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        $rxLogBase['invoice_id'] = is_array($invoice) ? (string) ($invoice['id_invoice'] ?? '') : '';
        $rxLogBase['invoice_status'] = is_array($invoice) ? ($invoice['Status'] ?? null) : null;
        $data_user = $this->DataUser($panel['name_panel'], $username);
        if (!is_array($data_user) || ($data_user['status'] ?? '') == "Unsuccessful") {
            $rxReadMsg = is_array($data_user) ? ($data_user['msg'] ?? '') : 'invalid panel response';
            $rxReadCode = \Faoxima\Services\Fulfillment\Delivery::userMissingMessage(is_scalar($rxReadMsg) ? (string) $rxReadMsg : '') ? 'user_missing' : 'panel_read_failed';
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend', $rxReadCode, $rxLogBase + array('snapshot_error' => is_scalar($rxReadMsg) ? (string) $rxReadMsg : 'non-scalar panel message', 'mutation' => 'none'));
            return array(
                'status' => false,
                'code' => \Faoxima\Services\Fulfillment\Delivery::userMissingMessage(is_scalar($rxReadMsg) ? (string) $rxReadMsg : '') ? 'user_missing' : 'panel_read_failed',
                'no_mutation' => true,
                'msg' => $rxReadMsg
            );
        }
        $rxMode = \Faoxima\Services\Renewal\Plans::renewalMode($Method_extend);
        if (($rxOptions['source'] ?? '') === 'payment' && \Faoxima\Services\Renewal\ReservationCriteria::reservationStoredSelection($rxOptions['reservation'] ?? null) !== null) {
            $rxMode = \Faoxima\Services\Renewal\Plans::renewalMode('رزرو اشتراک');
        }
        if ($rxMode === null) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend', 'unsupported_renewal_method', $rxLogBase + array('mutation' => 'none'));
            return array(
                'status' => false,
                'code' => 'unsupported_renewal_method',
                'no_mutation' => true,
                'msg' => 'unsupported renewal method: ' . (string) $Method_extend
            );
        }
        $rxSnapshot = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($data_user, (string) $panel['type']);
        $rxPolicy = (string) ($rxMode['policy'] ?? '');
        $rxResetUsage = !empty($rxMode['reset_usage']);
        if (!empty($rxMode['queued']) && !is_array($rxTarget)) {
            $rxRegistered = $this->rxRenewalRegisterReservation($panel, $invoice, $product, $code_product, $new_limit, $time_day, $username, $rxSnapshot, $rxOptions);
            if ($rxRegistered !== null) {
                if (empty($rxRegistered['status'])) {
                    \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend_reservation', (string) ($rxRegistered['code'] ?? 'reservation_refused'), $rxLogBase + (($rxRegistered['code'] ?? '') === 'admin_blocked' ? array('admin_blocked' => true) : array()) + array('snapshot' => $rxSnapshot, 'error' => $rxRegistered['msg'] ?? null, 'detail' => $rxRegistered['detail'] ?? null, 'mutation' => 'none'));
                }
                return $rxRegistered;
            }
            $rxSelection = (array) ($rxOptions['reservation'] ?? array());
            $rxPolicy = (string) ($rxSelection['policy'] ?? 'reset_all');
            $rxResetUsage = !empty($rxSelection['reset_usage']) && \Faoxima\Services\Renewal\Plans::renewalPolicyAllowsUsageReset($rxPolicy);
        }
        if (($rxOptions['source'] ?? '') === 'admin' && empty($rxMode['queued']) && isset($rxOptions['policy']) && isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[(string) $rxOptions['policy']])) {
            $rxPolicy = (string) $rxOptions['policy'];
            $rxResetUsage = false;
        }
        if ($panel['type'] == "Manualsale") {
            $statement = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' AND codeproduct = :code_product ORDER BY RAND() LIMIT 1");
            $statement->execute(array(
                ':code_panel' => $panel['code_panel'],
                ':code_product' => $code_product,
            ));
            $newConfig = $statement->fetch(PDO::FETCH_ASSOC);
            if ($newConfig == false) {
                $statement2 = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' ORDER BY RAND() LIMIT 1");
                $statement2->execute(array(':code_panel' => $panel['code_panel']));
                $newConfig = $statement2->fetch(PDO::FETCH_ASSOC);
            }
            if ($newConfig == false) {
                \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend', 'manual_stock_empty', $rxLogBase + array('mutation' => 'none'));
                return array(
                    'status' => false,
                    'code' => 'manual_stock_empty',
                    'no_mutation' => true,
                    'msg' => 'no manual config available in stock for renewal'
                );
            }

            if (!empty($newConfig['group_id'])) {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND (group_id IS NULL OR group_id <> :group_id)");
                $release->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
                $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = :username WHERE group_id = :group_id AND status = 'active'");
                $claimGroup->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
            } else {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND id <> :new_id");
                $release->execute(array(
                    ':username' => $username,
                    ':new_id' => $newConfig['id'],
                ));

                \Faoxima\Core\Database\Db::update("manualsell", "status", "selled", "id", $newConfig['id']);
                \Faoxima\Core\Database\Db::update("manualsell", "username", $username, "id", $newConfig['id']);
            }

            if (is_array($invoice)) {
                \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle', 'time_cycle'), array('volume', 'volume_report', 'time', 'time_report'));
            }
            \Faoxima\Core\Database\Db::update("invoice", 'Status', "active", "username", $username);
            return array(
                'status' => true,
                'subscription_url' => $newConfig['contentrecord'],
                'configs' => "",
                'file_ext' => $newConfig['file_ext']
            );
        }
        if (is_array($rxTarget) && !empty($rxTarget['rx_renewal_plan']) && !empty($rxTarget['ok'])) {
            $rxPlan = $rxTarget;
        } else {
            if (empty($rxSnapshot['ok']) || empty($rxSnapshot['exists'])) {
                \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('extend', 'panel_state_unavailable', $rxLogBase + array('snapshot' => $rxSnapshot, 'snapshot_error' => $rxSnapshot['error'] ?? null, 'mutation' => 'none'));
                return array(
                    'status' => false,
                    'code' => 'panel_state_unavailable',
                    'no_mutation' => true,
                    'msg' => 'panel state unavailable: ' . (string) ($rxSnapshot['error'] ?? '')
                );
            }
            $rxPlan = \Faoxima\Services\Renewal\ProviderRenewal::renewalPlanForSnapshot($pdo, $rxSnapshot, $panel, (string) $username, $rxPolicy, $rxResetUsage, $new_limit, $time_day, (string) $rxMode['method'], null, array(
                'stage' => 'extend_plan',
                'invoice_id' => $rxLogBase['invoice_id'],
                'ref' => $rxLogBase['ref'],
                'operation_id' => $rxLogBase['operation_id'],
            ));
            if (empty($rxPlan['ok'])) {
                return array(
                    'status' => false,
                    'code' => (string) ($rxPlan['reason'] ?? 'renewal_refused'),
                    'no_mutation' => true,
                    'plan' => $rxPlan,
                    'msg' => 'renewal refused: ' . (string) ($rxPlan['reason'] ?? '')
                );
            }
        }
        return $this->applyRenewalPlan($panel, $data_user, $invoice, $product, $rxPlan, array_merge($rxOptions, array(
            'username' => (string) $username,
            'method' => (string) $Method_extend,
            'product_name' => (string) ($product['name_product'] ?? ''),
        )));
    }



    private function rxRenewalRegisterReservation(array $panel, $invoice, array $product, $code_product, $new_limit, $time_day, $username, array $snapshot, array $rxOptions)
    {
        global $pdo;
        $paymentRef = (string) (($rxOptions['reservation']['payment_ref'] ?? '') ?: ($rxOptions['ref'] ?? ''));
        $existing = $paymentRef !== '' ? \Faoxima\Services\Renewal\ReservationOrders::reservationByPaymentRef($pdo, $paymentRef) : null;
        if (is_array($existing)) {
            return $this->rxRenewalReservationResult($existing);
        }
        $isManual = $panel['type'] == "Manualsale";
        $stored = (string) ($rxOptions['source'] ?? '') === 'payment' ? \Faoxima\Services\Renewal\ReservationCriteria::reservationStoredSelection($rxOptions['reservation'] ?? null) : null;
        $selection = $stored !== null ? $stored : \Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSelection($panel);
        if ($isManual) {
            $selection['policy'] = 'reset_all';
            $selection['reset_usage'] = false;
        }
        $normalized = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeSelection($selection, $isManual ? null : $snapshot, time(), $stored !== null);
        if (empty($normalized['ok'])) {
            return array(
                'status' => false,
                'code' => (string) $normalized['reason'],
                'no_mutation' => true,
                'detail' => (string) ($normalized['detail'] ?? ''),
                'msg' => 'reservation refused: ' . (string) $normalized['reason']
            );
        }
        if ($normalized['trigger'] === 'immediate') {
            return null;
        }
        $context = \Faoxima\Services\Renewal\ProviderRenewal::renewalContext($pdo, $panel, (string) $username);
        if ($context['admin_blocked'] || ($snapshot['status'] ?? '') === 'disabled') {
            return array('status' => false, 'code' => 'admin_blocked', 'no_mutation' => true, 'msg' => 'service is blocked by an administrator');
        }
        if (!is_numeric($new_limit) || !is_numeric($time_day) || (float) $new_limit < 0 || (float) $time_day < 0) {
            return array('status' => false, 'code' => 'invalid_purchase', 'no_mutation' => true, 'msg' => 'invalid purchased quantities');
        }
        $invoice = is_array($invoice) ? $invoice : array();
        $registered = \Faoxima\Services\Renewal\ReservationOrders::reservationRegister($pdo, array(
            'service_key' => \Faoxima\Services\Renewal\ReservationStore::serviceKey($invoice['id_invoice'] ?? '', $panel['code_panel'], $username),
            'id_user' => (string) ($invoice['id_user'] ?? ($rxOptions['id_user'] ?? '')),
            'username' => (string) $username,
            'name_panel' => (string) $panel['name_panel'],
            'code_panel' => (string) $panel['code_panel'],
            'code_product' => (string) $code_product,
            'new_limit_gb' => (string) $new_limit,
            'time_day' => (string) $time_day,
            'price' => (string) ($rxOptions['price'] ?? ($product['price_product'] ?? '0')),
            'id_invoice' => $invoice['id_invoice'] ?? null,
            'bottype' => $invoice['bottype'] ?? null,
            'trigger' => $normalized['trigger'],
            'conditions' => $normalized['conditions'],
            'trigger_at' => $normalized['trigger_at'],
            'policy' => $normalized['policy'],
            'reset_usage' => $normalized['reset_usage'],
            'payment_ref' => $paymentRef,
            'fulfillment' => array(
                'product_name' => (string) ($product['name_product'] ?? ($invoice['name_product'] ?? '')),
                'inbounds' => $product['inbounds'] ?? null,
                'panel_type' => (string) $panel['type'],
                'source' => (string) ($rxOptions['source'] ?? ''),
            ),
        ));
        if (empty($registered['ok'])) {
            return array(
                'status' => false,
                'code' => (string) ($registered['code'] ?? 'queued_renewal_insert_failed'),
                'no_mutation' => true,
                'msg' => (string) ($registered['msg'] ?? ($registered['code'] ?? 'reservation failed'))
            );
        }
        $row = is_array($registered['row'] ?? null) ? $registered['row'] : \Faoxima\Services\Renewal\ReservationRecovery::reservationFetch($pdo, (int) $registered['id']);
        return $this->rxRenewalReservationResult(is_array($row) ? $row : array(
            'id' => (int) $registered['id'],
            'trigger_type' => $normalized['trigger'],
            'conditions' => implode(',', $normalized['conditions']),
            'trigger_at' => $normalized['trigger_at'],
            'policy' => $normalized['policy'],
            'reset_usage' => $normalized['reset_usage'] ? 1 : 0,
        ));
    }



    private function rxRenewalReservationResult(array $row)
    {
        $conditions = \Faoxima\Services\Renewal\ReservationCriteria::reservationRowConditions($row);
        $triggerAt = isset($row['trigger_at']) && $row['trigger_at'] !== null ? (int) $row['trigger_at'] : null;
        $policy = (string) ($row['policy'] ?? 'reset_all');
        return array(
            'status' => true,
            'queued' => true,
            'reservation_id' => (int) $row['id'],
            'reservation' => array(
                'trigger' => (string) ($row['trigger_type'] ?? \Faoxima\Services\Renewal\ReservationCriteria::reservationTriggerForConditions($conditions)),
                'conditions' => $conditions,
                'trigger_at' => $triggerAt,
                'policy' => $policy,
                'reset_usage' => !empty($row['reset_usage']),
                'trigger_label' => \Faoxima\Services\Renewal\Reservations::rxReservationActivationText($conditions, $triggerAt),
                'policy_label' => isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[$policy]) ? \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy, !empty($row['reset_usage'])) : $policy,
                'confirmation' => \Faoxima\Services\Renewal\Reservations::rxReservationConfirmationText($conditions, $triggerAt),
            ),
        );
    }



    private function rxRenewalInvoiceRow($username, $panel)
    {
        global $pdo;
        if ($pdo instanceof PDO && is_array($panel)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND Service_location = :location ORDER BY time_sell DESC LIMIT 1");
                $stmt->execute(array(':username' => (string) $username, ':location' => (string) $panel['name_panel']));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (Throwable $e) {
            }
        }
        $row = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $username, "select");
        return is_array($row) ? $row : null;
    }



    public function rxRenewalLocalSuccess($panel, $username, $idInvoice = '')
    {
        $invoice = ((string) $idInvoice !== '') ? \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $idInvoice, "select") : null;
        if (!is_array($invoice)) {
            $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        }
        if (!is_array($invoice)) {
            return;
        }
        \Faoxima\Services\Fulfillment\DeliveryState::notificationCycleReset($invoice['id_invoice'], array('volume_cycle', 'time_cycle'), array('volume', 'volume_report', 'time', 'time_report'));
        if (($panel['type'] ?? '') != "WGDashboard") {
            \Faoxima\Core\Database\Db::update("invoice", 'user_info', null, "id_invoice", $invoice['id_invoice']);
        }
        \Faoxima\Core\Database\Db::update("invoice", 'uuid', null, "id_invoice", $invoice['id_invoice']);
        if (($invoice['Status'] ?? '') !== 'disablebyadmin') {
            \Faoxima\Core\Database\Db::update("invoice", 'Status', "active", "id_invoice", $invoice['id_invoice']);
        }
    }



    public function applyStoredRenewalPlan($name_panel, $username, array $plan, array $opts = array())
    {
        $panel = $this->loadPanel((string) $name_panel, 'name_panel');
        if (!is_array($panel)) {
            return array('status' => false, 'code' => 'panel_not_found', 'no_mutation' => true, 'msg' => 'Panel Not Found');
        }
        $data_user = $this->DataUser($panel['name_panel'], $username);
        if (!is_array($data_user) || ($data_user['status'] ?? '') == "Unsuccessful") {
            return array('status' => false, 'code' => 'panel_read_failed', 'no_mutation' => true, 'msg' => is_array($data_user) ? ($data_user['msg'] ?? '') : '');
        }
        $invoice = !empty($opts['id_invoice']) ? \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $opts['id_invoice'], "select") : null;
        if (!is_array($invoice)) {
            $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        }
        $code = \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($opts['code_product'] ?? '');
        $product = ($code !== '' && $code !== 'custom_volume') ? \Faoxima\Core\Database\Db::select("product", "*", "code_product", $code, "select") : array();
        if (!is_array($product)) {
            $product = array();
        }
        $fulfillment = is_array($opts['fulfillment'] ?? null) ? $opts['fulfillment'] : array();
        if (empty($product['inbounds']) && !empty($fulfillment['inbounds'])) {
            $product['inbounds'] = $fulfillment['inbounds'];
        }
        return $this->applyRenewalPlan($panel, $data_user, $invoice, $product, $plan, array_merge($opts, array('username' => (string) $username)));
    }



    public function applyRenewalPlan(array $panel, array $data_user, $invoice, $product, array $plan, array $opts = array())
    {
        global $pdo;
        $username = (string) ($opts['username'] ?? ($data_user['username'] ?? ''));
        $invoice = is_array($invoice) ? $invoice : array();
        $product = is_array($product) ? $product : array();
        $idInvoice = (string) ($invoice['id_invoice'] ?? ($opts['id_invoice'] ?? ''));
        $serviceKey = (string) ($opts['service_key'] ?? \Faoxima\Services\Renewal\ReservationStore::serviceKey($idInvoice, $panel['code_panel'] ?? '', $username));
        $opId = (string) ($opts['op_id'] ?? \Faoxima\Payments\Fulfillment\Claims::newToken());
        if (($panel['type'] ?? '') === 'jovadpanel' && in_array((string) ($opts['source'] ?? ''), ['payment', 'reservation'], true) && trim((string) ($opts['ref'] ?? '')) !== '') {
            $opId = hash('sha256', 'jovad|' . $opts['source'] . '|' . $opts['ref'] . '|' . $serviceKey);
        }
        $lockOwner = isset($opts['lock_owner']) ? (string) $opts['lock_owner'] : '';
        $ownLock = $lockOwner === '';
        $source = (string) ($opts['source'] ?? 'wallet');
        $diag = array(
            'operation_id' => $opId,
            'service_key' => $serviceKey,
            'username' => $username,
            'panel' => (string) $panel['name_panel'],
            'panel_type' => (string) $panel['type'],
            'method' => (string) ($opts['method'] ?? ''),
            'policy' => (string) ($plan['policy'] ?? ''),
            'source' => $source,
            'ref' => (string) ($opts['ref'] ?? ''),
        );
        $before = array(
            'data_limit' => \Faoxima\Payments\Fulfillment\Claims::int($plan['before_data_limit'] ?? 0),
            'expire' => \Faoxima\Payments\Fulfillment\Claims::int($plan['before_expire'] ?? 0),
            'used_traffic' => \Faoxima\Payments\Fulfillment\Claims::int($plan['before_used_traffic'] ?? 0),
            'status' => (string) ($plan['before_status'] ?? ''),
        );
        $target = array(
            'data_limit' => \Faoxima\Payments\Fulfillment\Claims::int($plan['data_limit'] ?? 0),
            'expire' => \Faoxima\Payments\Fulfillment\Claims::int($plan['expire'] ?? 0),
            'used_traffic' => \Faoxima\Payments\Fulfillment\Claims::int($plan['target_used_traffic'] ?? 0),
            'reset_usage' => !empty($plan['reset_usage']),
            'pending_duration' => $plan['target_pending_duration'] ?? null,
        );
        $rxLogFail = function ($stage, $reason, $mutation, $error = null) use ($panel, $username, $idInvoice, $invoice, $plan, $diag, $before) {
            \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, $reason, array(
                'panel' => $panel,
                'username' => $username,
                'invoice_id' => $idInvoice,
                'invoice_status' => $invoice['Status'] ?? null,
                'method' => $diag['method'],
                'policy' => $diag['policy'],
                'plan' => $plan,
                'before' => $before,
                'operation_id' => $diag['operation_id'],
                'ref' => $diag['ref'],
                'mutation' => $mutation,
                'error' => $error,
            ));
        };
        if ($ownLock) {
            $rxLock = \Faoxima\Services\Renewal\ReservationStore::lockAcquireResult($pdo, $serviceKey, $opId);
            if (empty($rxLock['ok'])) {
                $rxLockReason = (string) ($rxLock['reason'] ?? 'lock_unavailable');
                $rxLockContended = !empty($rxLock['contended']) && $rxLockReason === 'lock_contended';
                $rxLockCode = $rxLockContended
                    ? 'renewal_in_progress'
                    : ($rxLockReason === 'schema_unavailable' ? 'renewal_schema_unavailable' : 'renewal_lock_unavailable');
                $rxLogFail('apply_lock', $rxLockCode, 'none', $rxLockReason);
                return array(
                    'status' => false,
                    'code' => $rxLockCode,
                    'no_mutation' => true,
                    'operation_id' => $opId,
                    'msg' => $rxLockContended
                        ? 'another renewal operation is running for this service'
                        : ($rxLockReason === 'schema_unavailable' ? 'renewal schema is not ready' : 'renewal lock storage is unavailable')
                );
            }
        }
        try {
            if ($ownLock && in_array($source, ['wallet', 'miniapp'], true)) {
                try {
                    $blocked = \Faoxima\Services\Renewal\ReservationStore::walletRenewalBlocked($pdo, $serviceKey);
                } catch (Throwable $lookupError) {
                    $rxLogFail('apply_lookup', 'renewal_recovery_lookup_failed', 'none', $lookupError->getMessage());
                    return ['status' => false, 'code' => 'renewal_recovery_lookup_failed', 'no_mutation' => true, 'operation_id' => $opId];
                }
                if ($blocked) {
                    $rxLogFail('apply_lookup', 'renewal_in_progress', 'none');
                    return ['status' => false, 'code' => 'renewal_in_progress', 'no_mutation' => true, 'operation_id' => $opId];
                }
            }
            if (!\Faoxima\Services\Renewal\ReservationStore::opStart($pdo, array(
                'op_id' => $opId,
                'source' => $source,
                'ref' => $opts['ref'] ?? null,
                'service_key' => $serviceKey,
                'username' => $username,
                'name_panel' => (string) $panel['name_panel'],
                'id_invoice' => $idInvoice,
                'method' => $opts['method'] ?? null,
                'policy' => $plan['policy'] ?? null,
                'before' => $before,
                'target' => (trim((string) ($opts['product_name'] ?? '')) !== '' && !isset($plan['rx_product_name'])) ? array_merge($plan, array('rx_product_name' => trim((string) $opts['product_name']))) : $plan,
                'finalize' => is_array($opts['finalize'] ?? null) ? $opts['finalize'] : null,
            ))) {
                $rxLogFail('apply_persist', 'operation_persist_failed', 'none');
                return array('status' => false, 'code' => 'operation_persist_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => 'renewal operation could not be persisted');
            }
            $resetDone = false;
            if (!empty($plan['reset_usage']) && ($panel['type'] ?? '') !== 'jovadpanel' && \Faoxima\Payments\Fulfillment\Claims::int($data_user['used_traffic'] ?? 0) > 0) {
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('stage' => 'reset_sent'));
                $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
                if (!is_array($reset) || empty($reset['status'])) {
                    $resetMsg = is_array($reset) ? (is_scalar($reset['msg'] ?? null) ? (string) $reset['msg'] : json_encode($reset['msg'] ?? '')) : '';
                    $probe = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($this, (string) $panel['name_panel'], $username, (string) $panel['type']);
                    if (empty($probe['ok']) || empty($probe['exists'])) {
                        return $this->rxRenewalUncertain($opId, $plan, $before, null, 'reset_result_unknown: ' . $resetMsg, $diag);
                    }
                    $probeUsed = \Faoxima\Payments\Fulfillment\Claims::int($probe['used_traffic']);
                    if ($probeUsed > RX_RN_USAGE_SLACK && $probeUsed + RX_RN_USAGE_SLACK > \Faoxima\Payments\Fulfillment\Claims::int($data_user['used_traffic'] ?? 0)) {
                        \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('state' => 'failed', 'stage' => 'reset_failed', 'last_error' => $resetMsg));
                        $rxLogFail('apply_reset', 'reset_failed', 'attempted_not_applied', $resetMsg);
                        return array('status' => false, 'code' => 'reset_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => 'error reset : ' . $resetMsg);
                    }
                }
                $resetDone = true;
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('stage' => 'reset_done'));
            }
            \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('stage' => 'modify_sent'));
            $write = $this->rxRenewalPanelWrite($panel, $data_user, $invoice, $product, $plan, $username, $opId);
            if (empty($write['status'])) {
                $writeMsg = is_scalar($write['msg'] ?? null) ? (string) $write['msg'] : json_encode($write['msg'] ?? '');
                if (($panel['type'] ?? '') === 'jovadpanel' && !empty($write['uncertain'])) {
                    return $this->rxRenewalUncertain($opId, $plan, $before, null, $writeMsg, $diag);
                }
                $probe = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($this, (string) $panel['name_panel'], $username, (string) $panel['type']);
                $class = \Faoxima\Services\Renewal\Plans::renewalClassify($probe, $plan);
                $untouched = !empty($probe['ok']) && !empty($probe['exists'])
                    && \Faoxima\Payments\Fulfillment\Claims::int($probe['data_limit']) === $before['data_limit']
                    && \Faoxima\Payments\Fulfillment\Claims::int($probe['expire']) === $before['expire']
                    && (bool) ($probe['pending'] ?? false) === (bool) ($plan['before_pending'] ?? false);
                if ($class === 'before' && $untouched && !$resetDone) {
                    \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('state' => 'failed', 'stage' => 'modify_failed', 'last_error' => $writeMsg));
                    $rxLogFail('apply_write', 'panel_write_failed', 'attempted_not_applied', $writeMsg);
                    return array('status' => false, 'code' => 'panel_write_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => $writeMsg);
                }
                if ($class === 'before') {
                    $write = $this->rxRenewalPanelWrite($panel, $data_user, $invoice, $product, $plan, $username, $opId);
                    if (empty($write['status'])) {
                        return $this->rxRenewalUncertain($opId, $plan, $before, \Faoxima\Services\Fulfillment\ProviderState::limits($probe), 'partial_write: ' . $writeMsg, $diag);
                    }
                } elseif ($class !== 'target') {
                    return $this->rxRenewalUncertain($opId, $plan, $before, !empty($probe['ok']) ? \Faoxima\Services\Fulfillment\ProviderState::limits($probe) : null, 'write_result_' . $class . ': ' . $writeMsg, $diag);
                }
            }
            $verify = $this->rxRenewalVerifyLoop($panel, $username, $plan);
            if (!empty($verify['ok'])) {
                \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('state' => 'verified', 'stage' => 'done', 'result_state' => $verify['after'], 'last_error' => null));
                $this->rxRenewalLocalSuccess($panel, $username, $idInvoice);
                $adminFinalize = ($source === 'admin' && is_callable([\Faoxima\Services\Renewal\AdministrativeSettlement::class, 'arFinalizeOperation'])) ? \Faoxima\Services\Renewal\AdministrativeSettlement::arFinalizeOperation($pdo, $opId) : null;
                return array(
                    'status' => true,
                    'data' => $write['data'] ?? null,
                    'operation_id' => $opId,
                    'admin_finalize' => $adminFinalize,
                    'rx_renewal' => array(
                        'plan' => $plan,
                        'before' => $before,
                        'target' => $target,
                        'after' => $verify['after'],
                        'verified' => true,
                        'operation_id' => $opId,
                    ),
                );
            }
            return $this->rxRenewalUncertain($opId, $plan, $before, $verify['after'], 'verification_failed: ' . (string) $verify['reason'], $diag);
        } catch (Throwable $e) {
            return $this->rxRenewalUncertain($opId, $plan, $before, null, 'exception: ' . $e->getMessage(), $diag);
        } finally {
            if ($ownLock) {
                \Faoxima\Services\Renewal\ReservationStore::lockRelease($pdo, $serviceKey, $opId);
            }
        }
    }



    private function rxRenewalVerifyLoop(array $panel, $username, array $plan)
    {
        $delays = defined('RX_RN_VERIFY_DELAYS') ? array_map('intval', explode(',', (string) RX_RN_VERIFY_DELAYS)) : array(0, 1, 2);
        $result = array('ok' => false, 'reason' => 'unreadable', 'after' => null);
        foreach ($delays as $delay) {
            if ($delay > 0) {
                sleep($delay);
            }
            $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($this, (string) $panel['name_panel'], (string) $username, (string) $panel['type']);
            if (empty($snapshot['ok']) || empty($snapshot['exists'])) {
                $result = array('ok' => false, 'reason' => 'unreadable: ' . (string) ($snapshot['error'] ?? 'missing'), 'after' => null);
                continue;
            }
            $verify = \Faoxima\Services\Renewal\Plans::renewalVerify($snapshot, $plan);
            $after = \Faoxima\Services\Fulfillment\ProviderState::limits($snapshot);
            $after['status'] = (string) ($snapshot['status'] ?? '');
            $after['pending_duration'] = $snapshot['pending_duration'] ?? null;
            $result = array('ok' => !empty($verify['ok']), 'reason' => (string) $verify['reason'], 'after' => $after, 'checks' => $verify['checks']);
            if (!empty($verify['ok'])) {
                break;
            }
        }
        return $result;
    }



    private function rxRenewalUncertain($opId, array $plan, array $before, $after, $reason, array $diag)
    {
        global $pdo;
        \Faoxima\Services\Renewal\ReservationStore::opUpdate($pdo, $opId, array('state' => 'uncertain', 'last_error' => $reason, 'result_state' => $after, 'next_attempt_at' => time() + 120));
        \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('apply_uncertain', trim((string) $reason) !== '' ? (string) $reason : 'pending_verification', array(
            'panel_name' => (string) ($diag['panel'] ?? ''),
            'panel_type' => (string) ($diag['panel_type'] ?? ''),
            'username' => (string) ($diag['username'] ?? ''),
            'method' => $diag['method'] ?? '',
            'policy' => $diag['policy'] ?? '',
            'plan' => $plan,
            'before' => $before,
            'after' => is_array($after) ? $after : null,
            'operation_id' => $opId,
            'ref' => $diag['ref'] ?? '',
            'mutation' => 'uncertain',
            'error' => $reason,
        ));
        if (!in_array((string) ($diag['source'] ?? ''), array('payment', 'reservation'), true)) {
            \Faoxima\Payments\Fulfillment\Settlement::alertAdmin('renewal:' . $opId, 'نتیجه تمدید هنوز تأیید نشده و به‌صورت خودکار بررسی می‌شود (پنل: ' . (string) ($diag['panel'] ?? '') . ')', array('username' => (string) ($diag['username'] ?? '')));
        }
        return array(
            'status' => false,
            'pending_verification' => true,
            'code' => 'pending_verification',
            'operation_id' => $opId,
            'msg' => $reason,
            'rx_renewal' => array(
                'plan' => $plan,
                'before' => $before,
                'target' => array('data_limit' => $plan['data_limit'] ?? null, 'expire' => $plan['expire'] ?? null),
                'after' => $after,
                'verified' => false,
                'operation_id' => $opId,
            ),
        );
    }



    private function rxRenewalPanelWrite(array $panel, array $data_user, array $invoice, array $product, array $plan, $username, $opId = '')
    {
        global $pdo;
        $data_limit_new = (int) $plan['data_limit'];
        $time_new = (int) $plan['expire'];
        $pending = !empty($plan['target_pending']);
        $pendingDuration = (int) ($plan['target_pending_duration'] ?? 0);
        $enable = !empty($plan['enable']);
        $inbounds = is_string($panel['inbounds'] ?? null) ? json_decode($panel['inbounds']) : "{}";
        if (isset($product['inbounds']) && $product['inbounds'] != null) {
            $inbounds = is_string($product['inbounds']) ? json_decode($product['inbounds']) : $product['inbounds'];
        }
        if ($panel['type'] == "remnawave") {
            $local = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $params = array(
                'id' => (int) $local['panel_user_id'],
                'expireAt' => \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_iso_from_timestamp($time_new),
                'trafficLimitBytes' => $data_limit_new,
            );
            if ($enable) {
                $params['status'] = 'ACTIVE';
            }
            $res = $mgr->updateUser($params, null, $pdo);
            return array('status' => !empty($res['ok']), 'msg' => empty($res['ok']) ? 'remnawave extend failed' : '', 'data' => $res['data'] ?? null);
        }
        if ($panel['type'] == "WGDashboard") {
            $allowResponse = \Faoxima\Services\Panels\Drivers\WgDashboard::allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array('status' => false, 'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : '');
            }
            $datauser = \Faoxima\Services\Panels\Drivers\WgDashboard::get_userwg($username, $panel['name_panel']);
            if (!is_array($datauser) || (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id']))) {
                return array('status' => false, 'msg' => is_array($datauser) && isset($datauser['msg']) ? $datauser['msg'] : '');
            }
            $jobs = is_array($datauser['jobs'] ?? null) ? $datauser['jobs'] : array();
            $fields = array();
            if (($plan['time_mode'] ?? '') !== 'keep') {
                $fields['date'] = $time_new > 0 ? date("Y-m-d H:i:s", $time_new) : null;
            }
            if (($plan['traffic_mode'] ?? '') !== 'keep') {
                $fields['total_data'] = $data_limit_new > 0 ? $data_limit_new / pow(1024, 3) : null;
            }
            foreach ($fields as $field => $value) {
                foreach ($jobs as $job) {
                    if (($job['Field'] ?? '') !== $field) {
                        continue;
                    }
                    $deleteJob = \Faoxima\Services\Panels\Drivers\WgDashboard::deletejob($panel['name_panel'], array("Job" => $job));
                    if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                        return array('status' => false, 'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : '');
                    }
                }
                if ($value !== null) {
                    $setJob = \Faoxima\Services\Panels\Drivers\WgDashboard::setjob($panel['name_panel'], $field, $value, $datauser['id']);
                    if (isset($setJob['status']) && $setJob['status'] === false) {
                        return array('status' => false, 'msg' => isset($setJob['msg']) ? $setJob['msg'] : '');
                    }
                }
            }
            return array('status' => true);
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($data_user['group_ids']) && is_array($data_user['group_ids'])) ? $data_user['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => ($pending || $time_new == 0) ? 0 : date('c', $time_new),
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if (!empty($invoice['uuid'])) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($data_user['inbounds']) && is_array($data_user['inbounds'])) ? $data_user['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            foreach ($existingInbounds as $protocol => $tags) {
                if (is_array($tags)) {
                    $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                    $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                }
            }
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $pending ? 0 : $time_new,
                'inbounds' => !empty($mergedInbounds) ? $mergedInbounds : $inbounds,
            );
            if (!empty($invoice['uuid'])) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "totalGB" => $data_limit_new,
                                "expiryTime" => $pending ? -1 * $pendingDuration * 1000 : $time_new * 1000,
                                "enable" => true,
                            )
                        ),
                        'decryption' => 'none',
                        'fallbacks' => array(),
                    )
                ),
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($data_user['service_ids']) ? $data_user['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = \Faoxima\Services\Panels\Drivers\Guard::guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array('status' => false, 'msg' => $serviceResult['msg']);
            }
            $data = array(
                "limit_usage" => $data_limit_new,
                "limit_expire" => \Faoxima\Services\Panels\Drivers\Guard::guardNormalizeExpire($time_new),
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $pending ? 0 : $time_new,
            );
        } elseif ($panel['type'] == "jovadpanel") {
            return \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelApplyRenewalPlan($panel, $data_user, $plan, (string) $username, (string) $opId);
        } else {
            return array('status' => false, 'msg' => 'unsupported panel type for renewal');
        }
        if (in_array($panel['type'], array('marzban', 'pasarguard', 'rebecca'), true)) {
            if ($pending) {
                $data['status'] = 'on_hold';
                $data['on_hold_expire_duration'] = $pendingDuration;
            } elseif ($enable) {
                $data['status'] = 'active';
            }
        }
        $extend = $this->Modifyuser($username, $panel['name_panel'], $data);
        if (!is_array($extend) || empty($extend['status'])) {
            return array('status' => false, 'msg' => is_array($extend) ? ($extend['msg'] ?? '') : 'invalid panel response');
        }
        return array('status' => true, 'data' => $extend['data'] ?? null);
    }
}
