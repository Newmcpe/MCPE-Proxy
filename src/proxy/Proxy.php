<?php

declare(strict_types=1);

namespace proxy;


use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\I;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\utils\TextFormat;
use proxy\plugin\ProxyPluginBase;
use raklib\protocol\DATA_PACKET_4;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\OpenConnectionRequest1;
use raklib\protocol\UnconnectedPing;
use raklib\protocol\UnconnectedPong;
use raklib\RakLib;

class Proxy
{
    /** @var Proxy */
    public static $instance = null;
    /** @var  string */
    public $serverHost;
    /** @var  int */
    public $serverPort;
    /** @var  string */
    public $clientHost;
    /** @var  int */
    public $clientPort;
    /** @var resource */
    private $socket;
    /** @var  ProxyPluginBase */
    private $plugins;

    /**
     * Proxy constructor.
     * @param string $serverHost
     * @param int    $serverPort
     * @param int    $bindPort
     * @param string $directory
     */
    public function __construct(string $serverHost, int $serverPort = 19132, int $bindPort = 19132, string $directory)
    {
        self::$instance = $this;

        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (@socket_bind($this->socket, "0.0.0.0", $bindPort) === true) {
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 1024 * 1024 * 8);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1024 * 1024 * 8);
        } else {
            echo "\e[38;5;203m**** FAILED TO BIND TO " . "0.0.0.0" . ":" . $bindPort . "!" . PHP_EOL;
            echo "Perhaps a somewhere is already running on that port?\e[m" . PHP_EOL;
            exit(1);
        }
        socket_set_nonblock($this->socket);

        PacketPool::init();
        $this->serverHost = gethostbyname($serverHost);
        $this->serverPort = $serverPort;
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, 1024 * 1024 * 8);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1024 * 1024 * 8);

        echo str_repeat("-", 54) . PHP_EOL . "
        \e[38;5;87m _____                     
        \e[38;5;87m|  __ \                    
        \e[38;5;87m| |__) | __ _____  ___   _ 
        \e[38;5;87m|  ___/ '__/ _ \ \/ / | | |
        \e[38;5;87m| |   | | | (_) >  <| |_| |
        \e[38;5;87m|_|   |_|  \___/_/\_\ __, |
        \e[38;5;87m                      __/ |
        \e[38;5;87m                     |___/ 
        \n\e[38;5;83mgithub.com/Frago9876543210/MCPE-Proxy\e[m\n\n" . str_repeat("-", 54) . PHP_EOL;

        echo "\e[38;5;227mWaiting for ping from the client...\e[m" . PHP_EOL;
        while (true) {
            $len = socket_recvfrom($this->socket, $buffer, 65535, 0, $this->clientHost, $this->clientPort);
            if ($len !== false && ord($buffer[0]) == UnconnectedPing::$ID) {
                echo "\e[38;5;83mReceived a ping from the client!\e[m" . PHP_EOL;
                break;
            }
        }

        echo "\e[38;5;145mSending ping to " . $this->serverHost . "...\e[m" . PHP_EOL;
        $ping = "\x01" . pack("NN", mt_rand(0, 0x7fffffff), mt_rand(0, 0x7fffffff)) . RakLib::MAGIC;
        socket_sendto($this->socket, $ping, strlen($ping), 0, $this->serverHost, $this->serverPort);

        echo "\e[38;5;227mWaiting for a response from the server...\e[m" . PHP_EOL;
        $pong = "";
        while (true) {
            $len = socket_recvfrom($this->socket, $pong, 65535, 0, $h, $p);
            if ($len !== false and $h === $this->serverHost and $this->serverPort === $p and ord($pong{0}) === UnconnectedPong::$ID) {
                echo "\e[38;5;83mReceived response from server!\e[m" . PHP_EOL;
                $info = explode(";", substr($pong, 35));
                echo "\e[38;5;87m\tMOTD: " . TextFormat::toANSI($info[1]) . PHP_EOL;
                echo "\tVersion: " . $info[3] . ", Protocol: " . $info[2] . PHP_EOL;
                echo "\tPlayers: " . $info[4] . "/" . $info[5] . "\e[m" . PHP_EOL;
                break;
            }
        }
        socket_sendto($this->socket, $pong, strlen($pong), 0, $this->clientHost, $this->clientPort);

        echo "\e[38;5;227mWaiting for login to the server\e[m" . PHP_EOL;
        while (true) {
            $len = socket_recvfrom($this->socket, $buffer, 65535, 0, $this->clientHost, $this->clientPort);
            if ($len !== false && ord($buffer{0}) === OpenConnectionRequest1::$ID) {
                echo "\e[38;5;83mReceived OpenConnectionRequest1 from client\e[m" . PHP_EOL;
                break;
            }
        }

        foreach (glob($directory . "plugins" . DIRECTORY_SEPARATOR . "*") as $path) {
            if (is_dir($path)) {
                if (file_exists($path . DIRECTORY_SEPARATOR . "plugin.json")) {
                    $data = json_decode(file_get_contents($path . DIRECTORY_SEPARATOR . "plugin.json"), true);
                    if (isset($data['main']) && isset($data['loader'])) {
                        $main = $data['main'];
                        $loader = $data['loader'];
                        /** @noinspection PhpIncludeInspection */
                        require_once $path . DIRECTORY_SEPARATOR . $loader;
                        /** @var ProxyPluginBase $plugin */
                        $plugin = new $main();
                        $this->plugins[] = $plugin;
                        $plugin->onInit($this);
                    }
                }
            }
        }

        $this->Listen();
    }

    /**
     * Listens and sends packets
     */
    protected function Listen(): void
    {
        while (true) {
            $status = @socket_recvfrom($this->socket, $buffer, 65535, 0, $source, $port);
            if ($status !== false) {
                if ($source === $this->serverHost and $port === $this->serverPort) {
                    foreach ($this->plugins as $plugin) {
                        if ($plugin instanceof ProxyPluginBase) {
                            if (!$plugin->onRakNetPacketFromServer($buffer)) {
                                continue;
                            }
                        }
                    }
                    if (($pk = $this->readDataPacket($buffer)) !== null) {
                        foreach ($this->plugins as $plugin) {
                            if ($plugin instanceof ProxyPluginBase) {
                                if (!$plugin->onDataPacketFromServer($pk)) {
                                    continue;
                                }
                            }
                        }
                    }
                    socket_sendto($this->socket, $buffer, strlen($buffer), 0, $this->clientHost, $this->clientPort);
                } elseif ($source === $this->clientHost and $port === $this->clientPort) {
                    foreach ($this->plugins as $plugin) {
                        if ($plugin instanceof ProxyPluginBase) {
                            if (!$plugin->onRakNetPacketFromClient($buffer)) {
                                continue;
                            }
                        }
                    }
                    if (($pk = $this->readDataPacket($buffer)) !== null) {
                        foreach ($this->plugins as $plugin) {
                            if ($plugin instanceof ProxyPluginBase) {
                                //NOTE: but client send this packet again
                                if (!$plugin->onDataPacketFromClient($pk)) {
                                    continue;
                                }
                            }
                        }
                    }
                    socket_sendto($this->socket, $buffer, strlen($buffer), 0, $this->serverHost, $this->serverPort);
                } else {
                    continue;
                }
            }
        }
    }

    /**
     * This function receives a packet sent by the client or server
     * @param string $buffer
     * @return null|DataPacket
     */
    public function readDataPacket(string $buffer): ?DataPacket
    {
        if (($packet = Pool::getPacketFromPool(ord($buffer{0}))) !== null) {
            $packet->buffer = $buffer;
            $packet->decode();
            if ($packet instanceof DATA_PACKET_4) {
                foreach ($packet->packets as $pk) {
                    if (($id = ord($pk->buffer{0})) === 0xfe) {
                        $batch = PacketPool::getPacket($pk->buffer);
                        if ($batch instanceof BatchPacket) {
                            @$batch->decode();
                            if ($batch->payload !== "" && is_string($batch->payload)) {
                                foreach ($batch->getPackets() as $buf) {
                                    $stole = PacketPool::getPacketById(ord($buf{0}));
                                    //Now there are a lot of forks, then some packages can be decrypted wrongly and this will cause an error.
                                    //Here you can disable some packages if they cause an errors
                                   $disabled = [ 
                                       I::AVAILABLE_COMMANDS_PACKET, 
                                       I::UPDATE_ATTRIBUTES_PACKET 
                                   ]; 
                                    if (!in_array($stole::NETWORK_ID, $disabled)) { 
                                        $stole->buffer = $buf; 
                                        $stole->decode(); 

                                       return $stole; 
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * @return Proxy
     */
    public static function getInstance(): Proxy
    {
        return self::$instance;
    }

    /**
     * This function can send a packet to the server or client
     * @param DataPacket $packet
     * @param string     $host
     * @param int        $port
     */
    public function writeDataPacket(DataPacket $packet, string $host, int $port): void
    {
        $batch = new BatchPacket;
        $batch->addPacket($packet);
        $batch->setCompressionLevel(7);
        $batch->encode();

        $encapsulated = new EncapsulatedPacket;
        $encapsulated->reliability = 0;
        $encapsulated->buffer = $batch->buffer;

        $dataPacket = new DATA_PACKET_4;
        $dataPacket->seqNumber = 666;
        $dataPacket->packets = [$encapsulated];
        $dataPacket->encode();

        socket_sendto($this->socket, $dataPacket->buffer, strlen($dataPacket->buffer), 0, $host, $port);
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }
}
