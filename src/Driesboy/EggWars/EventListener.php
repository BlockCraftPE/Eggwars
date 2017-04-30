<?php

namespace Driesboy\EggWars;

use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\block\Block;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\tile\Chest;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\network\protocol\ContainerSetContentPacket;
use pocketmine\network\protocol\SetPlayerGameTypePacket;
use pocketmine\network\protocol\AdventureSettingsPacket;


class EventListener implements Listener{

  public $sd = array();
  public function __construct(){
  }

  public function OnQuit(PlayerQuitEvent $e){
    $main = EggWars::getInstance();
    $p = $e->getPlayer();
    if($main->IsInArena($p->getName())){
      $arena = $main->IsInArena($p->getName());
      $main->RemoveArenaPlayer($arena, $p->getName());
      $p->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
      $message = $p->getNameTag()." §eleft the game!";
      $main->ArenaMessage($arena, $message);
    }
  }

  public function OnJoin(PlayerJoinEvent $e){
    if ($e->getPlayer()->hasPermission("rank.diamond")){
      $e->getPlayer()->setGamemode("1");
      $pk = new ContainerSetContentPacket();
      $pk->windowid = ContainerSetContentPacket::SPECIAL_CREATIVE;
      $e->getPlayer()->dataPacket($pk);
    }
  }

    /**
     * Priority is the MONITOR so it can pass PureChat plugin priority.
     *
     * @param PlayerChatEvent $e
     * @priority MONITOR
     */
     public function Chat(PlayerChatEvent $e){
       $o = $e->getPlayer();
       $m = $e->getMessage();
       $main = EggWars::getInstance();
       if($main->IsInArena($o->getName())){
         $color = "";
         $is = substr($m, 0, 1);
         $Team = $main->PlayerTeamColor($o);
         $arena = $main->IsInArena($o->getName());
         $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
         $Players = $main->ArenaPlayer($arena);
         if($ac->get("Status") === "Lobby"){
           foreach($Players as $p){
             $to = $main->getServer()->getPlayer($p);
             if($to instanceof Player){
               $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($o, $m);
               $to->sendMessage($chatFormat);
               $e->setCancelled();
             }
           }
         }
         if(!empty($main->Teams()[$Team])){
           $color = $main->Teams()[$Team];
         }
         if ($ac->get("Status") != "Lobby"){
           if($is === "!"){
             foreach($Players as $p){
               $to = $main->getServer()->getPlayer($p);
               if($to instanceof Player){
                 $msil = substr($m, 1);
                 $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($o, $msil);
                 $to->sendMessage($chatFormat);
                 $e->setCancelled();
               }
             }
           }else{
             foreach($Players as $p){
               $to = $main->getServer()->getPlayer($p);
               if($to instanceof Player){
                 $toTeam = $main->PlayerTeamColor($to);
                 if($Team === $toTeam){
                   $Format = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($o, $m);
                   $message = "§8[".$color."team§8] ". $Format;
                   $to->sendMessage($message);
                   $e->setCancelled();
                 }
               }
             }
           }
         }
         return;
       }
     }

