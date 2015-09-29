<?php

require_once('libs/telegram.php');
require_once('libs/helpdesk.php');

$message = [
    "first-name" => "Maria",
    "last-name" => "Prototopova",
    "user-telegram-id" => "325"
];

if ($input = file_get_contents('php://input') || TRUE) {
    
    #$message = new Message($input);
    
    $bot = new Bot();
    
    $user = new User([
        "first-name" => $message["first-name"],
        "last-name" => $message["last-name"],
        "user-telegram-id" => $message["user-telegram-id"],
    ]);
    
    /*
     * Administrator:
     */
    if ($user->isAdmin()) {
        echo "Admin";
    }
    
    /*
     * Manager:
     */
    if ($user->isManager($bot)) {
        echo "Manager";
    }
    
    /*
     * Client:
     */
    if ($user->isClient($bot)) {
        
        $manager = $bot->getManager();
        $manager->sendMessage("Hello!", $bot);
        
    }
    
    #file_put_contents('input.txt', "f", FILE_APPEND);
    
}




//$telegram_bot = new TelegramBot();
//
//if ($telegram_bot->getWebhookUpdates()) {
//    
//}