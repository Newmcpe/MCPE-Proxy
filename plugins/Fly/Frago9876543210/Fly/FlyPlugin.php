<?php
declare(strict_types=1);
namespace Frago9876543210\Fly;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
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
        if ($packet instanceof AdventureSettingsPacket) {
            echo "AdventureSettingsPacket listened";
           		$pk = new AdventureSettingsPacket();
		$pk->setFlag(AdventureSettingsPacket::WORLD_IMMUTABLE, false);
		$pk->setFlag(AdventureSettingsPacket::NO_PVP, false);
		$pk->setFlag(AdventureSettingsPacket::AUTO_JUMP, false);
		$pk->setFlag(AdventureSettingsPacket::ALLOW_FLIGHT, true);
		$pk->setFlag(AdventureSettingsPacket::NO_CLIP, false);
		$pk->setFlag(AdventureSettingsPacket::FLYING, true);
		$pk->entityUniqueId = $packet->entityUniqueId;
            $this->getProxy()->writeDataPacket($pk, $this->getProxy()->clientHost, $this->getProxy()->clientPort);
        }
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
