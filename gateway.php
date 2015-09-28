<?php

require_once('libs/telegram.php');
require_once('libs/helpdesk.php');

if ($input = file_get_contents('php://input')) {
    
    $message = new Message($input);
    
    file_put_contents('input.txt', $message->getText(), FILE_APPEND);
    
}




//$telegram_bot = new TelegramBot();
//
//if ($telegram_bot->getWebhookUpdates()) {
//    
//}