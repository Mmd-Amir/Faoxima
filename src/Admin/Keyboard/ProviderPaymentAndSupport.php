<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $rxAdminPanelBtn, $textbotlang.
 * Inherited or exported by reference: $button, $configedit, $departemanslist, $iranpaykeyboard, $optionathmarzban, $optionathx_ui, $result, $row, $stmt, $supportcenter, $table_exists, $tronnowpayments.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderPaymentAndSupport
{
    public static function handle(
        &$button,
        &$configedit,
        &$departemanslist,
        &$iranpaykeyboard,
        &$optionathmarzban,
        &$optionathx_ui,
        $pdo,
        &$result,
        &$row,
        $rxAdminPanelBtn,
        &$stmt,
        &$supportcenter,
        &$table_exists,
        $textbotlang,
        &$tronnowpayments
    ) {
        $tronnowpayments = json_encode([
            'keyboard' => [
                [['text' => "🏷️ نام نمایشی درگاه رمز ارز آفلاین"]],
                [['text' => "⬇️ کف رمزارز آفلاین"],['text' => "⬆️ سقف رمزارز آفلاین"]],
                [['text' => "📚 آموزش  ارزی افلاین"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $optionathmarzban = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athmarzban'), $rxAdminPanelBtn("🖥 مدیریت نود ها", 'admin_panel_athmarzban')],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]));
        $optionathx_ui = \Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::rx_finalizeInlineAdminKb(json_encode([
            'keyboard' => [
                [$rxAdminPanelBtn("🔧 کانفیگ دستی", 'admin_panel_athx_ui')],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]));
        $configedit = json_encode([
            'keyboard' => [
                [['text' => "مخشصات کانفیگ"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $iranpaykeyboard = json_encode([
            'keyboard' => [
                [['text' => "api  درگاه ارزی ریالی"]],
                [['text' => "🗂 نام درگاه ریالی سوم"]],
                [['text' => "⬇️ کف ریالی سوم"],['text' => "⬆️ سقف ریالی سوم"]],
                [['text' => "💰 کش بک ریالی سوم"]],
                [['text' => "📚 آموزش ریالی سوم"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $supportcenter = json_encode([
            'keyboard' => [
                [['text' => "👤 تنظیم آیدی پشتیبانی"]],
                [['text' => "🔼 اضافه کردن دپارتمان"],['text' => "🔽 حذف کردن دپارتمان"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        $departeman = [];

        $departemans = [
            'keyboard' => [],
            'resize_keyboard' => true,
        ];

        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT * FROM departman");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $departeman[] = [$row['name_departman']];
            }
            foreach ($departeman as $button) {
                $departemans['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
        }

        $departemans['keyboard'][] = [
            ['text' => $textbotlang['Admin']['backadmin']],
            ['text' => $textbotlang['Admin']['backmenu']]
        ];

        $departemanslist = json_encode($departemans);

        return 1;
    }
}
