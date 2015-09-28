<?php

class Message {

    /**
     * Parsing update telegram object.
     *
     * @param string     $input         Raw input data.
     *
     */
    public function __construct($input) {
        
        $this->update = json_decode($input, true);
        
    }
    
    public function getText() {
        return print_r($this->update, true);
    }
    
    private $update;
    private $sender;
    private $recepient;
}

class BotList {
    
}