  public function OnInteract(PlayerInteractEvent $e){
    $o = $e->getPlayer();
    $b = $e->getBlock();
    $t = $o->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($t instanceof Sign){
      $yazilar = $t->getText();
      if($yazilar[0] === $main->tyazi){
        $arena = str_ireplace("§e", "", $yazilar[2]);
        $Status = $main->ArenaStatus($arena);
        if($Status === "Lobby"){
          if(!$main->IsInArena($o->getName())){
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            $Players = count($main->ArenaPlayer($arena));
            $fullPlayer = $ac->get("Team") * $ac->get("PlayersPerTeam");
            if($Players >= $fullPlayer){
              $o->sendPopup("§8» §cThis game is full! §8«");
              return;
            }
            $main->AddArenaPlayer($arena, $o->getName());
            $o->teleport(new Position($ac->getNested("Lobby.X"), $ac->getNested("Lobby.Y"), $ac->getNested("Lobby.Z"), $main->getServer()->getLevelByName($ac->getNested("Lobby.World"))));
            $main->TeamSellector($arena, $o);
            $main->ArenaMessage($arena, "§5".$o->getName()." §5joined the game. ". count($main->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
          }else{
            $o->sendPopup("§cYou're already in a game!");
          }
        }elseif ($Status === "In-Game"){
          $o->sendPopup("§8» §dThe game is still going on!");
        }elseif ($Status === "Done"){
          $o->sendPopup("§8» §eResetting the Arena ...");
        }
        $e->setCancelled();
      }
    }
  }

  public function UpgradeGenerator(PlayerInteractEvent $e){
    $o = $e->getPlayer();
    $b = $e->getBlock();
    $sign = $o->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($sign instanceof Sign){
      $y = $sign->getText();
      if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
        $tip = $y[0];
        $level = str_ireplace("§eLevel ", "", $y[1]);
        switch($level){
          case 0:
          switch ($tip){
            case "§6Gold":
            if($main->ItemId($o, Item::GOLD_INGOT) >= 5){
              $o->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b8 seconds", $y[3]);
              $o->sendMessage("§8» §aGold generator Activated!");
            }else{
              $o->sendMessage("§8» §65 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($o, Item::DIAMOND) >= 5){
              $o->getInventory()->removeItem(Item::get(Item::DIAMOND,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b10 seconds", $y[3]);
              $o->sendMessage("§8» §aDiamond generator Activated!");
            }else{
              $o->sendMessage("§8» §b5 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 1:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($o, Item::IRON_INGOT) >= 10){
              $o->getInventory()->removeItem(Item::get(Item::IRON_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b2 seconds", $y[3]);
              $o->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $o->sendMessage("§8» §f10 Iron needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($o, Item::GOLD_INGOT) >= 10){
              $o->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b6 seconds", $y[3]);
              $o->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $o->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($o, Item::DIAMOND) >= 10){
              $o->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b8 seconds", $y[3]);
              $o->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $o->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 2:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($o, Item::GOLD_INGOT) >= 10){
              $o->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 3", "§b1 seconds", "§c§lMAXIMUM");
              $o->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $o->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($o, Item::DIAMOND) >= 10){
              $o->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 3", "§b4 seconds", "§c§lMAXIMUM");
              $o->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $o->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($o, Item::DIAMOND) >= 20){
              $o->getInventory()->removeItem(Item::get(Item::DIAMOND,0,20));
              $sign->setText($y[0], "§eLevel 3", "§b6 seconds", "§c§lMAXIMUM");
              $o->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $o->sendMessage("§8» §b20 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          default:
          $o->sendMessage("§8» §cThis generator is already on the Maximum level!");
          break;
        }
      }
    }
  }

  public function DestroyEgg(PlayerInteractEvent $e){
    $o = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($main->IsInArena($o->getName())){
      if($b->getId() === 122){
        $yun = $b->getLevel()->getBlock(new Vector3($b->x, $b->y - 1, $b->z));
        if($yun->getId() === 35){
          $color = $yun->getDamage();
          $Team = array_search($color, $main->TeamSearcher());
          $oht = $main->PlayerTeamColor($o);
          if($oht === $Team){
            $o->sendPopup("§8»§c You can not break your own egg!");
            $e->setCancelled();
          }else{
            $b->getLevel()->setBlock(new Vector3($b->x, $b->y, $b->z), Block::get(0));
            $main->CreateLightning($b->x, $b->y, $b->z, $o->getLevel());
            $arena = $main->IsInArena($o->getName());
            $main->ky[$arena][] = $Team;
            $main->ArenaMessage($main->IsInArena($o->getName()), "§eTeam " .$main->Teams()[$Team]."$Team's".$main->Teams()[$oht]." §eegg has been destroyed by " .$o->getNameTag());
          }
        }
      }
    }
  }

  public function CreateSign(SignChangeEvent $e){
    $o = $e->getPlayer();
    $main = EggWars::getInstance();
    if($o->isOp()){
      if($e->getLine(0) === "eggwars"){
        if(!empty($e->getLine(1))){
          if($main->ArenaControl($e->getLine(1))){
            if($main->ArenaReady($e->getLine(1))){
              $arena = $e->getLine(1);
              $e->setLine(0, $main->tyazi);
              $e->setLine(1, "§f0/0");
              $e->setLine(2, "§e$arena");
              $e->setLine(3, "§l§bTap to Join");
              for($i=0; $i<=3; $i++){
                $o->sendMessage("§8» §a$i".$e->getLine($i));
              }
            }else{
              $e->setLine(0, "§cERROR");
              $e->setLine(1, "§7".$e->getLine(1));
              $e->setLine(2, "§7Arena");
              $e->setLine(3, "§7not exactly!");
            }
          }else{
            $e->setLine(0, "§cERROR");
            $e->setLine(1, "§7".$e->getLine(1));
            $e->setLine(2, "§7Arena");
            $e->setLine(3, "§7Not found");
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7Arena");
          $e->setLine(2, "§7Section");
          $e->setLine(3, "§7null!");
        }
      }elseif ($e->getLine(0) === "generator"){
        if(!empty($e->getLine(1))){
          switch ($e->getLine(1)){
            case "Iron":
            $e->setLine(0, "§fIron");
            $e->setLine(1, "§eLevel 1");
            $e->setLine(2, "§b4 seconds");
            $e->setLine(3, "§a§lUpgrade");
            break;
            case "Gold":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b8 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
            case "Diamond":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b10 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7generator");
          $e->setLine(2, "§7Type");
          $e->setLine(3, "§7unspecified!");
        }
      }
    }
  }

  public function onDeath(PlayerDeathEvent $e){
    $o = $e->getPlayer();
    $main = EggWars::getInstance();
    if($main->IsInArena($o->getName())){
      $e->setDeathMessage("");
      $sondarbe = $o->getLastDamageCause();
      if($sondarbe instanceof EntityDamageByEntityEvent){
        $e->setDrops(array());
        $olduren = $sondarbe->getDamager();
        if($olduren instanceof Player){
          $main->ArenaMessage($main->IsInArena($o->getName()), $o->getNameTag()." §ewas killed by ".$olduren->getNameTag());
        }
      }else{
        $e->setDrops(array());
        if(!empty($this->sd[$o->getName()])){
          $olduren = $main->getServer()->getPlayer($this->sd[$o->getName()]);
          if($olduren instanceof Player){
            $main->ArenaMessage($main->IsInArena($o->getName()), $o->getNameTag()." §ewas killed by ".$olduren->getNameTag());
          }
        }else{
          $main->ArenaMessage($main->IsInArena($o->getName()), $o->getNameTag()." §edied!");
        }
      }
    }
  }

  public function Damage(EntityDamageEvent $e){
    $o = $e->getEntity();
    $main = EggWars::getInstance();
    if($o->getLevel()->getName() === "ELobby"){
      $e->setCancelled();
    }
    if($e instanceof EntityDamageByEntityEvent){
      $d = $e->getDamager();
      if($o instanceof Villager && $d instanceof Player){
        if($o->getNameTag() === "§6EggWars Shop"){
          $e->setCancelled();
          $main->m[$d->getName()] = "ok";
          $main->EmptyShop($d);
        }
      }
      if($o instanceof Player && $d instanceof Player){
        if($main->IsInArena($o->getName())){
          $arena = $main->IsInArena($o->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          $Team = $main->PlayerTeamColor($o);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }else{
            $td = substr($d->getNameTag(), 0, 3);
            $to = substr($o->getNameTag(), 0, 3);
            if($td === $to){
              $e->setCancelled();
            }else{
              $this->sd[$o->getName()] = $d->getName();
            }
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $o->setHealth(20);
            if($main->EggSkin($arena, $Team)){
              $main->RemoveArenaPlayer($arena, $o->getName());
            }else{
              $o->teleport(new Position($ac->getNested("$Team.X"), $ac->getNested("$Team.Y"), $ac->getNested("$Team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $o->getNameTag()." §ewas killed by ".$d->getNameTag());
            }
            $o->getInventory()->clearAll();
          }
        }else{
          $e->setCancelled();
        }
      }
    }else{
      if($o instanceof Player){
        if($main->IsInArena($o->getName())){
          $arena = $main->IsInArena($o->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }
          $Team = $main->PlayerTeamColor($o);
          $message = null;
          if(!empty($this->sd[$o->getName()])){
            $sd = $main->getServer()->getPlayer($this->sd[$o->getName()]);
            if($sd instanceof Player){
              unset($this->sd[$o->getName()]);
              $message = $o->getNameTag()." §ewas killed by ".$sd->getNameTag();
            }else{
              $message = $o->getNameTag()." §edied!";
            }
          }else{
            $message = $o->getNameTag()." §edied!";
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $o->setHealth(20);
            if($main->EggSkin($arena, $Team)){
              $pname = $o->getName();
              $main->RemoveArenaPlayer($arena, $o->getName());
              $main->ArenaMessage($arena, $message);
              $main->ArenaMessage($arena, "§c$pname has been eliminated from the game.");

            }else{
              $o->teleport(new Position($ac->getNested("$Team.X"), $ac->getNested("$Team.Y"), $ac->getNested("$Team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $message);
            }
            $o->getInventory()->clearAll();
          }
        }
      }
    }
  }

  public function onMove(PlayerMoveEvent $e){
    $o = $e->getPlayer();
    $main = EggWars::getInstance();
    if ($o->getLevel()->getFolderName() === "ELobby"){
      if($e->getTo()->getFloorY() < 3){
        $e->getPlayer()->teleport(new Position("1000", "30", "-10"));
      }
    }
    if ($o->getLevel()->getFolderName() === "EWaiting"){
      if($e->getTo()->getFloorY() < 3){
        $e->getPlayer()->teleport(new Position("-3", "55", "0"));
      }
    }
  }

  public function envKapat(InventoryCloseEvent $e){
    $o = $e->getPlayer();
    $env = $e->getInventory();
    $main = EggWars::getInstance();
    if($env instanceof ChestInventory){
      if(!empty($main->m[$o->getName()])){
        $o->getLevel()->setBlock(new Vector3($o->getFloorX(), $o->getFloorY() - 4, $o->getFloorZ()), Block::get(Block::AIR));
        unset($main->m[$o->getName()]);
      }
    }
  }

  public function StoreEvent(InventoryTransactionEvent $e){
    $envanter = $e->getTransaction()->getInventories();
    $trans = $e->getTransaction()->getTransactions();
    $main = EggWars::getInstance();
    $o = null;
    $sb = null;
    $transfer = null;
    foreach($envanter as $env){
      $Held = $env->getHolder();
      if($Held instanceof Chest){
        $sb = $Held->getBlock();
      }
      if($Held instanceof Player){
        $o = $Held;
      }
    }

    foreach($trans as $t){
      if($t->getInventory() instanceof PlayerInventory){
        $transfer = $t;
      }
    }

    if($o != null and $sb != null and $transfer != null){

      $shopc = new Config($main->getDataFolder()."shop.yml", Config::YAML);
      $shop = $shopc->get("shop");
      $sandik = $o->getLevel()->getTile($sb);
      if($sandik instanceof Chest){
        $item = $transfer->getTargetItem();
        $si = $sandik->getInventory();

        if(empty($main->m[$o->getName()])){
          $itemler = 0;
          for($i=0; $i<count($shop); $i += 2){
            $slot = $i / 2;
            if($item->getId() === $shop[$i]){
              $itemler++;
            }
          }
          if($itemler === count($shop)){
            $main->m[$o->getName()] = 1;
          }
        }else{
          $e->setCancelled();
          if($item->getId() === 35 && $item->getDamage() === 14){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            $sandik->getInventory()->clearAll();
            for($i=0; $i<count($shop); $i += 2){
              $slot = $i / 2;
              $sandik->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
            }
          }
          $transSlot = 0;
          for($i=0; $i<$si->getSize(); $i++){
            if($si->getItem($i)->getId() === $item->getId()){
              $transSlot = $i;
              break;
            }
          }
          $is = $si->getItem(1)->getId();
          if($transSlot % 2 != 0 && ($is === 264 or $is === 265 or $is === 266)){
            $e->setCancelled();
          }
          if($item->getId() === 264 or $item->getId() === 265 or $item->getId() === 266){
            $e->setCancelled();
          }
          if($transSlot % 2 === 0 && ($is === 264 or $is === 265 or $is === 266)){
            $ucret = $si->getItem($transSlot + 1)->getCount();
            $para = $main->ItemId($o, $si->getItem($transSlot + 1)->getId());
            if($para >= $ucret){
              $o->getInventory()->removeItem(Item::get($si->getItem($transSlot + 1)->getId(), 0, $ucret));
              $aitemd = $si->getItem($transSlot);
              $aitem = Item::get($aitemd->getId(), $aitemd->getDamage(), $aitemd->getCount());
              $o->getInventory()->addItem($aitem);
            }
            $e->setCancelled();
          }
          if($is != 264 or $is != 265 or $is != 266){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            for($i=0; $i<count($shop); $i+=2){
              if($item->getId() === $shop[$i]){
                $sandik->getInventory()->clearAll();
                $gyer = $shop[$i+1];
                $slot = 0;
                for($e=0; $e<count($gyer); $e++){
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][0], 0, $gyer[$e][1]));
                  $slot++;
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][2], 0, $gyer[$e][3]));
                  $slot++;
                }
                break;
              }
            }
            $sandik->getInventory()->setItem($sandik->getInventory()->getSize() - 1, Item::get(Item::WOOL, 14, 1));
          }
        }
      }
    }

  }

  public function BlockBreakEvent(BlockBreakEvent $e){
    $o = $e->getPlayer();
    $b = $e->getBlock();
    if($o->getLevel()->getName() === "ELobby"){
      if (!$o->isOP()){
        $e->setCancelled();
      }
    }
    $main = EggWars::getInstance();
    if($main->IsInArena($o->getName())){
      $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
      $ad = $main->ArenaStatus($main->IsInArena($o->getName()));
      if($ad === "Lobby"){
        $e->setCancelled(true);
        return;
      }
      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$o->isOp()){
        $e->setCancelled(true);
      }
    }
  }

  public function BlockPlaceEvent(BlockPlaceEvent $e){
    $o = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($o->getLevel()->getName() === "ELobby"){
      if (!$o->isOP(){
        $e->setCancelled();
      }
    }
    $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
    if($main->IsInArena($o->getName())){
      $ad = $main->ArenaStatus($main->IsInArena($o->getName()));
      if($ad === "Lobby"){
        if($b->getId() === 35){
          $arena = $main->IsInArena($o->getName());
          $tyun = array_search($b->getDamage() ,$main->TeamSearcher());
          $marena = $main->AvailableTeams($arena);
          if(in_array($tyun, $marena)){
            $color = $main->Teams()[$tyun];
            $o->setNameTag($color.$o->getName());
            $o->sendPopup("§8» Team $color"."$tyun Selected!");
          }else{
            $o->sendPopup("§8» §cTeams must be equal!");
          }
          $e->setCancelled();
        }
        return;
      }

      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$o->isOp()){
        $e->setCancelled(true);
      }
    }
  }

}
