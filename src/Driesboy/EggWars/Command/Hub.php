<?php

namespace Driesboy\EggWars\Command;

use Driesboy\EggWars\EggWars;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\Config;

class Hub extends Command{

    public function __construct(){
        parent::__construct("hub", "Hub Command");
        $this->setAliases(array("lobby", "spawn"));
    }

    public function execute(CommandSender $g, $label, array $args){
        $main = EggWars::getInstance();
        if($main->IsInArena($g->getName())){
            $arena = $main->IsInArena($g->getName());
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            $durum = $ac->get("Status");
            if($durum === "Lobby"){
                $main->RemoveArenaPlayer($arena, $g->getName());
                $g->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
                $g->sendMessage("§8» §aYou are teleported to the Lobby");
            }else{
                $g->sendMessage("§8» §cYour game is running");
            }
        }
    }
}
