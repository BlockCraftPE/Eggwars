<?php

namespace Driesboy\EggWars\Command;

use Driesboy\EggWars\EggWars;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class EW extends Command{

  public function __construct(){
    parent::__construct("ew", "EggWars by Driesboy & Enes5519");
  }

  public function execute(CommandSender $g, $label, array $args){
    $main = EggWars::getInstance();
    if($g->hasPermission("eggwars.command") && $g instanceof Player){
      if(!empty($args[0])){
        if($args[0] === "help"){
          $g->sendMessage("§6----- §fEggwars Help Page §6-----");
          $g->sendMessage("§8» §e/ew create".' <arena> <teams> <PlayersPerTeam> '."§6Create an Arena!");
          $g->sendMessage("§8» §e/ew set".' <arena> <team> '."§6Set the TeamSpawn!");
          $g->sendMessage("§8» §e/ew lobby".' <arena> '."§6Set the WaitingLobby!");
          $g->sendMessage("§8» §e/ew save".' <arena> '."§6Save the map!");
          $g->sendMessage("§8» §e/ew shop "."§6Spawn a Villager");
        }elseif ($args[0] === "create"){
          if(!empty($args[1])){
            if(!empty($args[2]) && is_numeric($args[2])){
              if(!empty($args[3]) && is_numeric($args[3])){
                $main->ArenaCreate($args[1], $args[2], $args[3], $g);
              }else{
                $g->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
              }
            }else{
              $g->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
            }
          }else{
            $g->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
          }
        }elseif ($args[0] === "set"){
          if(!empty($args[1])){
            if(!empty($args[2])){
              $main->ArenaSet($args[1], $args[2], $g);
            }else{
              $g->sendMessage("§8» §c/ew set ".'<arena> <team>');
            }
          }else{
            $g->sendMessage("§8» §c/ew set ".'<arena> <team>');
          }
        }elseif ($args[0] === "lobby"){
          if(!empty($args[1])){
            if($main->ArenaControl($args[1])){
              $ac = new Config($main->getDataFolder()."Arenas/$args[1].yml", Config::YAML);
              $ac->setNested("Lobby.X", $g->getFloorX());
              $ac->setNested("Lobby.Y", $g->getFloorY());
              $ac->setNested("Lobby.Z", $g->getFloorZ());
              $ac->setNested("Lobby.Yaw", $g->getYaw());
              $ac->setNested("Lobby.Pitch", $g->getPitch());
              $ac->setNested("Lobby.World", $g->getLevel()->getFolderName());
              $ac->save();
              $g->sendMessage("§8» §a$args[1] 's WaitingLobby has been created succesfull");
            }else{
              $g->sendMessage("§8» §c$args[1] is not an arena");
            }
          }else{
            $g->sendMessage("§8» §c/ew Lobby ".'<arena>');
          }
        }elseif($args[0] === "save"){
          if(!empty($args[1])){
            if($main->ArenaControl($args[1])) {
              if ($g->getLevel() != Server::getInstance()->getDefaultLevel()) {
                $ac = new Config($main->getDataFolder()."Arenas/$args[1].yml", Config::YAML);
                $ac->set("World", $g->getLevel()->getFolderName());
                $ac->save();
                $main->copy(Server::getInstance()->getDataPath()."worlds/".$g->getLevel()->getFolderName(), $main->getDataFolder()."Back-Up/".$g->getLevel()->getFolderName());
                $g->sendMessage("§8» §a$args[1] has been saved");
              } else {
                $g->sendMessage("§8» §cYour map cannot be the ServerSpawn");
              }
            }else{
              $g->sendMessage("§8» §c$args[1] is not an arena");
            }
          }else{
            $g->sendMessage("§8» §c/ew save ".'<arena>');
          }
        }elseif($args[0] === "shop"){
          $this->CreateShop($g->x, $g->y, $g->z, $g->yaw, $g->pitch, $g->getLevel(), 1);
        }elseif($args[0] === "start"){
          if($main->IsInArena($g->getName())){
            $arena = $main->IsInArena($g->getName());
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            if($ac->get("Status") === "Lobby"){
              $ac->set("StartTime", 6);
              $ac->save();
              $g->sendMessage("§bStarting the game ...");
            }
          }else{
            $g->sendMessage("§cYou are not in a game!");
          }
        }
      }else{
        $g->sendMessage("§8» §c/ew Help §7EggWars Help Commando's");
      }
    }else{
      $g->sendMessage("§8» §6EggWars Plugin By §eDriesboy & Enes5519!");
    }
  }

  public function CreateShop($x, $y, $z, $yaw, $pitch, Level $World, $pro){
    $nbt = new CompoundTag;
    $nbt->Pos = new ListTag("Pos", [
      new DoubleTag("", $x),
      new DoubleTag("", $y),
      new DoubleTag("", $z)
    ]);
    $nbt->Rotation = new ListTag("Rotation", [
      new DoubleTag("", $yaw),
      new DoubleTag("", $pitch)
    ]);
    $nbt->Motion = new ListTag("Motion", [
      new DoubleTag("", 0),
      new DoubleTag("", 0)
    ]);
    $nbt->Profession = new ByteTag("Profession", $pro);
    $nbt->Health = new ShortTag("Health", 10);
    $nbt->CustomName = new StringTag("CustomName", "§6EggWars Shop");
    $nbt->CustomNameVisible = new ByteTag("CustomNameVisible", 1);
    $World->loadChunk($x >> 4, $z >> 4);
    $koylu = Entity::createEntity("Villager", $World, $nbt);
    $koylu->setProfession($pro);
    $koylu->spawnToAll();
  }
}
