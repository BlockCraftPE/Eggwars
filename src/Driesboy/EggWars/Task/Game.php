<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\TextFormat as TF;

class Game extends PluginTask{

  private $p;
  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun($tick){
    $main = $this->p;
    $pl = $main->getServer()->getOnlinePlayers();
    foreach($pl as $p){
      if($p->getLevel()->getFolderName() === "ELobby"){
        if(!$p->getInventory()->getItemInHand()->hasEnchantments()){
          $p->sendPopup(TF::GRAY."You are playing on ".TF::BOLD.TF::BLUE."GameCraft PE EggWars".TF::RESET."\n".TF::DARK_GRAY."[".TF::LIGHT_PURPLE.count($main->getServer()->getOnlinePlayers()).TF::DARK_GRAY."/".TF::LIGHT_PURPLE.$main->getServer()->getMaxPlayers().TF::DARK_GRAY."] | ".TF::YELLOW."$".$main->getServer()->getPluginManager()->getPlugin("EconomyAPI")->myMoney($p).TF::DARK_GRAY." | ".TF::BOLD.TF::AQUA."Vote: ".TF::RESET.TF::GREEN."gamecraftvote.tk");
        }
      }
    }
    foreach($main->Arenas() as $arena){
      if($main->ArenaReady($arena)){
        $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
        $Status = $ac->get("Status");
        if($Status === "Lobby"){
          $Time = (int) $ac->get("StartTime");
          if($Time > 0 || $Time <= 0){
            if(count($main->ArenaPlayer($arena)) >= $ac->get("Team")){
              $Time--;
              $ac->set("StartTime", $Time);
              $ac->save();
              switch ($Time){
                case 120:
                $main->ArenaMessage($arena, "§9EggWars starting in 2 minutes");
                break;
                case 90:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute and 30 seconds");
                break;
                case 60:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute");
                break;
                case 30:
                case 15:
                case 5:
                case 4:
                case 3:
                case 2:
                case 1:
                $main->ArenaMessage($arena, "§9EggWars starting in $Time seconds");
                break;
                default:
                if($Time <= 0) {
                  foreach ($main->ArenaPlayer($arena) as $Is) {
                    $o = $main->getServer()->getPlayer($Is);
                    if ($o instanceof Player) {
                      if (!$main->PlayerTeamColor($o)) {
                        $Team = $main->AvailableRastTeam($arena);
                        $o->setNameTag($Team . $o->getName());
                      }
                      $Team = $main->PlayerTeamColor($o);
                      $o->teleport(new Position($ac->getNested($Team . ".X"), $ac->getNested($Team . ".Y"), $ac->getNested($Team . ".Z"), $main->getServer()->getLevelByName($ac->get("World"))));
                      $o->getInventory()->clearAll();
                      $o->sendMessage("§1Go!");
                    }
                  }
                  $ac->set("Status", "In-Game");
                  $ac->save();
                }
                break;
              }
              $all = $main->ArenaPlayer($arena);
              foreach($all as $p){
                $o = $main->getServer()->getPlayer($p);
                if($o instanceof Player){
                  $o->setXpLevel($Time);
                }
              }
            }
          }
        }elseif($Status === "In-Game"){
          $level = Server::getInstance()->getLevelByName($ac->get("World"));
          $tile = $level->getTiles();
          foreach ($tile as $sign){
            if($sign instanceof Sign){
              $y = $sign->getText();
              if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
                $evet = false;
                foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 10, $sign->y - 10, $sign->z - 10, $sign->x + 10, $sign->y + 10, $sign->z + 10)) as $ent){
                  if($ent instanceof Player){
                    $evet = true;
                  }
                }
                if($evet === true){
                  $im = explode(" ", $y[2]);
                  $second = str_ireplace("§b", "", $im[0]);
                  $tur = $y[0];
                  if($second != "Broken"){
                    $item = $this->turDonusItem($tur);
                    if(time() % $second === 0){
                      $level->dropItem(new Vector3($sign->x, $sign->y, $sign->z), $item);
                    }
                  }
                }
              }
            }
          }
          foreach($main->ArenaPlayer($arena) as $Is){
            $o = Server::getInstance()->getPlayer($Is);
            $i = null;
            foreach($main->Status($arena) as $Status){
              $i.=$Status;
            }
            $o->sendPopup($i);
          }
          if($main->OneTeamRemained($arena)){
            $ac->set("Status", "Done");
            $ac->save();
            $main->ArenaMessage($arena, "§aCongratulations, you win!");
            foreach ($main->ArenaPlayer($arena) as $Is) {
              $o = Server::getInstance()->getPlayer($Is);
              if(!($o instanceof Player)){
                return true;
              }
              $Team = $main->PlayerTeamColor($o);
            }
            Server::getInstance()->broadcastMessage("$Team §9won the game on §b$arena!");
          }
        }elseif($Status === "Done"){
          $bitis = (int) $ac->get("EndTime");
          if($bitis > 0 || $bitis <= 0){
            $bitis--;
            $ac->set("EndTime", $bitis);
            $ac->save();
            foreach($main->ArenaPlayer($arena) as $Players){
              $o = Server::getInstance()->getPlayer($Players);
              if($bitis <= 1){
                $main->RemoveArenaPlayer($arena, $o->getName());
              }
            }
            if($bitis <= 0){
              $main->ArenaRefresh($arena);
              return;
            }
          }
        }else{
          $ac->set("Status", "Done");
          $ac->save();
        }
      }
    }
  }

  public function turDonusItem($tur){
    $item = null;
    switch($tur){
      case "§6Gold":
      $item = Item::get(266);
      break;
      case "§bDiamond":
      $item = Item::get(264);
      break;
      default:
      $item = Item::get(265);
      break;
    }
    return $item;
  }
}
