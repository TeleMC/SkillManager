<?php
namespace SkillManager;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Knight {
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

    public function Active_1(Player $player) {
        $SkillName = "순섬";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "나이트") {
            //$player->sendMessage("{$this->plugin->pre} 검을 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "검을 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->getSkillMana($SkillName, $SkillLevel);
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendMessage("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->ability->addBornPoint($player->getName(), "근력", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "베기", 1);
        $this->Active_1_Motion($player);
        $this->plugin->useSkill($player->getName(), $SkillName);
        //$player->sendMessage("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
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
                        }
                    }
                }, 1 * 20);
        return true;
    }

    public function Active_1_Motion($player) {
        $x = -\sin($player->yaw / 180 * M_PI);
        $z = \cos($player->yaw / 180 * M_PI);
        $peek = $player->getDirectionVector();
        $vec = new Vector3(5 * $peek->x, 0, 5 * $peek->z);
        $player->setMotion($vec);
        $this->plugin->SkillSound($player, 237);
        $this->plugin->SkillSound($player, 182);
    }

    public function Active_2_Particle($player, $target) {
        //// TODO: 파티클...
        $level = $target->getLevel();
        $vec = new Vector3($target->x, $target->y + 0.62, $target->z);
        for ($x = -1; $x < 2; $x++) {
            for ($z = -1; $z < 2; $z++) {
                for ($y = -1; $y < 2; $y++) {
                    $level->addParticle(new DustParticle($vec->add($x, $y, $z), 247, 234, 110));
                }
            }
        }
        $target->level->addParticle(new HugeExplodeSeedParticle($target));
        $this->plugin->SkillSound($player, 182);

    }

    public function Active_2(Player $player) {
        $SkillName = "파워 블러스트";
        if (!$this->plugin->isSkill($player->getName(), $SkillName))
            return false;
        if ($this->plugin->getSkillLevel($player->getName(), $SkillName) <= 0)
            return false;
        if ($this->plugin->isUsingSkill($player->getName(), $SkillName)) {
            $this->plugin->sendPopupDelay($player, "이미 시전이 준비되어있습니다.");
            return false;
        }
        if ($this->plugin->equipments->getEquipmentType($player->getEnderChestInventory()->getItem(0)->getCustomName()) !== "나이트") {
            //$player->sendMessage("{$this->plugin->pre} 검을 들고있지 않습니다!");
            $this->plugin->sendPopupDelay($player, "검을 들고있지 않습니다!");
            return false;
        }
        $SkillLevel = $this->plugin->getSkillLevel($player->getName(), $SkillName);
        $SkillMana = $this->plugin->util->getMaxMp($player->getName()) * ($this->plugin->getSkillMana($SkillName, $SkillLevel)) / 100;
        if ($this->plugin->util->getMp($player->getName()) < $this->plugin->getSkillMana($SkillName, $SkillLevel)) {
            //$player->sendMessage("{$this->plugin->pre} 마나가 부족합니다.");
            $this->plugin->sendPopupDelay($player, "마나가 부족합니다.");
            return false;
        }
        $this->plugin->util->reduceMp($player->getName(), $SkillMana);
        $this->plugin->ability->addBornPoint($player->getName(), "근력", 1);
        $this->plugin->ability->addBornPoint($player->getName(), "검기", 1);
        $this->plugin->useSkill($player->getName(), $SkillName);
        //$player->sendMessage("{$this->plugin->pre} 스킬, [ {$SkillName} ] (을)를 시전하였습니다.");
        $this->plugin->sendPopupDelay($player, "스킬, [ {$SkillName} ] 시전이 준비되었습니다.");
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
                        }
                    }
                }, 60 * 20);
        return true;
    }
}
