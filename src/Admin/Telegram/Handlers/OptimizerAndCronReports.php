<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $callback_query_id, $from_id, $liveInvoice, $message_id, $pdo, $rxOptOrphanedBatch, $rxOptScanCount, $rxOptScanStmt, $rxOptTicketCount, $rxOptTicketStmt, $scanResult.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class OptimizerAndCronReports
{
    public static function handle(
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $callback_query_id,
        $from_id,
        $liveInvoice,
        $message_id,
        $pdo,
        $rxOptOrphanedBatch,
        $rxOptScanCount,
        $rxOptScanStmt,
        $rxOptTicketCount,
        $rxOptTicketStmt,
        $scanResult
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
            [$rxOptText, $rxOptKeyboard] = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_render(\Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_state());
            Editmessagetext($from_id, $message_id, $rxOptText, $rxOptKeyboard);
            break;
        case 2:
            $rxOptState = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_state();
            $rxOptActive = is_array($rxOptState) && in_array($rxOptState['status'] ?? '', ['starting', 'running', 'cancelling'], true);
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $rxOptActive ? "⏳ درخواست لغو ثبت شد" : "", 'cache_time' => 0]);
            }
            if ($rxOptActive) {
                $rxOptCancel = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_cancel_request($rxOptState, $from_id);
                $rxOptState['status'] = 'cancelling';
                $rxOptState['cancel_requested_at'] = $rxOptCancel['requested_at'] ?? time();
                $rxOptState['cancel_requested_by'] = $rxOptCancel['requested_by'] ?? (string) $from_id;
            }
            [$rxOptText, $rxOptKeyboard] = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_render($rxOptState);
            Editmessagetext($from_id, $message_id, $rxOptText, $rxOptKeyboard);
            break;
        case 3:
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            }
            $rxOptRedisKey = RX_OPT_LOCK_KEY;
            $rxOptRedisToken = null;
            $rxOptRedisTtlMs = RX_OPT_LOCK_TTL * 1000;
            $rxOptLastRefresh = time();
            $rxOptBusy = false;
            $rxOptLockHandle = @fopen(\Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_path('lock'), 'c');
            if ($rxOptLockHandle !== false && !flock($rxOptLockHandle, LOCK_EX | LOCK_NB)) {
                fclose($rxOptLockHandle);
                $rxOptLockHandle = false;
                $rxOptBusy = true;
            }
            if (!$rxOptBusy) {
                try {
                    if (\Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_redis_on()) {
                        $rxOptToken = bin2hex(random_bytes(16));
                        $rxOptAcquired = \Faoxima\Core\Cache\RedisCache::rx_redis_set_nx($rxOptRedisKey, $rxOptToken, $rxOptRedisTtlMs);
                        if ($rxOptAcquired === true) {
                            $rxOptRedisToken = $rxOptToken;
                        } elseif ($rxOptAcquired === false) {
                            $rxOptBusy = true;
                        }
                    }
                } catch (Throwable $e) {
                }
            }
            $rxOptRelease = function () use (&$rxOptLockHandle, &$rxOptRedisToken, $rxOptRedisKey) {
                if ($rxOptRedisToken !== null) {
                    $rxOptHeldToken = $rxOptRedisToken;
                    $rxOptRedisToken = null;
                    try {
                        \Faoxima\Core\Cache\RedisCache::rx_redis_release_lock($rxOptRedisKey, $rxOptHeldToken);
                    } catch (Throwable $e) {
                    }
                }
                if ($rxOptLockHandle !== false) {
                    $rxOptHeldHandle = $rxOptLockHandle;
                    $rxOptLockHandle = false;
                    @flock($rxOptHeldHandle, LOCK_UN);
                    @fclose($rxOptHeldHandle);
                }
            };
            $rxOptRefresh = function () use (&$rxOptRedisToken, &$rxOptLastRefresh, $rxOptRedisKey, $rxOptRedisTtlMs) {
                if ($rxOptRedisToken === null || (time() - $rxOptLastRefresh) < 60) {
                    return;
                }
                $rxOptLastRefresh = time();
                try {
                    \Faoxima\Core\Cache\RedisCache::rx_redis_extend_lock($rxOptRedisKey, $rxOptRedisToken, $rxOptRedisTtlMs);
                } catch (Throwable $e) {
                }
            };
            if ($rxOptBusy) {
                $rxOptRelease();
                [$rxOptText, $rxOptKeyboard] = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_render(\Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_state());
                Editmessagetext($from_id, $message_id, $rxOptText, $rxOptKeyboard);
            } else {
                $rxOptPrevious = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_progress_read();
                if (is_array($rxOptPrevious) && in_array($rxOptPrevious['status'] ?? '', ['starting', 'running', 'cancelling'], true)) {
                    error_log('[optimizebot] superseding unfinished run ' . ($rxOptPrevious['run_id'] ?? '') . ' (' . $rxOptPrevious['status'] . ')');
                }
                \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_cancel_clear($rxOptPrevious['run_id'] ?? '');
                $rxOptProgress = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_progress_write([
                    'run_id' => bin2hex(random_bytes(8)),
                    'owner_token' => $rxOptRedisToken ?? bin2hex(random_bytes(16)),
                    'status' => 'starting',
                    'stage' => 'آماده‌سازی',
                    'stage_number' => 0,
                    'total_stages' => RX_OPT_TOTAL_STAGES,
                    'processed' => 0,
                    'deleted' => 0,
                    'marked' => 0,
                    'valid' => 0,
                    'errors' => 0,
                    'current_batch' => 0,
                    'started_at' => time(),
                    'completed_at' => null,
                    'cancel_requested_at' => null,
                    'cancel_requested_by' => null,
                    'last_error' => null,
                    'chat_id' => (string) $from_id,
                    'message_id' => (int) $message_id,
                ]);
                [$rxOptText, $rxOptKeyboard] = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_render($rxOptProgress);
                $rxOptEdit = Editmessagetext($from_id, $message_id, $rxOptText, $rxOptKeyboard);
                if (is_array($rxOptEdit) && !empty($rxOptEdit['result']['message_id'])) {
                    $rxOptProgress['message_id'] = (int) $rxOptEdit['result']['message_id'];
                }
                $rxOptFinalized = false;
                $rxOptTouched = [];
                $rxOptCancelSignal = new RuntimeException('optimizer cancelled');
                $rxOptSave = function () use (&$rxOptProgress) {
                    $rxOptProgress = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_progress_write($rxOptProgress);
                };
                $rxOptFlushCache = function () use (&$rxOptTouched) {
                    foreach (array_keys($rxOptTouched) as $rxOptTable) {
                        \Faoxima\Core\Database\SelectCache::clearSelectCache($rxOptTable);
                    }
                    $rxOptTouched = [];
                };
                $rxOptCheckpoint = function () use (&$rxOptProgress, $rxOptCancelSignal, $rxOptRefresh) {
                    $rxOptRefresh();
                    $rxOptCancel = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_cancel_get($rxOptProgress['run_id'], $rxOptProgress['owner_token']);
                    if ($rxOptCancel !== null) {
                        $rxOptProgress['cancel_requested_at'] = $rxOptCancel['requested_at'] ?? time();
                        $rxOptProgress['cancel_requested_by'] = $rxOptCancel['requested_by'] ?? null;
                        throw $rxOptCancelSignal;
                    }
                };
                $rxOptStage = function ($number, $name) use (&$rxOptProgress, $rxOptCheckpoint, $rxOptSave, $rxOptFlushCache) {
                    $rxOptFlushCache();
                    $rxOptCheckpoint();
                    $rxOptProgress['status'] = 'running';
                    $rxOptProgress['stage_number'] = $number;
                    $rxOptProgress['stage'] = $name;
                    $rxOptProgress['current_batch'] = 0;
                    $rxOptSave();
                };
                $rxOptFinish = function ($status) use (&$rxOptProgress, &$rxOptFinalized, $rxOptRelease, $rxOptFlushCache) {
                    if ($rxOptFinalized) {
                        $rxOptRelease();
                        return;
                    }
                    $rxOptFinalized = true;
                    try {
                        $rxOptFlushCache();
                    } catch (Throwable $e) {
                        error_log('[optimizebot] cache flush failed: ' . $e->getMessage());
                    }
                    $rxOptProgress['status'] = $status;
                    $rxOptProgress['completed_at'] = time();
                    try {
                        $rxOptProgress = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_progress_write($rxOptProgress);
                        \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_cancel_clear($rxOptProgress['run_id']);
                    } catch (Throwable $e) {
                        error_log('[optimizebot] final progress write failed: ' . $e->getMessage());
                    }
                    $rxOptRelease();
                    try {
                        [$rxOptFinalText, $rxOptFinalKeyboard] = \Faoxima\Admin\Telegram\Support\OptimizerFunctionsHelpers::rx_optimizer_render($rxOptProgress);
                        Editmessagetext($rxOptProgress['chat_id'], $rxOptProgress['message_id'], $rxOptFinalText, $rxOptFinalKeyboard);
                    } catch (Throwable $e) {
                        error_log('[optimizebot] final status message failed: ' . $e->getMessage());
                    }
                };
                register_shutdown_function(function () use (&$rxOptProgress, $rxOptFinish) {
                    if (($rxOptProgress['last_error'] ?? null) === null) {
                        $rxOptProgress['last_error'] = 'unexpected_termination';
                    }
                    $rxOptFinish('failed');
                });
                $rxOptOutcome = 'completed';
                try {
                    if (function_exists('set_time_limit')) {
                        @set_time_limit(0);
                    }
                    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
                    $sixtyDaysAgo = time() - (60 * 24 * 60 * 60);
                    $ninetyDaysAgo = time() - (90 * 24 * 60 * 60);

                    $rxOptStage(1, 'بررسی سرویس‌های متصل به پنل حذف‌شده');
                    $chk = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'invalidated_at'");
                    if ($chk && $chk->rowCount() !== 1) {
                        $pdo->exec("ALTER TABLE invoice ADD invalidated_at INT UNSIGNED NULL DEFAULT NULL");
                    }

                    $nowStamp = time();
                    $countorphanedpanel = 0;
                    do {
                        $rxOptCheckpoint();
                        $rxOptOrphanedBatch = \Faoxima\Admin\Reporting\Statistics::rx_stats_preserve_invoice_batch(
                            $pdo,
                            "Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                     AND Service_location IS NOT NULL AND Service_location <> ''
                     AND Service_location NOT IN (SELECT name_panel FROM marzban_panel WHERE name_panel IS NOT NULL)",
                            [],
                            "UPDATE invoice SET Status = 'Unsuccessful', invalidated_at = ?",
                            [$nowStamp]
                        );
                        $countorphanedpanel += $rxOptOrphanedBatch;
                    } while ($rxOptOrphanedBatch === 500);
                    $rxOptProgress['marked'] += $countorphanedpanel;
                    $rxOptProgress['processed'] += $countorphanedpanel;
                    if ($countorphanedpanel > 0) {
                        $rxOptTouched['invoice'] = true;
                    }

                    $countorphanedservice = 0;
                    $countunusedservice = 0;
                    $countunreachablepanel = 0;
                    $countexpiredservice = 0;
                    $rxOptStage(2, 'استعلام سرویس‌های فعال از پنل');
                    $rxOptScanBatch = 100;
                    $rxOptScanSql = "SELECT id_invoice, Service_location, username, time_sell, Status, invalidated_at FROM invoice
                 WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                 AND Service_location IS NOT NULL AND Service_location <> ''
                 AND username IS NOT NULL AND username <> ''";
                    $rxOptScanFirst = $pdo->prepare($rxOptScanSql . " ORDER BY id_invoice ASC LIMIT " . $rxOptScanBatch);
                    $rxOptScanNext = $pdo->prepare($rxOptScanSql . " AND id_invoice > ? ORDER BY id_invoice ASC LIMIT " . $rxOptScanBatch);
                    $rxOptSetInvalidated = $pdo->prepare("UPDATE invoice SET invalidated_at = ? WHERE id_invoice = ?");
                    $rxOptSetInvalidatedStatus = function ($stamp, $status, $id) use ($pdo) {
                        \Faoxima\Admin\Reporting\Statistics::rx_stats_preserve_invoice_batch($pdo, "id_invoice = ?", [$id],
                            "UPDATE invoice SET invalidated_at = ?, Status = ?", [$stamp, $status], 1);
                    };
                    $rxOptClearInvalidated = $pdo->prepare("UPDATE invoice SET invalidated_at = NULL WHERE id_invoice = ?");
                    $lastId = null;
                    do {
                        $rxOptCheckpoint();
                        if ($lastId === null) {
                            $rxOptScanFirst->execute();
                            $rxOptScanStmt = $rxOptScanFirst;
                        } else {
                            $rxOptScanNext->execute([$lastId]);
                            $rxOptScanStmt = $rxOptScanNext;
                        }
                        $liveInvoices = $rxOptScanStmt->fetchAll(PDO::FETCH_ASSOC);
                        $rxOptScanStmt->closeCursor();
                        $rxOptScanCount = count($liveInvoices);
                        $rxOptProgress['current_batch']++;
                        $rxOptBatchChanged = false;
                        $rxOptSinceCheck = 0;
                        try {
                            foreach ($liveInvoices as $liveInvoice) {
                                $lastId = $liveInvoice['id_invoice'];
                                $rxOptProgress['processed']++;
                                $rxOptScanFailed = false;
                                try {
                                    $scanResult = $ManagePanel->DataUser($liveInvoice['Service_location'], $liveInvoice['username']);
                                } catch (Throwable $e) {
                                    $rxOptScanFailed = true;
                                    $rxOptProgress['errors']++;
                                    if (empty($liveInvoice['invalidated_at'])) {
                                        $rxOptSetInvalidated->execute([$nowStamp, $liveInvoice['id_invoice']]);
                                        $rxOptBatchChanged = true;
                                        $countunreachablepanel++;
                                        $rxOptProgress['marked']++;
                                    }
                                }
                                if (!$rxOptScanFailed) {
                                    $rxOptMarkedNow = true;
                                    if (isset($scanResult['msg']) && $scanResult['msg'] == "User not found") {
                                        $rxOptSetInvalidatedStatus($nowStamp, 'disabledn', $liveInvoice['id_invoice']);
                                        $countorphanedservice++;
                                    } elseif (isset($scanResult['status']) && $scanResult['status'] == "Unsuccessful" && isset($scanResult['msg']) && $scanResult['msg'] == "Panel Not Found") {
                                        $rxOptSetInvalidatedStatus($nowStamp, 'Unsuccessful', $liveInvoice['id_invoice']);
                                        $countorphanedservice++;
                                    } elseif ((int)($scanResult['used_traffic'] ?? 0) === 0) {
                                        $purchasedAt = is_numeric($liveInvoice['time_sell']) ? (int)$liveInvoice['time_sell'] : null;
                                        if ($purchasedAt !== null && $purchasedAt < $thirtyDaysAgo) {
                                            $rxOptSetInvalidatedStatus($nowStamp, 'unusedservice', $liveInvoice['id_invoice']);
                                            $countunusedservice++;
                                        } else {
                                            $rxOptMarkedNow = false;
                                        }
                                    } elseif (in_array($liveInvoice['Status'], ['end_of_time', 'end_of_volume'], true)) {
                                        if (empty($liveInvoice['invalidated_at'])) {
                                            $rxOptSetInvalidated->execute([$nowStamp, $liveInvoice['id_invoice']]);
                                            $countexpiredservice++;
                                        } else {
                                            $rxOptMarkedNow = false;
                                        }
                                    } else {
                                        $rxOptMarkedNow = false;
                                        if (!empty($liveInvoice['invalidated_at'])) {
                                            $rxOptClearInvalidated->execute([$liveInvoice['id_invoice']]);
                                            $rxOptBatchChanged = true;
                                        }
                                    }
                                    if ($rxOptMarkedNow) {
                                        $rxOptBatchChanged = true;
                                        $rxOptProgress['marked']++;
                                    } else {
                                        $rxOptProgress['valid']++;
                                    }
                                }
                                if (++$rxOptSinceCheck >= 10) {
                                    $rxOptSinceCheck = 0;
                                    $rxOptCheckpoint();
                                }
                            }
                        } finally {
                            if ($rxOptBatchChanged) {
                                \Faoxima\Core\Database\SelectCache::clearSelectCache('invoice');
                            }
                        }
                        unset($liveInvoices, $liveInvoice, $scanResult);
                        $rxOptSave();
                    } while ($rxOptScanCount === $rxOptScanBatch);

                    $rxOptDeleteBatch = 500;
                    $rxOptCachedTables = ['invoice' => true, 'Payment_report' => true, 'DiscountSell' => true, 'Discount' => true, 'cancel_service' => true, 'Requestagent' => true, 'support_message' => true];
                    $rxOptDelete = function ($table, $sql, array $params) use ($pdo, &$rxOptProgress, &$rxOptTouched, $rxOptCachedTables, $rxOptCheckpoint, $rxOptSave, $rxOptDeleteBatch) {
                        $rxOptDelStmt = $pdo->prepare($sql . " LIMIT " . $rxOptDeleteBatch);
                        $rxOptDeleted = 0;
                        do {
                            $rxOptCheckpoint();
                            if ($table === 'invoice') {
                                $rxOptWhere = preg_split('/\s+WHERE\s+/i', $sql, 2)[1];
                                $rxOptAffected = \Faoxima\Admin\Reporting\Statistics::rx_stats_preserve_invoice_batch($pdo, $rxOptWhere, $params,
                                    'DELETE FROM invoice', [], $rxOptDeleteBatch);
                            } else {
                                $rxOptDelStmt->execute($params);
                                $rxOptAffected = $rxOptDelStmt->rowCount();
                            }
                            $rxOptDeleted += $rxOptAffected;
                            $rxOptProgress['deleted'] += $rxOptAffected;
                            $rxOptProgress['processed'] += $rxOptAffected;
                            $rxOptProgress['current_batch']++;
                            if ($rxOptAffected > 0 && isset($rxOptCachedTables[$table])) {
                                $rxOptTouched[$table] = true;
                            }
                            $rxOptSave();
                        } while ($rxOptAffected >= $rxOptDeleteBatch);
                        return $rxOptDeleted;
                    };

                    $rxOptStage(3, 'حذف سفارش‌ها و سرویس‌های قدیمی');
                    $countunpiadorder = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'unpaid' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countdisableorder = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'disabled' AND name_product != 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countremoveadminorder = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'removebyadmin' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countremoveadminorder += $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'removedbyadmin' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countdisableordtester = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'disabled' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countremovedservices = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'removeTime' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countremovedservices += $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'removevolume' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countremovedservices += $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'removebyuser' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countoldunpaid = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'unpaid' AND name_product = 'سرویس تست' AND CAST(time_sell AS UNSIGNED) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countfailedorder = $rxOptDelete('invoice', "DELETE FROM invoice WHERE (Status = 'disabledn' OR Status = 'Unsuccessful') AND COALESCE(invalidated_at, CAST(time_sell AS UNSIGNED)) < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countunusedexpired = $rxOptDelete('invoice', "DELETE FROM invoice WHERE Status = 'unusedservice' AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff", [':cutoff' => $thirtyDaysAgo]);
                    $countstalelive = $rxOptDelete(
                        'invoice',
                        "DELETE FROM invoice
                 WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')
                 AND invalidated_at IS NOT NULL AND invalidated_at < :cutoff",
                        [':cutoff' => $thirtyDaysAgo]
                    );

                    $rxOptStage(4, 'حذف گزارش‌های پرداخت منقضی/رد شده');
                    $paymentCutoff = date('Y/m/d H:i:s', $thirtyDaysAgo);
                    $countpayexpired = $rxOptDelete('Payment_report', "DELETE FROM Payment_report WHERE payment_Status IN ('expire','reject','cancelled') AND time < :cutoff", [':cutoff' => $paymentCutoff]);

                    $rxOptStage(5, 'حذف تراکنش‌های قدیمی کیف پول');
                    $countledgerexpired = $rxOptDelete('wallet_ledger', "DELETE FROM wallet_ledger WHERE created_at < FROM_UNIXTIME(:cutoff)", [':cutoff' => $thirtyDaysAgo]);

                    $rxOptStage(6, 'حذف کدهای تخفیف منقضی‌شده');
                    $countdiscountexpired = 0;
                    $chk = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'status'");
                    if ($chk && $chk->rowCount() === 1) {
                        $countdiscountexpired += $rxOptDelete('DiscountSell', "DELETE FROM DiscountSell WHERE status = 'expired' AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff", [':cutoff' => $ninetyDaysAgo]);
                    }
                    $chk = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'expire_at'");
                    if ($chk && $chk->rowCount() === 1) {
                        $countdiscountexpired += $rxOptDelete('Discount', "DELETE FROM Discount WHERE status = 'expired' AND expire_at IS NOT NULL AND expire_at <> '' AND CAST(expire_at AS UNSIGNED) < :cutoff", [':cutoff' => $ninetyDaysAgo]);
                    }

                    $rxOptStage(7, 'حذف درخواست‌های بررسی‌شده');
                    $countcancelrequests = 0;
                    $chk = $pdo->query("SHOW COLUMNS FROM cancel_service LIKE 'resolved_at'");
                    if ($chk && $chk->rowCount() === 1) {
                        $countcancelrequests = $rxOptDelete('cancel_service', "DELETE FROM cancel_service WHERE status IN ('accept','reject') AND resolved_at IS NOT NULL AND resolved_at <> '' AND CAST(resolved_at AS UNSIGNED) < :cutoff", [':cutoff' => $sixtyDaysAgo]);
                    }
                    $countagentrequests = $rxOptDelete('Requestagent', "DELETE FROM Requestagent WHERE status IN ('accept','reject') AND time IS NOT NULL AND time <> '' AND CAST(time AS UNSIGNED) < :cutoff", [':cutoff' => $sixtyDaysAgo]);

                    $rxOptStage(8, 'حذف کدهای هدیه مصرف‌شده');
                    $countgiftcodes = $rxOptDelete('Giftcodeconsumed', "DELETE FROM Giftcodeconsumed WHERE consumed_at IS NOT NULL AND consumed_at <> '' AND CAST(consumed_at AS UNSIGNED) < :cutoff", [':cutoff' => $ninetyDaysAgo]);

                    $rxOptStage(9, 'حذف لاگ‌های قدیمی کریپتو');
                    $countcryptologs = $rxOptDelete('crypto_verified_hashes', "DELETE FROM crypto_verified_hashes WHERE verified_at < FROM_UNIXTIME(:cutoff)", [':cutoff' => $ninetyDaysAgo]);
                    $countcryptologs += $rxOptDelete('crypto_sender_locks', "DELETE FROM crypto_sender_locks WHERE COALESCE(last_used_at, first_seen_at) < FROM_UNIXTIME(:cutoff)", [':cutoff' => $ninetyDaysAgo]);

                    $rxOptStage(10, 'حذف تیکت‌های قدیمی');
                    $ticketCutoff = date('Y/m/d H:i:s', $thirtyDaysAgo);
                    $rxOptTicketBatch = 100;
                    $rxOptTicketFirst = $pdo->prepare("SELECT Tracking FROM support_message GROUP BY Tracking HAVING MIN(time) < ? ORDER BY Tracking ASC LIMIT " . $rxOptTicketBatch);
                    $rxOptTicketNext = $pdo->prepare("SELECT Tracking FROM support_message WHERE Tracking > ? GROUP BY Tracking HAVING MIN(time) < ? ORDER BY Tracking ASC LIMIT " . $rxOptTicketBatch);
                    $countoldtickets = 0;
                    $delReact = null;
                    $delMsg = null;
                    $rxOptLastTracking = null;
                    do {
                        $rxOptCheckpoint();
                        if ($rxOptLastTracking === null) {
                            $rxOptTicketFirst->execute([$ticketCutoff]);
                            $rxOptTicketStmt = $rxOptTicketFirst;
                        } else {
                            $rxOptTicketNext->execute([$rxOptLastTracking, $ticketCutoff]);
                            $rxOptTicketStmt = $rxOptTicketNext;
                        }
                        $oldTrackings = $rxOptTicketStmt->fetchAll(PDO::FETCH_COLUMN);
                        $rxOptTicketStmt->closeCursor();
                        $rxOptTicketCount = count($oldTrackings);
                        if ($rxOptTicketCount > 0 && $delReact === null) {
                            $delReact = $pdo->prepare(
                                "DELETE r FROM support_message_reaction r
                         INNER JOIN support_message m ON m.id = r.message_id
                         WHERE m.Tracking = :t"
                            );
                            $delMsg = $pdo->prepare("DELETE FROM support_message WHERE Tracking = :t");
                        }
                        foreach ($oldTrackings as $tracking) {
                            $delReact->execute([':t' => $tracking]);
                            $delMsg->execute([':t' => $tracking]);
                            $countoldtickets++;
                            $rxOptProgress['deleted']++;
                            $rxOptProgress['processed']++;
                            $rxOptLastTracking = $tracking;
                            $rxOptTouched['support_message'] = true;
                        }
                        unset($oldTrackings);
                        $rxOptProgress['current_batch']++;
                        $rxOptSave();
                    } while ($rxOptTicketCount === $rxOptTicketBatch);

                    $optimizebot = "✅ بهینه سازی با موفقیت انجام شد

📊 خلاصه عملیات:
✅ {$countunpiadorder} سفارش پرداخت نشده (بیش از ۳۰ روز) حذف گردید
✅ {$countdisableorder} سفارش غیرفعال (بیش از ۳۰ روز) حذف گردید
✅ {$countremoveadminorder} سفارش حذف شده توسط ادمین (بیش از ۳۰ روز) پاک گردید
✅ {$countdisableordtester} سرویس تست غیرفعال (بیش از ۳۰ روز) حذف گردید
✅ {$countoldunpaid} فاکتور تست پرداخت نشده (بیش از ۳۰ روز) پاک گردید
✅ {$countremovedservices} سفارشی که زمان یا حجم یا توسط کاربر حذف شده (بیش از ۳۰ روز) پاک گردید
✅ {$countfailedorder} سفارش ناموفق/نامعتبر (بیش از ۳۰ روز) پاک گردید
✅ {$countpayexpired} گزارش تراکنش منقضی/رد شده (بیش از ۳۰ روز) پاک گردید
✅ {$countledgerexpired} تراکنش کیف پول قدیمی‌تر از ۳۰ روز پاک گردید
✅ {$countdiscountexpired} کد تخفیف منقضی‌شده (بیش از ۹۰ روز) پاک گردید
✅ {$countcancelrequests} درخواست کنسلی بررسی‌شده (بیش از ۶۰ روز) پاک گردید
✅ {$countagentrequests} درخواست نمایندگی بررسی‌شده (بیش از ۶۰ روز) پاک گردید
✅ {$countgiftcodes} کد هدیه مصرف‌شده (بیش از ۹۰ روز) پاک گردید
✅ {$countcryptologs} لاگ کریپتوی قدیمی‌تر از ۹۰ روز پاک گردید
✅ {$countoldtickets} تیکت قدیمی‌تر از ۳۰ روز پاک گردید
✅ {$countorphanedpanel} سرویس متصل به پنل حذف‌شده شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countorphanedservice} سرویس نامعتبر (کاربر یافت نشد روی پنل) شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countunusedservice} سرویس بدون مصرف (۰ مصرف، بیش از ۳۰ روز از خرید) شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countunusedexpired} سرویس بدون مصرف که مهلتش تمام شده حذف گردید
✅ {$countunreachablepanel} سرویس با پنل غیرقابل‌دسترس شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countexpiredservice} سرویس با حجم/زمان تمام‌شده که تمدید نشده شناسایی و برای حذف (پس از ۳۰ روز) علامت‌گذاری شد
✅ {$countstalelive} سرویس علامت‌خورده (پنل غیرقابل‌دسترس یا حجم/زمان تمام‌شده) که مهلتش تمام شده حذف گردید";

                    $time = time();
                    $logss = "optimize_{$countunpiadorder}_{$countdisableorder}_{$countremoveadminorder}_{$countdisableordtester}_{$countdiscountexpired}_{$countcancelrequests}_{$countagentrequests}_{$countgiftcodes}_{$countcryptologs}_{$countorphanedpanel}_{$countorphanedservice}_{$countunusedservice}_{$countunusedexpired}_{$countunreachablepanel}_{$countexpiredservice}_{$countstalelive}_$time";
                    file_put_contents('log.txt', "\n" . $logss, FILE_APPEND);
                    $rxOptProgress['details'] = strstr($optimizebot, '📊') ?: $optimizebot;
                } catch (Throwable $e) {
                    if ($e === $rxOptCancelSignal) {
                        $rxOptOutcome = 'cancelled';
                    } else {
                        $rxOptOutcome = 'failed';
                        $rxOptProgress['errors']++;
                        $rxOptProgress['last_error'] = $e instanceof PDOException ? 'database_error' : 'internal_error';
                        error_log('[optimizebot] run ' . $rxOptProgress['run_id'] . ' failed at stage ' . $rxOptProgress['stage_number'] . ' batch ' . $rxOptProgress['current_batch'] . ': ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    }
                } finally {
                    $rxOptFinish($rxOptOutcome);
                }
            }
            break;
        }

        return true;
    }
}
