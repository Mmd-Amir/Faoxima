<?php

namespace Faoxima\Admin\Keyboard;

final class InvoicePagesHelpers
{
    public static function rxAdminInvoicePageResult($connect, $id_user, $start_index, $items_per_page)
    {
        $_s = (int)$start_index; $_p = (int)$items_per_page;
        $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? LIMIT ?, ?");
        $_stmt->bind_param("sii", $id_user, $_s, $_p);
        $_stmt->execute();
        $result = $_stmt->get_result();
        $_stmt->close();
        return $result;
    }

    public static function rxAdminInvoicePageKeyboard($result, $id_user, $textbotlang)
    {
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => "عملیات", 'callback_data' => "action"],
            ['text' => "وضعیت سرویس", 'callback_data' => "Status"],
            ['text' => "نام کاربری", 'callback_data' => "username"],
        ];
        while ($row = mysqli_fetch_assoc($result)) {
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "مشاهده‌اطلاعات",
                    'callback_data' => "manageinvoice_" . $row['id_invoice']
                ],
                [
                    'text' => $row['Status'],
                    'callback_data' => "username"
                ],
                [
                    'text' => $row['username'],
                    'callback_data' => $row['username']
                ],
            ];
        }
        $pagination_buttons = [
            [
                'text' => $textbotlang['users']['page']['next'],
                'callback_data' => 'next_pageinvoice_' . $id_user
            ],
            [
                'text' => $textbotlang['users']['page']['previous'],
                'callback_data' => 'previous_pageinvoice_' . $id_user
            ]
        ];
        $keyboardlists['inline_keyboard'][] = $pagination_buttons;
        return json_encode($keyboardlists);
    }
}
