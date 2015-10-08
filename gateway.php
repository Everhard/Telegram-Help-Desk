<?php

require_once('config.php');
require_once('libs/telegram.php');
require_once('libs/helpdesk.php');

if ($input = file_get_contents('php://input')) {
    
    /*
     * Message from user (it can be client, manager, admin).
     */
    $message = new Message($input);
    
    /*
     * Current bot (it can be main admin bot or manager's bot):
     */
    $bot = new Bot();
    
    /*
     * User that have sent message.
     */
    $user = new User(
            $message->getSenderId(),
            $message->getSenderFirstName(), 
            $message->getSenderLastName()
    );

    if ($bot->isMain()) {
        
        if ($user->isAdmin()) {
            require_once('control-roles/administrator.php');
        }
        
        elseif ($user->isManager()) {
            require_once('control-roles/manager.php');
        }
        
        elseif ($user->isClient()) {
            require_once('control-roles/client.php');
        }
        
    }
    
    else {
        if ($user->isManager($bot)) {
            require_once('helpdesk-roles/manager.php');
        }
        
        if ($user->isClient($bot)) {
            require_once('helpdesk-roles/client.php');
        }
    }
}