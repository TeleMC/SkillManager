<?php
namespace SkillManager;

use Monster\mob\MonsterBase;
use Monster\mob\PersonBase;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Archer {
    public function __construct(SkillManager $plugin) {
        $this->plugin = $plugin;
    }

    public function Active_1_Particle($player, $target) {
        $level = $target->getLevel();
        $vec = new Vector3($target->x, $target->y + 0.62, $target->z);
        for ($x = -1; $x < 2; $x++) {
            for ($z = -1; $z < 2; $z++) {
                for ($y = -1; $y < 2; $y++) {
                    $level->addParticle(new HugeExplodeParticle($vec->add($x, $y, $z)));
                }
            }
        }
    }

    public function Active_1_Shoot(Player $player) {
        if (is_null($player) || !$player instanceof Player)
            return;
        $bow = $player->getInventory()->getIteminHand();
        $nbt = Entity::createBaseNBT(
                $player->add(0, $player->getEyeHeight(), 0),
                $player->getDirectionVector(),
                ($player->yaw > 180 ? 360 : 0) - $player->yaw,
                -$player->pitch
        );
        $diff = $player->getItemUseDuration();
        $p = $diff / 20;
        $force = min((($p ** 2) + $p * 2) / 3, 1) * 2;
        $entity = Entity::createEntity("Arrow", $player->getLevel(), $nbt, $player, $force == 2);
        if ($entity instanceof Projectile) {
            if ($entity instanceof Arrow) {
                $entity->setPickupMode(Arrow::PICKUP_CREATIVE);
            }
            $ev = new EntityShootBowEvent($player, $bow, $entity, 7208);
            $player->getServer()->getPluginManager()->callEvent($ev);
            $entity = $ev->getProjectile();
            if ($ev->isCancelled()) {
                $entity->flagForDespawn();
                $player->getInventory()->sendContents($player);
            } else {
                $entity->setMotion($entity->getMotion()->multiply(3));
                if ($entity instanceof Projectile) {
                    $player->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($entity));
                    if ($projectileEv->isCancelled()) {
                        $ev->getProjectile()->flagForDespawn();
                    } else {
                        $ev->getProjectile()->spawnToAll();
                        $player->getLevel()->broadcastLevelSoundEvent($player, LevelSoundEventPacket::SOUND_BOW);
                        return $entity;
                    }
                } else {
                    $entity->spawnToAll();
                }
            }
        } else {
            $entity->spawnToAll();
        }
        return;
    }

    public function Active_1(Player $player) {
        $SkillName = "연속 사격";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if (isset($this->plugin->Archer_Active_1[$player->getId()])) {
            foreach ($this->plugin->Archer_Active_1[$player->getId()] as $key => $value) {
                if (!$value->isClosed()) {
                    return false;
                } else {
                    unset($this->plugin->Archer_Active_1[$player->getId()][$key]);
                }
            }
        }
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "아처") {
            //$player->sendPopup("{$this->plugin->pre} 활을 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "활을 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->ability->addBornPoint($player->getName(), "연사", 1);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->getScheduler()->scheduleRepeatingTask(
                new class($this->plugin, $player) extends Task {
                    public function __construct(SkillManager $plugin, Player $player) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->count = 0;
                    }

                    public function onRun($currentTick) {
                        if (0 <= $this->count && $this->count <= 2) {
                            $this->count++;
                            $arrow = $this->plugin->Archer->Active_1_Shoot($this->player);
                            $this->plugin->Archer_Active_1[$this->player->getId()][$this->count] = $arrow;
                        } elseif (3 <= $this->count && $this->count <= 5) {
                            $this->count++;
                            $this->plugin->Archer->Active_1_Shoot($this->player);
                        } else {
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        }
                    }
                }, 0.25 * 20);
        return true;
    }

    public function Active_2_Particle_2(Entity $target) {
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
    }

    public function Active_2(Player $player) {
        $SkillName = "스나이핑";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if (isset($this->plugin->Archer_Active_2[$player->getId()])) {
            if (!$this->plugin->Archer_Active_2[$player->getId()]->isClosed()) {
                return false;
            } else {
                unset($this->plugin->Archer_Active_2[$player->getId()]);
            }
        }
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "아처") {
            //$player->sendPopup("{$this->plugin->pre} 활을 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "활을 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->util->getMaxMp($player->getName()) * ($this->plugin->getSkillMana($SkillName, $SkillLevel)) / 100;
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $arrow = $this->Active_2_Shoot($player);
        $this->plugin->Archer_Active_2[$player->getId()] = $arrow;
        $this->plugin->ability->addBornPoint($player->getName(), "정조준", 1);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->getScheduler()->scheduleRepeatingTask(
                new class($this->plugin, $player, $this) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, Archer $archer) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->archer = $archer;
                        $this->count = 0;
                    }

                    public function onRun($currentTick) {
                        if (!isset($this->plugin->Archer_Active_2[$this->player->getId()])) {
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        } elseif (isset($this->plugin->Archer_Active_2[$this->player->getId()]) && !($arrow = $this->plugin->Archer_Active_2[$this->player->getId()])->isClosed() && $arrow->distance($this->player) <= 49) {
                            $this->archer->Active_2_Particle($arrow);
                        } elseif (($arrow = $this->plugin->Archer_Active_2[$this->player->getId()])->isClosed()) {
                            unset($this->plugin->Archer_Active_2[$this->player->getId()]);
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        } elseif (($arrow = $this->plugin->Archer_Active_2[$this->player->getId()])->distance($this->player) > 49) {
                            $arrow->close();
                            unset($this->plugin->Archer_Active_2[$this->player->getId()]);
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        }
                    }
                }, 0.25 * 20);
        return true;
    }

    public function Active_2_Shoot(Vector3 $player) {
        $shooter = null;
        if ($player instanceof Player)
            $shooter = $player;
        if ($player instanceof MonsterBase || $player instanceof PersonBase)
            $shooter = $player->getTarget();
        if ($shooter == null)
            return null;
        $bow = $shooter->getInventory()->getIteminHand();
        $nbt = Entity::createBaseNBT(
                $player->add(0, $shooter->getEyeHeight(), 0),
                $shooter->getDirectionVector(),
                ($shooter->yaw > 180 ? 360 : 0) - $shooter->yaw,
                -$shooter->pitch
        );
        $force = 2;
        $entity = Entity::createEntity("Arrow", $shooter->getLevel(), $nbt, $shooter, $force == 2);
        if ($entity instanceof Projectile) {
            if ($entity instanceof Arrow) {
                $entity->setPickupMode(Arrow::PICKUP_CREATIVE);
            }
            /*$shooter = $player;
            if(!$player instanceof Player)
              $shooter = $player->getTarget();*/
            $ev = new EntityShootBowEvent($shooter, $bow, $entity, 7208);
            $this->plugin->getServer()->getPluginManager()->callEvent($ev);
            $entity = $ev->getProjectile();
            if ($ev->isCancelled()) {
                $entity->flagForDespawn();
            } else {
                $peek = $shooter->getDirectionVector();
                $vec = new Vector3(3 * $peek->x, 3 * $peek->y, 3 * $peek->z);
                $entity->setMotion($vec);
                if ($entity instanceof Projectile) {
                    $this->plugin->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($entity));
                    if ($projectileEv->isCancelled()) {
                        $ev->getProjectile()->flagForDespawn();
                    } else {
                        $ev->getProjectile()->spawnToAll();
                        $player->getLevel()->broadcastLevelSoundEvent($shooter, LevelSoundEventPacket::SOUND_BOW);
                        return $entity;
                    }
                } else {
                    $entity->spawnToAll();
                }
            }
        } else {
            $entity->spawnToAll();
        }
        return;
    }

    public function Active_2_Particle(Entity $arrow) {
        for ($x = -0.125; $x <= 0.125; $x += 0.125) {
            for ($z = -0.125; $z <= 0.125; $z += 0.125) {
                for ($y = -0.125; $y <= 0.125; $y += 0.125) {
                    $arrow->level->addParticle(new CriticalParticle($arrow->add($x, $y, $z)));
                }
            }
        }
    }
}
