<?php
namespace SkillManager;

use Monster\mob\MonsterBase;
use Monster\mob\PersonBase;
use muqsit\invmenu\inventories\DoubleChestInventory as DoubleInventory;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\item\Item;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\Player;

class EventListener implements Listener {
    public function __construct(SkillManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $ev) {
        if (!isset($this->plugin->udata[$ev->getPlayer()->getName()])) {
            $this->plugin->udata[$ev->getPlayer()->getName()]["스킬 포인트"] = 0;
            $this->plugin->udata[$ev->getPlayer()->getName()]["스킬"]["산뜻한 발걸음"] = 1;
            $this->plugin->udata[$ev->getPlayer()->getName()]["스킬"]["재생의 오라"] = 1;
            $this->plugin->udata[$ev->getPlayer()->getName()]["스킬"]["강타"] = 1;
            $this->plugin->udata[$ev->getPlayer()->getName()]["etc"] = [];
            $this->plugin->udata[$ev->getPlayer()->getName()]["시전중"] = [];
        }
        if (!isset($this->plugin->udata[$ev->getPlayer()->getName()]["스킬"]["가속"]))
            $this->plugin->udata[$ev->getPlayer()->getName()]["스킬"]["가속"] = 1;
        if (!isset($this->plugin->udata[$ev->getPlayer()->getName()]["etc"]))
            $this->plugin->udata[$ev->getPlayer()->getName()]["etc"] = [];
        if ($this->plugin->util->isRegistered($ev->getPlayer()->getName())) {
            $this->plugin->check_skill($ev->getPlayer()->getName());
            $this->plugin->adjust_skill($ev->getPlayer()->getName());
        }
        $ev->getPlayer()->setAllowFlight(true);
    }

    public function onQuit(PlayerQuitEvent $ev) {
        $this->plugin->endAllSkill($ev->getPlayer()->getName());
        if (isset($this->plugin->skillInv[$ev->getPlayer()->getName()]))
            unset($this->plugin->skillInv[$ev->getPlayer()->getName()]);
        if (isset($this->plugin->Archer_Active_1[$ev->getPlayer()->getId()]))
            unset($this->plugin->Archer_Active_1[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Archer_Active_2[$ev->getPlayer()->getId()]))
            unset($this->plugin->Archer_Active_2[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Wizard_Active_1[$ev->getPlayer()->getId()]))
            unset($this->plugin->Wizard_Active_1[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Wizard_Active_2[$ev->getPlayer()->getId()]))
            unset($this->plugin->Wizard_Active_2[$ev->getPlayer()->getId()]);
        unset($this->login[$ev->getPlayer()->getName()]);
    }

    public function onDeath(PlayerDeathEvent $ev) {
        $this->plugin->endAllSkill($ev->getPlayer()->getName());
        if (isset($this->plugin->Archer_Active_1[$ev->getPlayer()->getId()]))
            unset($this->plugin->Archer_Active_1[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Archer_Active_2[$ev->getPlayer()->getId()]))
            unset($this->plugin->Archer_Active_2[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Wizard_Active_1[$ev->getPlayer()->getId()]))
            unset($this->plugin->Wizard_Active_1[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->Wizard_Active_2[$ev->getPlayer()->getId()]))
            unset($this->plugin->Wizard_Active_2[$ev->getPlayer()->getId()]);
    }

    public function onDrop(PlayerDropItemEvent $ev) {
        if (isset($this->plugin->sdata[$this->ConvertName($ev->getItem()->getCustomName())]))
            $ev->setCancelled(true);
    }

