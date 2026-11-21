<?php

namespace Faoxima\Telegram\Navigation;

final class NavigationLogger
{
    protected static $pressed = null;

    protected static function dir()
    {
        $dir = RX_NAV_LOG_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    protected static function clean($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = (string) $value;
        $value = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $value);
        return trim($value);
    }

    protected static function payload()
    {
        if (isset($GLOBALS['update']) && is_array($GLOBALS['update'])) {
            return $GLOBALS['update'];
        }
        return [];
    }

    protected static function resolveUserId($from_id = null)
    {
        if ($from_id !== null && (string) $from_id !== '') {
            return (string) $from_id;
        }
        if (isset($GLOBALS['from_id']) && (string) $GLOBALS['from_id'] !== '') {
            return (string) $GLOBALS['from_id'];
        }
        $update = self::payload();
        if (isset($update['callback_query']['from']['id'])) {
            return (string) $update['callback_query']['from']['id'];
        }
        if (isset($update['message']['from']['id'])) {
            return (string) $update['message']['from']['id'];
        }
        return '';
    }

    protected static function detect()
    {
        $update = self::payload();
        $info = [
            'type'    => 'Message',
            'button'  => '',
            'chat_id' => '',
            'msg_id'  => '',
        ];

        if (isset($update['callback_query'])) {
            $cq = $update['callback_query'];
            $info['type']    = 'Callback';
            $info['button']  = isset($cq['data']) ? self::clean($cq['data']) : '';
            $info['chat_id'] = isset($cq['message']['chat']['id']) ? self::clean($cq['message']['chat']['id']) : '';
            $info['msg_id']  = isset($cq['message']['message_id']) ? self::clean($cq['message']['message_id']) : '';
            if ($info['button'] === '' && isset($GLOBALS['datain'])) {
                $info['button'] = self::clean($GLOBALS['datain']);
            }
        } elseif (isset($update['message'])) {
            $msg = $update['message'];
            $info['type']    = 'Message';
            $info['button']  = isset($msg['text']) ? self::clean($msg['text']) : '';
            $info['chat_id'] = isset($msg['chat']['id']) ? self::clean($msg['chat']['id']) : '';
            $info['msg_id']  = isset($msg['message_id']) ? self::clean($msg['message_id']) : '';
        } else {
            if (isset($GLOBALS['datain']) && self::clean($GLOBALS['datain']) !== '') {
                $info['type']   = 'Callback';
                $info['button'] = self::clean($GLOBALS['datain']);
            } elseif (isset($GLOBALS['text'])) {
                $info['type']   = 'Message';
                $info['button'] = self::clean($GLOBALS['text']);
            }
        }

        if ($info['chat_id'] === '') {
            $info['chat_id'] = self::resolveUserId();
        }
        if ($info['msg_id'] === '' && isset($GLOBALS['message_id'])) {
            $info['msg_id'] = self::clean($GLOBALS['message_id']);
        }

        return $info;
    }

    protected static function write($line, $record)
    {
        return;
        $dir = self::dir();
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'nav_debug.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents(
                $dir . DIRECTORY_SEPARATOR . 'nav_debug.jsonl',
                $json . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    public static function button($from_id = null, $context = [])
    {
        $info = self::detect();
        $userId = self::resolveUserId($from_id);

        if (isset($context['type']) && $context['type'] !== '') {
            $info['type'] = self::clean($context['type']);
        }
        if (isset($context['button']) && $context['button'] !== '') {
            $info['button'] = self::clean($context['button']);
        }
        if (isset($context['chat_id']) && $context['chat_id'] !== '') {
            $info['chat_id'] = self::clean($context['chat_id']);
        }
        if (isset($context['msg_id']) && $context['msg_id'] !== '') {
            $info['msg_id'] = self::clean($context['msg_id']);
        }

        $step = '';
        if (isset($context['step'])) {
            $step = self::clean($context['step']);
        } elseif (isset($GLOBALS['user']['step'])) {
            $step = self::clean($GLOBALS['user']['step']);
        }

        self::$pressed = [
            'user_id' => $userId,
            'type'    => $info['type'],
            'button'  => $info['button'],
            'chat_id' => $info['chat_id'],
            'msg_id'  => $info['msg_id'],
        ];

        $ts = date('Y-m-d H:i:s');
        $line = '[' . $ts . '] [' . $userId . '] [Type: ' . $info['type'] . ']'
            . ' | Button: ' . ($info['button'] !== '' ? $info['button'] : '-')
            . ' | From Step: ' . ($step !== '' ? $step : '-')
            . ' -> To Step: ' . ($step !== '' ? $step : '-')
            . ' | Chat ID: ' . ($info['chat_id'] !== '' ? $info['chat_id'] : '-')
            . ' | Msg ID: ' . ($info['msg_id'] !== '' ? $info['msg_id'] : '-');

        $record = [
            'timestamp'   => $ts,
            'unixtime'    => time(),
            'phase'       => 'press',
            'user_id'     => $userId,
            'update_type' => $info['type'],
            'button'      => $info['button'],
            'from_step'   => $step,
            'to_step'     => $step,
            'chat_id'     => $info['chat_id'],
            'msg_id'      => $info['msg_id'],
        ];

        self::write($line, $record);
    }

    public static function transition($from_id, $oldStep, $newStep, $context = [])
    {
        $userId = self::resolveUserId($from_id);
        $oldStep = self::clean($oldStep);
        $newStep = self::clean($newStep);

        $pressed = is_array(self::$pressed) ? self::$pressed : [];
        $type    = isset($pressed['user_id']) && $pressed['user_id'] === $userId && isset($pressed['type'])
            ? $pressed['type']
            : self::detect()['type'];
        $button  = isset($pressed['user_id']) && $pressed['user_id'] === $userId && isset($pressed['button'])
            ? $pressed['button']
            : '';
        $chatId  = isset($pressed['user_id']) && $pressed['user_id'] === $userId && isset($pressed['chat_id']) && $pressed['chat_id'] !== ''
            ? $pressed['chat_id']
            : self::detect()['chat_id'];
        $msgId   = isset($pressed['user_id']) && $pressed['user_id'] === $userId && isset($pressed['msg_id'])
            ? $pressed['msg_id']
            : '';

        if (isset($context['type']) && $context['type'] !== '') {
            $type = self::clean($context['type']);
        }
        if (isset($context['button']) && $context['button'] !== '') {
            $button = self::clean($context['button']);
        }
        if (isset($context['chat_id']) && $context['chat_id'] !== '') {
            $chatId = self::clean($context['chat_id']);
        }
        if (isset($context['msg_id']) && $context['msg_id'] !== '') {
            $msgId = self::clean($context['msg_id']);
        }

        $ts = date('Y-m-d H:i:s');
        $line = '[' . $ts . '] [' . $userId . '] [Type: ' . $type . ']'
            . ' | Button: ' . ($button !== '' ? $button : '-')
            . ' | From Step: ' . ($oldStep !== '' ? $oldStep : '-')
            . ' -> To Step: ' . ($newStep !== '' ? $newStep : '-')
            . ' | Chat ID: ' . ($chatId !== '' ? $chatId : '-')
            . ' | Msg ID: ' . ($msgId !== '' ? $msgId : '-');

        $record = [
            'timestamp'   => $ts,
            'unixtime'    => time(),
            'phase'       => 'transition',
            'user_id'     => $userId,
            'update_type' => $type,
            'button'      => $button,
            'from_step'   => $oldStep,
            'to_step'     => $newStep,
            'chat_id'     => $chatId,
            'msg_id'      => $msgId,
        ];

        self::write($line, $record);
    }
}
