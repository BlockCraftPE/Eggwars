<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;

class SignManager extends PluginTask{

  private $p;
  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun($currentTick){
    $main = $this->p;
    $level = Server::getInstance()->getDefaultLevel();
    $tiles = $level->getTiles();
    foreach ($tiles as $t){
      if($t instanceof Sign){
        $y = $t->getText();
        if($y[0] === $main->tyazi){
          $arena = str_ireplace("§e", "", $y[2]);
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          $Status = $ac->get("Status");
          $Players = count($main->ArenaPlayer($arena));
          $fullPlayer = $ac->get("Team") * $ac->get("PlayersPerTeam");
          $d = null;
          $re = null;
          $b=$t->getBlock();
          if($Status === "Lobby"){
            if($Players >= $fullPlayer){
              $d = "§c§lFull";
              $re = 14;
            }else{
              $d = "§a§lTap to join";
              $re = 5;
            }
          }elseif ($Status === "In-Game"){
            $d = "§d§lIn-Game";
            $re = 1;
          }elseif($Status === "Done"){
            $d = "§9§lRestarting";
            $re = 4;
          }
          $ab = $b->getSide(Vector3::SIDE_SOUTH, 1);
          $ba = $b->getSide(Vector3::SIDE_NORTH, 1);
          $ca = $b->getSide(Vector3::SIDE_EAST, 1);
          $ac = $b->getSide(Vector3::SIDE_WEST, 1);
          $t->setText($y[0], "§f$Players/$fullPlayer", $y[2], $d);
          if($ac->getId() === 35){
            $ac->setDamage($re);
            $b->getLevel()->setBlock($ac, $ac);
          }elseif($ca->getId() === 35){
            $ca->setDamage($re);
            $b->getLevel()->setBlock($ca, $ca);
          }elseif($ab->getId() === 35){
            $ab->setDamage($re);
            $b->getLevel()->setBlock($ab, $ab);
          }elseif($ba->getId() === 35){
            $ba->setDamage($re);
            $b->getLevel()->setBlock($ba, $ba);
          }
        }
      }
    }
  }
}