    public function ConvertName(string $string) {
        $string = str_replace(
                ["§0", "§1", "§2", "§3", "§4", "§5", "§6", "§7", "§8", "§9", "§a", "§b", "§c", "§d", "§e", "§f", "§l", "§o", "§r"],
                ["", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
                $string);
        $string = explode("\n", $string)[0];
        return $string;
    }

    public function onClose(InventoryCloseEvent $ev) {
        if (isset($this->plugin->skillInv[$ev->getPlayer()->getName()]))
            unset($this->plugin->skillInv[$ev->getPlayer()->getName()]);
    }

    public function SkillWindow(InventoryTransactionEvent $ev) {
        foreach ($ev->getTransaction()->getActions() as $action) {
            if ($ev->getTransaction()->getSource() instanceof Player) {
                if ($action instanceof CreativeInventoryAction) return;
                if ($action instanceof DropItemAction) return;
                $player = $ev->getTransaction()->getSource();
                $item = $action->getTargetItem();
                if (isset($this->plugin->skillInv[$player->getName()])) {
                    if ($action->getInventory() instanceof DoubleInventory) {
                        if (46 <= $action->getSlot() && $action->getSlot() <= 49) {
                            if (isset($this->plugin->sdata[$this->ConvertName($item->getCustomName())])) {
                                $player->getInventory()->setItem($action->getSlot() - 45, $item);
                            } else {
                                $a = new Item(383, 38);
                                $a->setCustomName("§r§c빈칸");
                                $player->getInventory()->setItem($action->getSlot() - 45, $a);
                            }
                        } elseif (($action->getSlot() == 45 || 50 <= $action->getSlot()) || 36 <= $action->getSlot() && $action->getSlot() < 45) {
                            $ev->setCancelled(true);
                        }
                    } elseif ($action->getInventory() instanceof PlayerInventory) {
                        $ev->setCancelled(true);
                    }
                } else {
                    if ($action->getInventory() instanceof PlayerInventory) {
                        if (isset($this->plugin->sdata[$this->ConvertName($action->getSourceItem()->getCustomName())]) && !(1 <= $action->getSlot() && $action->getSlot() <= 4)) {
                            $ev->setCancelled(true);
                            $player->getInventory()->setItem($action->getSlot(), new Item(0, 0));
                        }
                    }
                }
            }
        }
    }

    public function onMove(PlayerMoveEvent $ev) {
        $player = $ev->getPlayer();
        if ($this->plugin->isUsingSkill($player->getName(), "순섬")) {
            foreach ($player->level->getNearbyEntities($player->boundingBox->expandedCopy(5, 5, 5), $player) as $target) {
                if ($target instanceof MonsterBase || $target instanceof PersonBase) {
                    $source = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                    $target->attack($source);
                    break;
                }
            }
        }
        if ($this->plugin->isSkill($player->getName(), "프리스트의 오라") && $this->plugin->getSkillLevel($player->getName(), "프리스트의 오라") > 0 && $this->plugin->util->iswar($player->getName())) {
            $this->Phrase($player);
        }
    }

    public function Phrase(Player $player) {
        if (!isset($this->time[$player->getName()]["프리스트의 오라"]))
            $this->time[$player->getName()]["프리스트의 오라"] = microtime(true);
        if (microtime(true) - $this->time[$player->getName()]["프리스트의 오라"] <= 1.5)
            return false;
        $this->time[$player->getName()]["프리스트의 오라"] = microtime(true);
        $diff = 2.5;
        $r = 9;
        for ($theta = 0; $theta <= 360; $theta += $diff) {
            $x = $r * sin($theta);
            $z = $r * cos($theta);
            $y = $player->getFloorY();
            if ($player->level->getBlock($player->add($x, 0, $z))->getId() == 0) {
                for ($i = 0; $i <= 5; $i++) {
                    if ($player->level->getBlock($player->add($x, -1 * $i, $z))->getId() !== 0) {
                        $y = $player->getFloorY() - $i + 1;
                        break;
                    }
                }
            } else {
                for ($i = 0; $i <= 5; $i++) {
                    if ($player->level->getBlock($player->add($x, $i, $z))->getId() == 0) {
                        $y = $player->getFloorY() + $i;
                        break;
                    }
                }
            }
            $player->getLevel()->addParticle(new CriticalParticle(new Vector3($player->x + $x, $y, $player->z + $z)));
        }
    }

    public function onTouch(PlayerInteractEvent $ev) {
        $player = $ev->getPlayer();
        $block = $ev->getBlock();
        if ($this->plugin->isUsingSkill($player->getName(), "파워 블러스트")) {
            $count = 0;
            foreach ($player->level->getNearbyEntities(new AxisAlignedBB($block->x - 9, $block->y - 9, $block->z - 9, $block->x + 9, $block->y + 9, $block->z + 9)) as $target) {
                if (3 <= $count) break;
                if ($target instanceof MonsterBase || $target instanceof PersonBase) {
                    $source = new EntityDamageByEntityEvent($player, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0);
                    $target->attack($source);
                    $count++;
                }
            }
        }
    }

    public function onFlyJump(PlayerToggleFlightEvent $ev) {
        $player = $ev->getPlayer();
        if ($player->getGamemode() !== 1 && !$player->isOp()) {
            $player->setFlying(false);
            $ev->setCancelled(true);
        }
        /*if($player->getGamemode() == 0 && $this->plugin->isSkill($player->getName(), "가속")){
          $player->setFlying(false);
          $ev->setCancelled(true);
          $this->plugin->Adventurer->Active_3($player);
        }*/
    }

    /*public function onPacketSend(DataPacketSendEvent $ev){
      $pk = $ev->getPacket();
      $player = $ev->getPlayer();
      if(stripos($pk->getName(), "Move") === false && stripos($pk->getName(), "Batch") === false  && stripos($pk->getName(), "TextPacket") === false && stripos($pk->getName(), "SetDisplayObjectivePacket") === false && stripos($pk->getName(), "SetScorePacket") === false){
        if(stripos($pk->getName(), "AdventureSettingsPacket") !== false){
          $player->sendMessage("Awd");
          if($player->getGamemode() !== 1 && !$player->isOp()){
            $ev->setCancelled(true);
            //$ev->getPlayer()->setAllowFlight(true);
          }
          if($player->getGamemode() == 0 && $this->plugin->isSkill($player->getName(), "가속")){
            $ev->setCancelled(true);
            //$ev->getPlayer()->setAllowFlight(true);
            $this->plugin->Adventurer->Active_3($player);
          }
        }
      }
      if($pk instanceof AdventureSettingsPacket && isset($this->login[$player->getName()])){
        $player->sendMessage("Awd");
        if($player->getGamemode() !== 1 && !$player->isOp()){
          $ev->setCancelled(true);
          $ev->getPlayer()->setAllowFlight(true);
        }
        if($player->getGamemode() == 0 && $this->plugin->isSkill($player->getName(), "가속")){
          $ev->setCancelled(true);
          $ev->getPlayer()->setAllowFlight(true);
          $this->plugin->Adventurer->Active_3($player);
        }
      }
      }*/

    public function onPacketReceived(DataPacketReceiveEvent $ev) {
        $pk = $ev->getPacket();
        $player = $ev->getPlayer();
        if ($pk instanceof SetLocalPlayerAsInitializedPacket) {
            $ev->getPlayer()->setAllowFlight(true);
            $this->login[$player->getName()] = true;
        }
        if ($pk instanceof AdventureSettingsPacket) {
            if ($player->getGamemode() !== 1 && !$player->isOp()) {
                $player->setFlying(false);
                $ev->getPlayer()->setAllowFlight(true);
                $ev->setCancelled(true);
            }
            if ($player->getGamemode() == 0 || $player->getGamemode() == 2 && $this->plugin->isSkill($player->getName(), "가속")) {
                $player->setFlying(false);
                $ev->setCancelled(true);
                $this->plugin->Adventurer->Active_3($player);
            }
        }
    }

    /*public function onMove_Entity(EntityMotionEvent $ev){
      $this->plugin->getServer()->broadcastMessage("Asdf");
      foreach($this->plugin->Wizard_Active_1 as $key => $snowball){
        if($snowball->isClosed())
          unset($this->plugin->Wizard_Active_1[$key]);
        if(!$snowball->isClosed()){
          $snowball->getLevel()->addParticle(new CriticalParticle($snowball));
        }
      }
    }*/
}
