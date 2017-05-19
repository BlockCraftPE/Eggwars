<?php

namespace Driesboy\EggWars;

use Driesboy\EggWars\Command\Hub;
use Driesboy\EggWars\Command\EW;
use Driesboy\EggWars\Task\Game;
use Driesboy\EggWars\Task\SignManager;
use Driesboy\EggWars\Task\StackTask;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;

class EggWars extends PluginBase{

  private static $ins;
  public $ky = array();
  public $sb = '§6EggWars> ';
  public $tyazi = '§8§l» §r§6Egg §fWars §l§8«';
  public $m = array();
  public $mo = array();

  public function onEnable(){
    @mkdir($this->getDataFolder());
    @mkdir($this->getDataFolder()."Arenas/");
    @mkdir($this->getDataFolder()."Back-Up/");
    self::$ins = $this;
    $this->saveDefaultConfig();
    $this->saveResource("shop.yml");
    $this->AnotherPrepare();
    $this->PrepareArenas();
  }

  public static function getInstance(){
    return self::$ins;
  }

  public function AnotherPrepare(){
    Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), $this);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new SignManager($this), 20);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new Game($this), 20);
    $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    if ($cfg->get("Reduce-Lagg") === true){
      Server::getInstance()->getScheduler()->scheduleDelayedRepeatingTask(new StackTask($this), 15, 15);
    }
    Server::getInstance()->getCommandMap()->register("ew", new EW());
    Server::getInstance()->getCommandMap()->register("hub", new Hub());
  }

  public function PrepareArenas(){
    foreach($this->Arenas() as $arena){
      if($this->ArenaReady($arena)){
        $this->ArenaRefresh($arena);
      }
    }
  }

  public function ArenaPlayer($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $Players = $ac->get("Players");
    $o = array();
    foreach ($Players as $Is) {
      $go = Server::getInstance()->getPlayer($Is);
      if($go instanceof Player){
        $o[] = $Is;
      }else{
        $this->RemoveArenaPlayer($arena, $Is, 1);
      }
    }
    return $o;
  }

  public function RemoveArenaPlayer($arena, $isim, $oa = 0){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $Players = $ac->get("Players");
    $Status = $ac->get("Status");
    if($Status === "Lobby"){
      $this->ArenaMessage($arena, "§6$isim left the game ". count($this->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
    }
    if(@in_array($isim, $Players)){
      $o = Server::getInstance()->getPlayer($isim);
      if($o instanceof Player && $oa != 1){
        $this->PureChat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        $nameTag = $this->PureChat->getNametag($o);
        $o->setNameTag($nameTag);
        $o->getInventory()->clearAll();
        $o->setHealth(20);
        $o->setFood(20);
        $o->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
        if ($o->hasPermission("rank.diamond")){
          $o->setGamemode("1");
          $pk = new ContainerSetContentPacket();
          $pk->windowid = ContainerSetContentPacket::SPECIAL_CREATIVE;
          $o->dataPacket($pk);
        }
      }
      $key = array_search($isim, $Players);
      unset($Players[$key]);
      $ac->set("Players", $Players);
      $ac->save();
    }
  }

  public function AddArenaPlayer($arena, $isim){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $Players = $ac->get("Players");
    if(!in_array($isim, $Players)){
      $o = Server::getInstance()->getPlayer($isim);
      if($o instanceof Player){
        $o->setNameTag($o->getName());
        $o->setGamemode(0);
        $o->getInventory()->clearAll();
        $o->setHealth(20);
        $o->setFood(20);
        $o->removeAllEffects();
      }
      $Players[] = $isim;
      $ac->set("Players", $Players);
      $ac->save();
    }
  }

  public function Arenas(){
    $Arenas = array();
    $d = opendir($this->getDataFolder()."Arenas");
    while($file = readdir($d)){
      if($file != "." && $file != ".."){
        $arena = str_replace(".yml", "", $file);
        if($this->ArenaReady($arena)){
          $Arenas[] = $arena;
        }
      }
    }
    return $Arenas;
  }

  public function Teams(){
    $Teams = array(
      "ORANGE" => "§6",
      "PURPLE" => "§d",
      "LIGHT-BLUE" => "§b",
      "YELLOW" => "§e",
      "GREEN" => "§a",
      "GRAY" => "§7",
      "BLUE" => "§9",
      "RED" => "§c"
    );
    return $Teams;
  }

  public function TeamSearcher(){
    $tyc = array(
      "ORANGE" => 1,
      "PURPLE" => 10,
      "LIGHT-BLUE" => 3,
      "YELLOW" => 4,
      "GREEN" => 13,
      "GRAY" => 7,
      "BLUE" => 11,
      "RED" => 14
    );
    return $tyc;
  }


  public function ArenaControl($arena){
    if(file_exists($this->getDataFolder()."Arenas/$arena.yml")){
      return true;
    }else{
      return false;
    }
  }

  public function ArenaReady($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    if($ac->get("World")){
      if(file_exists($this->getDataFolder()."Back-Up/".$ac->get("World")."/")){
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  public function IsInArena($isim){
    $Arenas = $this->Arenas();
    $a = null;
    foreach ($Arenas as $arena){
      $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
      $Players = $ac->get("Players");
      if(in_array($isim, $Players)){
        $a = $arena;
        break;
      }
    }
    if($a != null){
      return $a;
    }else{
      return false;
    }
  }

  public function ArenaStatus($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $Status = $ac->get("Status");
    return $Status;
  }

  public function ArenaCreate($arena, $Team, $tbo, Player $o){
    if(!$this->ArenaControl($arena)){
      if($Team <= 8) {
        if($tbo <= 8) {
          $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
          $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
          $ac->set("Status", "Lobby");
          $ac->set("StartTime", $cfg->get("StartTime"));
          $ac->set("EndTime", $cfg->get("EndTime"));
          $ac->set("Team", (int) $Team);
          $ac->set("PlayersPerTeam", (int) $tbo);
          $ac->set("Players", array());
          $ac->save();
          $o->sendMessage($this->sb."§a$arena was successfully built!");
        }else{
          $o->sendMessage("§8» §cThe number of players per team should be 8 or less.");
        }
      }else{
        $o->sendMessage("§8» §cTeam number should be 8 or less.");
      }
    }else{
      $o->sendMessage("§8» §c$arena already exists!");
    }
  }

  public function ArenaTeams($arena){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      $Teams = array();
      foreach ($this->Teams() as $Team => $color){
        if(!empty($ac->getNested($Team.".X"))){
          $Teams[] = $Team;
        }
      }
      return $Teams;
    }else{
      return false;
    }
  }

  public function ArenaSet($arena, $Team, Player $o){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      if(!empty($this->Teams()[$Team])){
        if(count($this->ArenaTeams($arena)) === $ac->get("Team")){
          if($ac->getNested("$Team.X")){
            $ac->setNested("$Team.X", $o->getFloorX());
            $ac->setNested("$Team.Y", $o->getFloorY());
            $ac->setNested("$Team.Z", $o->getFloorZ());
            $ac->save();
            $o->sendMessage("§8» §a$Team has been successfully updated!");
          }else{
            $o->sendMessage("§8» §cAll the teams are settled, you can only change the teams!");
          }
        }else{
          $ac->setNested("$Team.X", (int) $o->getFloorX());
          $ac->setNested("$Team.Y", (int) $o->getFloorY());
          $ac->setNested("$Team.Z", (int) $o->getFloorZ());
          $ac->save();
          $o->sendMessage($this->Teams()[$Team]."$Team 's spawn successfully placed!");
        }
      }else{
        $Team = null;
        foreach ($this->Teams() as $Team => $color){
          $Team .= $color.$Team." ";
        }
        $o->sendMessage("§8» §fTeams you can use: \n$Team");
      }
    }else{
      $o->sendMessage("§8» §cThere is no such arena.");
    }
  }

  public function copy($source, $target){
    $directory = opendir($source);
    @mkdir($target);
    while (false !== ($file = readdir($directory))){
      if ($file != "." && $file != "..") {
        if (is_dir($source.'/'.$file)) {
          $this->copy($source.'/'.$file, $target.'/'.$file);
        } else {
          copy($source.'/'.$file, $target.'/'.$file);
        }
      }
    }
    closedir($directory);
  }

  public function MapReset($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml");
    $World = $ac->get("World");
    $level = Server::getInstance()->getLevelByName($World);
    if($level instanceof Level){
      Server::getInstance()->unloadLevel($level);
    }
    $this->copy($this->getDataFolder()."Back-Up/".$World, $this->getServer()->getDataPath()."worlds/".$World);
    Server::getInstance()->loadLevel($World);
  }

  public function ItemId(Player $o, $id){
    $items = 0;
    for($i=0; $i<36; $i++){
      $item = $o->getInventory()->getItem($i);
      if($item->getId() === $id){
        $items += $item->getCount();
      }
    }
    return $items;
  }

  public function Status($arena){
    $Status = array();
    $plus = "§8[§a+§8]";
    $minus = "§8[§c-§8]";
    foreach($this->ArenaTeams($arena) as $at){
      if(!@in_array($at, $this->ky[$arena])){
        $Status[] = $this->Teams()[$at].$at.$plus." ";
      }else{
        $Status[] = $this->Teams()[$at].$at." ".$minus." ";
      }
    }
    return $Status;
  }

  public function ArenaMessage($arena, $message){
    $Players = $this->ArenaPlayer($arena);
    foreach($Players as $Is){
      $o = $this->getServer()->getPlayer($Is);
      if($o instanceof Player){
        $o->sendMessage($message);
      }
    }
  }

  public function PlayerTeamColor(Player $o){
    $TeamColor = substr($o->getNameTag(), 0, 3);
    if(strstr($TeamColor, "§")){
      $Key = array_search($TeamColor, $this->Teams());
      return $Key;
    }else{
      return false;
    }
  }

  public function AvailableTeams($arena){
    $Players = $this->ArenaPlayer($arena);
    $TeamNumber = 0;
    $cfg = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $musaitTeam = array();
    foreach($this->ArenaTeams($arena) as $Team){
      foreach($Players as $Is){
        $o = $this->getServer()->getPlayer($Is);
        if($o instanceof Player){
          if($this->PlayerTeamColor($o) === $Team){
            $TeamNumber++;
          }
        }
      }

      if($TeamNumber < $cfg->get("PlayersPerTeam")){
        $musaitTeam[] = $Team;
      }
      $TeamNumber = 0;
    }

    return $musaitTeam;
  }

  public function AvailableRastTeam($arena){
    $mt = $this->AvailableTeams($arena);
    $mixed = array_rand($mt);
    return $this->Teams()[$mt[$mixed]];
  }

  public function TeamSellector($arena, Player $o){
    foreach($this->ArenaTeams($arena) as $at){
      $meta = $this->TeamSearcher()[$at];
      $color = $this->Teams()[$at];
      $item = Item::get(35);
      $item->setDamage($meta);
      $item->setCustomName("§r§8» ".$color.$at."§8 «");
      $o->getInventory()->addItem($item);
    }
  }

  public function EggSkin($arena, $Team){
    if(empty($this->ky[$arena])){
      return false;
    }else{
      if(@in_array($Team, $this->ky[$arena])){
        return true;
      }else{
        return false;
      }
    }
  }

  public function ArenaRefresh($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
    $Lobby = Server::getInstance()->getLevelByName($ac->getNested("Lobby.World"));
    if(!$Lobby instanceof Level){
      Server::getInstance()->loadLevel($ac->getNested("Lobby.World"));
    }
    $ac->set("Status", "Lobby");
    $ac->set("StartTime", (int) $cfg->get("StartTime"));
    $ac->set("EndTime", (int) $cfg->get("EndTime"));
    $ac->set("Players", array());
    $ac->save();
    unset($this->ky[$arena]);
    $this->MapReset($arena);
  }

  public function OneTeamRemained($arena){
    $Players = $this->ArenaPlayer($arena);
    $Teams = array();
    foreach ($Players as $ol){
      $o = Server::getInstance()->getPlayer($ol);
      if($o instanceof Player){
        $Team = $this->PlayerTeamColor($o);
        if(!in_array($Team, $Teams)){
          $Teams[] = $Team;
        }
      }
    }
    if(count($Teams) === 1){
      return true;
    }else{
      return false;
    }
  }

  public function EmptyShop(Player $o){
    $o->getLevel()->setBlock(new Vector3($o->getFloorX(), $o->getFloorY() - 4, $o->getFloorZ()), Block::get(Block::CHEST));
    $nbt = new CompoundTag("", [
      new ListTag("Items", []),
      new StringTag("id", Tile::CHEST),
      new IntTag("x", $o->getFloorX()),
      new IntTag("y", $o->getFloorY() - 4),
      new IntTag("z", $o->getFloorZ()),
      new StringTag("CustomName", "§6EggWars Shop")
    ]);
    $nbt->Items->setTagType(NBT::TAG_Compound);
    $tile = Tile::createTile("Chest", $o->getLevel(), $nbt);
    if($tile instanceof Chest) {
      $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
      $shop = $config->get("shop");
      $tile->setName("§6EggWars Shop");
      $tile->getInventory()->clearAll();
      for ($i = 0; $i < count($shop); $i+=2) {
        $slot = $i / 2;
        $tile->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
      }
      $tile->getInventory()->setItem($tile->getInventory()->getSize()-1, Item::get(Item::WOOL, 14, 1));
      $o->addWindow($tile->getInventory());
    }
  }

  public static function CreateLightning($x, $y, $z, $level){
    $lightning = new AddEntityPacket();
    $lightning->metadata = array();
    $lightning->type = 93;
    $lightning->eid = Entity::$entityCount++;
    $lightning->speedX = 0;
    $lightning->speedY = 0;
    $lightning->speedZ = 0;
    $lightning->x = $x;
    $lightning->y = $y;
    $lightning->z = $z;
    foreach($level->getPlayers() as $pl){
      $pl->dataPacket($lightning);
    }
  }
}
