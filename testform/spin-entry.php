<?php

/**
 * Created by PhpStorm.
 * User: steve
 * Date: 5/16/2016
 * Time: 7:38 PM
 */
class SpinEntry
{
    private $saltValue = 'STEVEMUCHOW1'; // hack hack hack. This should really be on the server side.
    protected $passwordHashMD5 = 'password';
    protected $coinsWon = 0;
    protected $coinsBet = 0;
    protected $playerId = 1;
    public $isValid = true;

    public function getPwd() {
        return $this->passwordHashMD5;
    }

    public function testRandomize() {
        $this->coinsBet = mt_rand(1, 4);
        $this->coinsWon = 0;
        $win = mt_rand(1,20);
        if ($win==1) {
            $this->coinsWon = mt_rand($this->coinsBet, $this->coinsBet*10);
        }
    }

    public function getParameters() {
        return "hash,$this->passwordHashMD5,won,$this->coinsWon,bet,$this->coinsBet,id,$this->playerId";
    }
    public function getEncryptedParameters($password) {
        if ($this->passwordHashMD5=='password') {
            $this->passwordHashMD5 = md5($password . $this->saltValue);
        }
        $str = "hash,$this->passwordHashMD5,won,$this->coinsWon,bet,$this->coinsBet,id,$this->playerId";
        echo " get: $str<br />\n";
        return $str;
    }
}