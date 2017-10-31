<?php

declare(strict_types=1);

namespace Frago9876543210\Fly;


use pocketmine\network\mcpe\protocol\SetTitlePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use proxy\plugin\ProxyPluginBase;
use proxy\Proxy;

class FlyPlugin extends ProxyPluginBase
{
    public function onInit(Proxy $proxy): void
    {
    }

    public function onDataPacketFromClient(DataPacket $packet): bool
    {
        return true;
    }

    public function onDataPacketFromServer(DataPacket $packet): bool
    {
        	$pk = new SetTitlePacket();
			$pk->type = SetTitlePacket::TITLE_TYPE_TIMES;
			$pk->text = "";
			$pk->fadeInTime = 5;
			$pk->fadeOutTime = 5;
			$pk->stayTime = 20 * $time;
         $this->getProxy()->writeDataPacket($pk, $this->getProxy()->clientHost, $this->getProxy()->clientPort);
        $pk = new SetTitlePacket();
			$pk->type = SetTitlePacket::TITLE_TYPE_TITLE;
			$pk->text = "Привет";
         $this->getProxy()->writeDataPacket($pk, $this->getProxy()->clientHost, $this->getProxy()->clientPort);
        return true;
    }

    public function onRakNetPacketFromClient(string $buffer): bool
    {
        return true;
    }

    public function onRakNetPacketFromServer(string $buffer): bool
    {
        return true;
    }
}
