<?php

namespace Faoxima\Admin\Telegram\Handlers;

final class BroadcastQueueHelpers
{
    public static function nm_writeBroadcastQueueFromJson($jsonStr) {
            $arr = is_string($jsonStr) ? json_decode($jsonStr) : $jsonStr;
            if (!is_array($arr)) {
                return 0;
            }
            $tmp = "cronbot/users.txt.new";
            $fh  = @fopen($tmp, 'w');
            if (!$fh) {
                return 0;
            }
            $count = 0;
            foreach ($arr as $row) {
                $id = null;
                if (is_object($row) && isset($row->id))     $id = $row->id;
                elseif (is_array($row) && isset($row['id']))$id = $row['id'];
                elseif (is_scalar($row))                    $id = $row;
                if ($id !== null && $id !== '' && is_numeric($id)) {
                    fwrite($fh, ((string) $id) . "\n");
                    $count++;
                }
            }
            fclose($fh);
            @unlink('cronbot/users.json');
            @unlink('cronbot/users.txt');
            @rename($tmp, 'cronbot/users.txt');

            $arr = null;
            unset($arr);
            return $count;
        }
}
