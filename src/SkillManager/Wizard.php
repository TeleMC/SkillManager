<?php
namespace SkillManager;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\level\Explosion;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Wizard {
    public function __construct(SkillManager $plugin) {
        $this->plugin = $plugin;
    }

    public function getDirectionVector_Reverse(Player $player) {
        $y = sin(deg2rad($player->pitch));
        $xz = -cos(deg2rad($player->pitch));
        $x = -$xz * sin(deg2rad($player->yaw));
        $z = $xz * cos(deg2rad($player->yaw));
        return $player->temporalVector->setComponents($x, $y, $z)->normalize();
    }

    public function Active_1_Particle_2(Entity $target) {
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
        $target->getLevel()->broadcastLevelSoundEvent($target, LevelSoundEventPacket::SOUND_EXPLODE);
    }

    public function Active_1(Player $player) {
        $SkillName = "마력탄";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "위자드") {
            //$player->sendPopup("{$this->plugin->pre} 스태프를 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "스태프를 들고있지 않습니다!");
            return false;
        }
        if (isset($this->plugin->Wizard_Active_1[$player->getId()]) && !$this->plugin->Wizard_Active_1[$player->getId()]->isClosed())
            return false;
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->ability->addBornPoint($player->getName(), "응축", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "마력탄", 1);
        $snowball = $this->Active_1_Shoot($player);
        $this->plugin->Wizard_Active_1[$player->getId()] = $snowball;
        $this->plugin->getScheduler()->scheduleRepeatingTask(
                new class($this->plugin, $this, $player) extends Task {
                    public function __construct(SkillManager $plugin, Wizard $wizard, Player $player) {
                        $this->plugin = $plugin;
                        $this->wizard = $wizard;
                        $this->player = $player;
                        $this->peek = $player->getDirectionVector();
                        $this->count = 0;
                    }

                    public function onRun($currentTick) {
                        if (!isset($this->plugin->Wizard_Active_1[$this->player->getId()])) {
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                            return true;
                        }
                        if (!($snowball = $this->plugin->Wizard_Active_1[$this->player->getId()])->isClosed()) {
                            if ($this->player->distance($snowball) > 49) {
                                $snowball->close();
                                $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                                unset($this->plugin->Wizard_Active_1[$this->player->getId()]);
                                return true;
                            }
                            $this->wizard->Active_1_Particle($snowball);
                            $vec = new Vector3(3 * $this->peek->x, 3 * $this->peek->y, 3 * $this->peek->z);
                            $snowball->setMotion($vec);
                        } else {
                            unset($this->plugin->Wizard_Active_1[$this->player->getId()]);
                            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                        }
                    }
                }, 0.1);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        return true;
    }

    public function Active_1_Shoot(Player $player) {
        $bow = $player->getInventory()->getIteminHand();
        $nbt = Entity::createBaseNBT(
                $player->add(0, $player->getEyeHeight(), 0),
                $player->getDirectionVector(),
                ($player->yaw > 180 ? 360 : 0) - $player->yaw,
                -$player->pitch
        );
        $entity = Entity::createEntity("Snowball", $player->getLevel(), $nbt, $player);
        if ($entity instanceof Projectile) {
            $peek = $player->getDirectionVector();
            $vec = new Vector3(3 * $peek->x, 3 * $peek->y, 3 * $peek->z);
            $entity->setMotion($vec);
            if ($entity instanceof Projectile) {
                $player->getServer()->getPluginManager()->callEvent($projectileEv = new ProjectileLaunchEvent($entity));
                if ($projectileEv->isCancelled()) {
                    $entity->flagForDespawn();
                } else {
                    $entity->setScale(10);
                    $entity->spawnToAll();
                    $player->getLevel()->broadcastLevelSoundEvent($player, LevelSoundEventPacket::SOUND_REMEDY);
                    return $entity;
                }
            } else {
                $entity->spawnToAll();
            }
        } else {
            $entity->spawnToAll();
        }
        return;
    }

    public function Active_1_Particle(Entity $snowball) {
        for ($x = -0.125; $x <= 0.125; $x += 0.125) {
            for ($z = -0.125; $z <= 0.125; $z += 0.125) {
                for ($y = -0.125; $y <= 0.125; $y += 0.125) {
                    $snowball->level->addParticle(new DustParticle($snowball->add($x, $y, $z), 149, 54, 255));
                }
            }
        }
    }

    public function Active_2_Particle(Player $player, Entity $target) {
        $packet = new AddEntityPacket();
        $packet->entityRuntimeId = Entity::$entityCount++;
        $packet->position = $target;
        $packet->type = 93;
        $packet->metadata = array();
        $this->plugin->getServer()->broadcastPacket($this->plugin->getServer()->getOnlinePlayers(), $packet);
        /*$explosion = new Explosion($target, 1);
        $explosion->explodeB();*/
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $this->plugin->SkillSound($player, 237);
        $this->plugin->SkillSound($player, 48);
    }

    public function Active_2_Particle_2(Player $player, Entity $target) {
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $this->plugin->SkillSound($player, 48);
    }

    public function Active_2(Player $player) {
        $SkillName = "라이트닝 플레어";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            //$player->sendPopup("{$this->plugin->pre} 이미 시전중입니다.");
            $this->plugin->sendPopupDelay($player, "이미 시전중입니다.");
            return false;
        }
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "위자드") {
            //$player->sendPopup("{$this->plugin->pre} 스태프를 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "스태프를 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->util->getMaxMp($player->getName()) * ($this->plugin->getSkillMana($SkillName, $SkillLevel)) / 100;
        if ($this->plugin->util->getMp($player->getName()) < $SkillMana) {
            //$player->sendPopup("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->SkillSound($player, 232);
        $this->plugin->useSkill($player->getName(), $SkillName);
        $this->plugin->ability->addBornPoint($player->getName(), "마력 증폭", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "마력 방출", 1);
        $this->plugin->getScheduler()->scheduleDelayedTask(
                new class($this->plugin, $player, $SkillName, $this->plugin->list[$player->getName()][$SkillName]) extends Task {
                    public function __construct(SkillManager $plugin, Player $player, string $SkillName, int $count) {
                        $this->plugin = $plugin;
                        $this->player = $player;
                        $this->skill = $SkillName;
                        $this->count = $count;
                    }

                    public function onRun($currentTick) {
                        if ($this->player instanceof Player && $this->plugin->isUsingSkill($this->player->getName(), $this->skill) && $this->count == $this->plugin->list[$this->player->getName()][$this->skill]) {
                            $this->plugin->endSkill($this->player->getName(), $this->skill);
                            //$this->player->sendPopup("{$this->plugin->pre} 스킬, [ {$this->skill} ] (이)가 미사용으로 시전이 취소되었습니다.");
                            $this->plugin->sendPopupDelay($this->player, "스킬, [ {$this->skill} ] (이)가 미사용으로 시전이 취소되었습니다.");
                        }
                    }
                }, 60 * 20);
        //$player->sendPopup("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전할 준비가 되었습니다.");
        return true;
    }
}
