<?php

/*
 * Write to history:
 */
$history = new History($bot);
$history->writeRequest($user);

/*
 * Send message to manager:
 */
$keyboard = $history->getArrayOfNames();

if ($message->getSenderText() == "/start") {
    $message_to_manager = "К Вам подключился клиент, можете начать с ним диалог.";
}

else {
    $message_to_manager = "/".$user->getCommandFullName()." : ".$message->getSenderText();
}

$manager = $bot->getManager();
$manager->sendMessage($message_to_manager, $bot, $keyboard);